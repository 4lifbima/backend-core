<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArtworkResource;
use App\Models\Artwork;
use App\Services\ArtworkService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdminDashboardController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ArtworkService $artworkService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Artwork::class);

        $summary = $this->artworkService->getDashboardSummary();

        return $this->successResponse([
            'stats' => $summary['stats'],
            'recent_artworks' => ArtworkResource::collection($summary['recent_artworks'])->resolve($request),
            'top_artworks' => ArtworkResource::collection($summary['top_artworks'])->resolve($request),
        ], 'Ringkasan dashboard berhasil dimuat.');
    }
}
