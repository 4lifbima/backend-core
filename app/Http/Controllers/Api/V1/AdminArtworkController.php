<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateArtworkRequest;
use App\Http\Resources\ArtworkResource;
use App\Models\Artwork;
use App\Services\ArtworkService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class AdminArtworkController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ArtworkService $artworkService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Artwork::class);

        $paginator = $this->artworkService->getAdminCatalog(
            $request->only(['search', 'sort', 'filter', 'per_page']),
        );

        return $this->successResponse([
            'items' => ArtworkResource::collection($paginator->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ], 'Daftar karya administrator berhasil dimuat.');
    }

    public function show(Request $request, Artwork $artwork): JsonResponse
    {
        Gate::authorize('view', $artwork);

        $detail = $this->artworkService->getPublicDetail($artwork, null);

        return $this->successResponse(
            ArtworkResource::make($detail)->resolve($request),
            'Detail karya berhasil dimuat.',
        );
    }

    public function update(UpdateArtworkRequest $request, Artwork $artwork): JsonResponse
    {
        Gate::authorize('update', $artwork);

        $updated = $this->artworkService->updateArtwork(
            $artwork,
            $request->validated(),
            $request->file('image'),
        );

        return $this->successResponse(
            ArtworkResource::make($updated)->resolve($request),
            'Informasi karya berhasil diperbarui.',
        );
    }

    public function destroy(Artwork $artwork): Response
    {
        Gate::authorize('delete', $artwork);

        $this->artworkService->deleteArtwork($artwork);

        return $this->noContentResponse();
    }
}
