<?php

namespace App\Services;

use App\Models\Artwork;
use App\Models\ArtworkLike;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ArtworkService
{
    public function __construct(
        private readonly ImageStorageService $imageStorageService,
    ) {}

    /**
     * Retrieve paginated public artworks ordered by likes count DESC, then newest DESC.
     */
    public function getPublicCatalog(array $filters, ?string $visitorTokenHash): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 12);
        $perPage = max(1, min($perPage, 48));
        $search = trim((string) ($filters['search'] ?? ''));

        $query = Artwork::query()
            ->withVisitorLikeStatus($visitorTokenHash);

        if ($search !== '') {
            $query->where(function ($subQuery) use ($search): void {
                $subQuery
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('participant_name', 'like', "%{$search}%");
            });
        }

        return $query
            ->orderedByPopularity()
            ->paginate($perPage);
    }

    /**
     * Retrieve a single artwork with likes count and visitor status.
     */
    public function getPublicDetail(Artwork $artwork, ?string $visitorTokenHash): Artwork
    {
        return Artwork::query()
            ->withVisitorLikeStatus($visitorTokenHash)
            ->findOrFail($artwork->id);
    }

    /**
     * Store a newly uploaded artwork and ensure file cleanup if DB persistence fails.
     */
    public function createArtwork(array $data, UploadedFile $image): Artwork
    {
        $storedPath = $this->imageStorageService->storeArtworkImage($image);

        try {
            $artwork = DB::transaction(function () use ($data, $storedPath): Artwork {
                return Artwork::create([
                    'participant_name' => trim($data['participant_name']),
                    'title' => trim($data['title']),
                    'description' => isset($data['description']) && trim((string) $data['description']) !== ''
                        ? trim((string) $data['description'])
                        : null,
                    'image_path' => $storedPath,
                ]);
            });
        } catch (\Throwable $e) {
            $this->imageStorageService->deleteIfUnused($storedPath);
            throw $e;
        }

        $artwork->likes_count = 0;
        $artwork->is_liked_by_visitor = 0;

        return $artwork;
    }

    /**
     * Register a unique like for the given visitor hash using DB unique constraints.
     *
     * @return array{liked: bool, already_liked: bool, likes_count: int}
     */
    public function likeArtwork(Artwork $artwork, string $visitorTokenHash): array
    {
        $created = false;

        try {
            DB::transaction(function () use ($artwork, $visitorTokenHash, &$created): void {
                ArtworkLike::create([
                    'artwork_id' => $artwork->id,
                    'visitor_token_hash' => $visitorTokenHash,
                ]);
                $created = true;
            });
        } catch (UniqueConstraintViolationException $e) {
            $created = false;
        } catch (QueryException $e) {
            // SQLSTATE 23000 indicates integrity/unique constraint violation in MySQL.
            if ($e->getCode() === '23000') {
                $created = false;
            } else {
                throw $e;
            }
        }

        $likesCount = $artwork->likes()->count();

        return [
            'liked' => $created,
            'already_liked' => ! $created,
            'likes_count' => $likesCount,
        ];
    }

    /**
     * Return like status and count for a single artwork and visitor hash.
     *
     * @return array{artwork_id: int, is_liked: bool, likes_count: int}
     */
    public function getLikeStatus(Artwork $artwork, ?string $visitorTokenHash): array
    {
        $likesCount = $artwork->likes()->count();
        $isLiked = $visitorTokenHash
            ? $artwork->likes()->where('visitor_token_hash', $visitorTokenHash)->exists()
            : false;

        return [
            'artwork_id' => $artwork->id,
            'is_liked' => $isLiked,
            'likes_count' => $likesCount,
        ];
    }

    /**
     * Aggregate dashboard metrics, recent artworks, and top-liked artworks.
     *
     * @return array<string, mixed>
     */
    public function getDashboardSummary(): array
    {
        $totalArtworks = Artwork::query()->count();
        $totalLikes = ArtworkLike::query()->count();
        $totalParticipants = (int) Artwork::query()
            ->selectRaw('COUNT(DISTINCT LOWER(TRIM(participant_name))) as total')
            ->value('total');
        $recentArtworksCount = Artwork::query()
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $recentArtworks = Artwork::query()
            ->withCount('likes')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(6)
            ->get();

        $topArtworks = Artwork::query()
            ->withCount('likes')
            ->orderedByPopularity()
            ->limit(5)
            ->get();

        return [
            'stats' => [
                'total_artworks' => $totalArtworks,
                'total_likes' => $totalLikes,
                'total_participants' => $totalParticipants,
                'recent_artworks_count' => $recentArtworksCount,
            ],
            'recent_artworks' => $recentArtworks,
            'top_artworks' => $topArtworks,
        ];
    }

    /**
     * Retrieve paginated artworks for administrator management with search, filter, and sorting.
     */
    public function getAdminCatalog(array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 10);
        $perPage = max(1, min($perPage, 50));
        $search = trim((string) ($filters['search'] ?? ''));
        $sort = (string) ($filters['sort'] ?? 'newest');
        $filter = (string) ($filters['filter'] ?? 'all');

        $query = Artwork::query()->withCount('likes');

        if ($search !== '') {
            $query->where(function ($subQuery) use ($search): void {
                $subQuery
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('participant_name', 'like', "%{$search}%");
            });
        }

        match ($filter) {
            'with_likes' => $query->has('likes'),
            'no_likes' => $query->doesntHave('likes'),
            'this_week' => $query->where('created_at', '>=', now()->subDays(7)),
            default => null,
        };

        match ($sort) {
            'most_liked' => $query->orderByDesc('likes_count')->orderByDesc('created_at')->orderByDesc('id'),
            'least_liked' => $query->orderBy('likes_count')->orderByDesc('created_at')->orderByDesc('id'),
            'oldest' => $query->orderBy('created_at')->orderBy('id'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };

        return $query->paginate($perPage);
    }

    /**
     * Update an existing artwork and optionally replace its stored image safely.
     */
    public function updateArtwork(Artwork $artwork, array $data, ?UploadedFile $newImage = null): Artwork
    {
        $oldImagePath = $artwork->image_path;
        $newImagePath = $newImage ? $this->imageStorageService->storeArtworkImage($newImage) : null;

        try {
            DB::transaction(function () use ($artwork, $data, $newImagePath): void {
                $artwork->update([
                    'participant_name' => trim($data['participant_name']),
                    'title' => trim($data['title']),
                    'description' => isset($data['description']) && trim((string) $data['description']) !== ''
                        ? trim((string) $data['description'])
                        : null,
                    'image_path' => $newImagePath ?? $artwork->image_path,
                ]);
            });
        } catch (\Throwable $e) {
            if ($newImagePath !== null) {
                $this->imageStorageService->deleteIfUnused($newImagePath);
            }
            throw $e;
        }

        if ($newImagePath !== null && $oldImagePath !== $newImagePath) {
            $this->imageStorageService->deleteIfUnused($oldImagePath, $artwork->id);
        }

        return Artwork::query()->withCount('likes')->findOrFail($artwork->id);
    }

    /**
     * Delete an artwork (likes cascade in DB) and remove image if no longer referenced.
     */
    public function deleteArtwork(Artwork $artwork): void
    {
        $imagePath = $artwork->image_path;
        $artworkId = $artwork->id;

        DB::transaction(function () use ($artwork): void {
            $artwork->delete();
        });

        $this->imageStorageService->deleteIfUnused($imagePath, $artworkId);
    }
}
