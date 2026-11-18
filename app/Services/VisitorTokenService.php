<?php

namespace App\Services;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

class VisitorTokenService
{
    public const COOKIE_NAME = 'uk_visitor_token';

    public const HEADER_NAME = 'X-Visitor-Token';

    /**
     * Resolve an existing valid server-signed visitor token from the request.
     *
     * @return array{token: string, token_id: string, hash: string}|null
     */
    public function resolveExisting(Request $request): ?array
    {
        $candidates = [
            $request->cookie(self::COOKIE_NAME),
            $request->header(self::HEADER_NAME),
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || $candidate === '') {
                continue;
            }

            $parsed = $this->parseSignedToken($candidate);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        return null;
    }

    /**
     * Resolve an existing valid token or mint a fresh cryptographically secure token.
     *
     * @return array{token: string, token_id: string, hash: string, is_new: bool}
     */
    public function resolveOrCreate(Request $request): array
    {
        $existing = $this->resolveExisting($request);

        if ($existing !== null) {
            return [
                ...$existing,
                'is_new' => false,
            ];
        }

        $tokenId = bin2hex(random_bytes(32));
        $signature = $this->signTokenId($tokenId);

        return [
            'token' => $tokenId.'.'.$signature,
            'token_id' => $tokenId,
            'hash' => $this->hashForStorage($tokenId),
            'is_new' => true,
        ];
    }

    /**
     * Attach the visitor token cookie and response header when issued.
     */
    public function attachToResponse(Response $response, Request $request, array $resolved): Response
    {
        if (! ($resolved['is_new'] ?? false)) {
            return $response;
        }

        $cookie = Cookie::create(
            name: self::COOKIE_NAME,
            value: $resolved['token'],
            expire: now()->addYear(),
            path: '/',
            domain: null,
            secure: $request->isSecure(),
            httpOnly: true,
            raw: false,
            sameSite: Cookie::SAMESITE_LAX,
        );

        $response->headers->setCookie($cookie);
        $response->headers->set(self::HEADER_NAME, $resolved['token']);

        return $response;
    }

    /**
     * Validate signature and return parsed token data if authentic.
     *
     * @return array{token: string, token_id: string, hash: string}|null
     */
    private function parseSignedToken(string $rawToken): ?array
    {
        $parts = explode('.', trim($rawToken), 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$tokenId, $signature] = $parts;

        if (strlen($tokenId) !== 64 || ! ctype_xdigit($tokenId)) {
            return null;
        }

        if (strlen($signature) !== 64 || ! ctype_xdigit($signature)) {
            return null;
        }

        $expectedSignature = $this->signTokenId($tokenId);
        if (! hash_equals($expectedSignature, $signature)) {
            return null;
        }

        return [
            'token' => $tokenId.'.'.$signature,
            'token_id' => $tokenId,
            'hash' => $this->hashForStorage($tokenId),
        ];
    }

    private function signTokenId(string $tokenId): string
    {
        return hash_hmac('sha256', 'visitor-token:'.$tokenId, (string) config('app.key'));
    }

    private function hashForStorage(string $tokenId): string
    {
        return hash_hmac('sha256', 'visitor-storage:'.$tokenId, (string) config('app.key'));
    }
}
