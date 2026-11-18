<?php

namespace Tests\Feature;

use App\Models\Artwork;
use App\Models\ArtworkLike;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_users_cannot_access_admin_endpoints(): void
    {
        $artwork = Artwork::factory()->create();

        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->getJson('/api/v1/admin/dashboard')->assertUnauthorized();
        $this->getJson('/api/v1/admin/artworks')->assertUnauthorized();
        $this->putJson("/api/v1/admin/artworks/{$artwork->id}", [])->assertUnauthorized();
        $this->deleteJson("/api/v1/admin/artworks/{$artwork->id}")->assertUnauthorized();
    }

    public function test_admin_can_login_view_profile_and_logout(): void
    {
        User::factory()->create([
            'name' => 'Pemateri Uji',
            'email' => 'pemateri@example.com',
            'password' => 'Secret123!',
        ]);

        // Bad password rejected
        $this->postJson('/api/v1/auth/login', [
            'email' => 'pemateri@example.com',
            'password' => 'WrongPassword',
        ])->assertUnprocessable();

        // Valid login succeeds
        $loginRes = $this->postJson('/api/v1/auth/login', [
            'email' => 'pemateri@example.com',
            'password' => 'Secret123!',
        ]);

        $loginRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'pemateri@example.com');

        $token = $loginRes->json('data.token');
        $this->assertNotEmpty($token);

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.name', 'Pemateri Uji');

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_admin_dashboard_returns_accurate_aggregated_stats(): void
    {
        $admin = User::factory()->create();
        Sanctum::actingAs($admin);

        $art1 = Artwork::factory()->create(['participant_name' => 'Budi']);
        $art2 = Artwork::factory()->create(['participant_name' => 'budi']);
        $art3 = Artwork::factory()->create(['participant_name' => 'Sari']);

        ArtworkLike::create([
            'artwork_id' => $art1->id,
            'visitor_token_hash' => hash('sha256', 'v1'),
        ]);
        ArtworkLike::create([
            'artwork_id' => $art1->id,
            'visitor_token_hash' => hash('sha256', 'v2'),
        ]);
        ArtworkLike::create([
            'artwork_id' => $art3->id,
            'visitor_token_hash' => hash('sha256', 'v3'),
        ]);

        $res = $this->getJson('/api/v1/admin/dashboard');

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.stats.total_artworks', 3)
            ->assertJsonPath('data.stats.total_likes', 3)
            ->assertJsonPath('data.stats.total_participants', 2)
            ->assertJsonPath('data.top_artworks.0.id', $art1->id)
            ->assertJsonPath('data.top_artworks.0.likes_count', 2);
    }

    public function test_admin_can_update_and_delete_artwork_and_cleanup_storage(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create();
        Sanctum::actingAs($admin);

        $oldFile = UploadedFile::fake()->image('old.png', 500, 600);
        $oldPath = $oldFile->store('artworks', 'public');

        $artwork = Artwork::factory()->create([
            'participant_name' => 'Nama Lama',
            'title' => 'Judul Lama',
            'image_path' => $oldPath,
        ]);

        ArtworkLike::create([
            'artwork_id' => $artwork->id,
            'visitor_token_hash' => hash('sha256', 'visitor-1'),
        ]);

        $newFile = UploadedFile::fake()->image('new.jpg', 600, 750);

        $updateRes = $this->post("/api/v1/admin/artworks/{$artwork->id}", [
            '_method' => 'PUT',
            'participant_name' => 'Nama Baru',
            'title' => 'Judul Baru',
            'description' => 'Deskripsi terbaru',
            'image' => $newFile,
        ], ['Accept' => 'application/json']);

        $updateRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.participant_name', 'Nama Baru')
            ->assertJsonPath('data.title', 'Judul Baru');

        $newPath = $updateRes->json('data.image_path');
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);

        $deleteRes = $this->deleteJson("/api/v1/admin/artworks/{$artwork->id}");
        $deleteRes->assertNoContent();

        $this->assertDatabaseMissing('artworks', ['id' => $artwork->id]);
        $this->assertDatabaseMissing('artwork_likes', ['artwork_id' => $artwork->id]);
        Storage::disk('public')->assertMissing($newPath);
    }

    public function test_admin_can_search_filter_and_sort_artworks(): void
    {
        $admin = User::factory()->create();
        Sanctum::actingAs($admin);

        $popular = Artwork::factory()->create([
            'participant_name' => 'Aulia',
            'title' => 'Brand Guideline Alpha',
        ]);
        $unliked = Artwork::factory()->create([
            'participant_name' => 'Bagus',
            'title' => 'Poster Minimal Beta',
        ]);

        ArtworkLike::create([
            'artwork_id' => $popular->id,
            'visitor_token_hash' => hash('sha256', 'v-1'),
        ]);

        // Search
        $this->getJson('/api/v1/admin/artworks?search=Alpha')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $popular->id);

        // Filter no_likes
        $this->getJson('/api/v1/admin/artworks?filter=no_likes')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $unliked->id);

        // Sort most_liked
        $this->getJson('/api/v1/admin/artworks?sort=most_liked')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $popular->id);
    }

    public function test_spa_stateful_cookie_login_and_logout_flow(): void
    {
        User::factory()->create([
            'email' => 'spa@example.com',
            'password' => 'Secret123!',
        ]);

        $spaHeaders = [
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/login',
            'Accept' => 'application/json',
        ];

        $this->withHeaders($spaHeaders)
            ->get('/sanctum/csrf-cookie')
            ->assertNoContent();

        $this->withHeaders($spaHeaders)
            ->postJson('/api/v1/auth/login', [
                'email' => 'spa@example.com',
                'password' => 'Secret123!',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonMissingPath('data.token');

        $this->withHeaders($spaHeaders)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.email', 'spa@example.com');

        $this->withHeaders($spaHeaders)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->withHeaders($spaHeaders)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_lan_proxied_origin_matching_request_host_can_authenticate_via_sanctum(): void
    {
        User::factory()->create([
            'email' => 'lan@example.com',
            'password' => 'Secret123!',
        ]);

        $lanHeaders = [
            'Host' => '192.168.228.129:5173',
            'Origin' => 'http://192.168.228.129:5173',
            'Referer' => 'http://192.168.228.129:5173/login',
            'Accept' => 'application/json',
        ];

        $this->withHeaders($lanHeaders)
            ->get('/sanctum/csrf-cookie')
            ->assertNoContent();

        $this->withHeaders($lanHeaders)
            ->postJson('/api/v1/auth/login', [
                'email' => 'lan@example.com',
                'password' => 'Secret123!',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withHeaders($lanHeaders)
            ->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
