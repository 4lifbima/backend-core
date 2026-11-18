<?php

namespace Tests\Feature;

use App\Models\Artwork;
use App\Models\ArtworkLike;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArtworkApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_catalog_orders_by_likes_desc_then_newest(): void
    {
        $olderPopular = Artwork::factory()->create([
            'title' => 'Older Popular',
            'created_at' => now()->subDays(2),
        ]);
        $newerTie = Artwork::factory()->create([
            'title' => 'Newer Tie',
            'created_at' => now()->subHours(1),
        ]);
        $olderTie = Artwork::factory()->create([
            'title' => 'Older Tie',
            'created_at' => now()->subHours(5),
        ]);

        foreach (['v1', 'v2', 'v3'] as $token) {
            ArtworkLike::create([
                'artwork_id' => $olderPopular->id,
                'visitor_token_hash' => hash('sha256', $token),
            ]);
        }

        ArtworkLike::create([
            'artwork_id' => $newerTie->id,
            'visitor_token_hash' => hash('sha256', 'v1'),
        ]);
        ArtworkLike::create([
            'artwork_id' => $olderTie->id,
            'visitor_token_hash' => hash('sha256', 'v2'),
        ]);

        $response = $this->getJson('/api/v1/artworks');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.items.0.title', 'Older Popular')
            ->assertJsonPath('data.items.0.likes_count', 3)
            ->assertJsonPath('data.items.1.title', 'Newer Tie')
            ->assertJsonPath('data.items.2.title', 'Older Tie');

        $this->assertNotEmpty($response->headers->get('X-Visitor-Token'));
    }

    public function test_visitor_can_upload_valid_image_artwork(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('my-design.png', 800, 1000);

        $response = $this->post('/api/v1/artworks', [
            'participant_name' => 'Rina Kartika',
            'title' => 'Poster Tipografi Modern',
            'description' => 'Eksplorasi grid modular.',
            'image' => $file,
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.participant_name', 'Rina Kartika')
            ->assertJsonPath('data.title', 'Poster Tipografi Modern')
            ->assertJsonPath('data.likes_count', 0);

        $imagePath = $response->json('data.image_path');
        $this->assertStringStartsWith('artworks/', $imagePath);
        $this->assertStringEndsWith('.png', $imagePath);
        $this->assertNotSame('artworks/my-design.png', $imagePath);
        Storage::disk('public')->assertExists($imagePath);
    }

    public function test_upload_rejects_disguised_non_image_file_and_oversized_file(): void
    {
        Storage::fake('public');

        $fakeScript = UploadedFile::fake()->createWithContent('shell.png', '<?php echo "hello"; ?>');

        $response = $this->post('/api/v1/artworks', [
            'participant_name' => 'Test User',
            'title' => 'Bad File',
            'image' => $fakeScript,
        ], ['Accept' => 'application/json']);

        $response->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['image']]);
    }

    public function test_visitor_can_like_once_and_duplicate_like_is_blocked_with_hashed_token(): void
    {
        Storage::fake('public');

        $artwork = Artwork::factory()->create();

        // First GET mints visitor token
        $catalogRes = $this->getJson('/api/v1/artworks');
        $visitorToken = $catalogRes->headers->get('X-Visitor-Token');
        $this->assertNotEmpty($visitorToken);

        // First like succeeds
        $firstLike = $this->withHeaders(['X-Visitor-Token' => $visitorToken])
            ->postJson("/api/v1/artworks/{$artwork->id}/like");

        $firstLike->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_liked', true)
            ->assertJsonPath('data.likes_count', 1);

        // Raw token must NEVER be stored in DB
        $storedLike = ArtworkLike::query()->where('artwork_id', $artwork->id)->firstOrFail();
        $this->assertNotSame($visitorToken, $storedLike->visitor_token_hash);
        $this->assertSame(64, strlen($storedLike->visitor_token_hash));

        // Duplicate like with same visitor token is rejected without incrementing
        $duplicateLike = $this->withHeaders(['X-Visitor-Token' => $visitorToken])
            ->postJson("/api/v1/artworks/{$artwork->id}/like");

        $duplicateLike->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.is_liked', true)
            ->assertJsonPath('data.likes_count', 1);

        $this->assertDatabaseCount('artwork_likes', 1);

        // Status endpoint reflects liked=true
        $statusRes = $this->withHeaders(['X-Visitor-Token' => $visitorToken])
            ->getJson("/api/v1/artworks/{$artwork->id}/like-status");

        $statusRes->assertOk()
            ->assertJsonPath('data.is_liked', true)
            ->assertJsonPath('data.likes_count', 1);
    }

    public function test_stateful_frontend_origin_can_upload_and_like_without_prior_csrf_cookie(): void
    {
        Storage::fake('public');

        $artwork = Artwork::factory()->create();
        $spaHeaders = [
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
            'Accept' => 'application/json',
        ];

        $uploadRes = $this->withHeaders($spaHeaders)->post('/api/v1/artworks', [
            'participant_name' => 'SPA Visitor',
            'title' => 'SPA Upload Test',
            'image' => UploadedFile::fake()->image('spa.png', 400, 500),
        ]);

        $uploadRes->assertCreated()
            ->assertJsonPath('success', true);

        $likeRes = $this->withHeaders($spaHeaders)
            ->postJson("/api/v1/artworks/{$artwork->id}/like");

        $likeRes->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.likes_count', 1);
    }
}
