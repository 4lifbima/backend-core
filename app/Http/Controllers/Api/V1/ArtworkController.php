<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreArtworkRequest;
use App\Http\Resources\ArtworkResource;
use App\Models\Artwork;
use App\Services\ArtworkService;
use App\Services\VisitorTokenService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ArtworkController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ArtworkService $artworkService,
        private readonly VisitorTokenService $visitorTokenService,
    ) {}

    public function index(Request $request): Response
    {
        $visitor = $this->visitorTokenService->resolveOrCreate($request);
        $paginator = $this->artworkService->getPublicCatalog(
            $request->only(['search', 'per_page']),
            $visitor['hash'],
        );

        $response = $this->successResponse([
            'items' => ArtworkResource::collection($paginator->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ], 'Daftar karya berhasil dimuat.');

        return $this->visitorTokenService->attachToResponse($response, $request, $visitor);
    }

    public function show(Request $request, Artwork $artwork): Response
    {
        $visitor = $this->visitorTokenService->resolveOrCreate($request);
        $detail = $this->artworkService->getPublicDetail($artwork, $visitor['hash']);

        $response = $this->successResponse(
            ArtworkResource::make($detail)->resolve($request),
            'Detail karya berhasil dimuat.',
        );

        return $this->visitorTokenService->attachToResponse($response, $request, $visitor);
    }

    public function store(StoreArtworkRequest $request): Response
    {
        $artwork = $this->artworkService->createArtwork(
            $request->validated(),
            $request->file('image'),
        );

        return $this->successResponse(
            ArtworkResource::make($artwork)->resolve($request),
            'Karya berhasil diunggah ke katalog.',
            201,
        );
    }

    public function like(Request $request, Artwork $artwork): Response
    {
        $visitor = $this->visitorTokenService->resolveOrCreate($request);
        $result = $this->artworkService->likeArtwork($artwork, $visitor['hash']);

        if ($result['already_liked']) {
            $response = $this->errorResponse(
                'Anda sudah memberikan Like pada karya ini.',
                409,
                ['artwork' => ['Setiap pengunjung hanya dapat memberikan satu Like untuk satu karya.']],
                [
                    'artwork_id' => $artwork->id,
                    'is_liked' => true,
                    'likes_count' => $result['likes_count'],
                ],
            );

            return $this->visitorTokenService->attachToResponse($response, $request, $visitor);
        }

        $response = $this->successResponse([
            'artwork_id' => $artwork->id,
            'is_liked' => true,
            'likes_count' => $result['likes_count'],
        ], 'Terima kasih atas apresiasi Anda.', 201);

        return $this->visitorTokenService->attachToResponse($response, $request, $visitor);
    }

    public function likeStatus(Request $request, Artwork $artwork): Response
    {
        $visitor = $this->visitorTokenService->resolveOrCreate($request);
        $status = $this->artworkService->getLikeStatus($artwork, $visitor['hash']);

        $response = $this->successResponse($status, 'Status Like berhasil dimuat.');

        return $this->visitorTokenService->attachToResponse($response, $request, $visitor);
    }
}
