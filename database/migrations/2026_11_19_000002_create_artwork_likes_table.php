<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('artwork_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artwork_id')
                ->constrained('artworks')
                ->cascadeOnDelete();
            $table->char('visitor_token_hash', 64);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['artwork_id', 'visitor_token_hash'], 'artwork_likes_artwork_visitor_unique');
            $table->index('artwork_id', 'artwork_likes_artwork_id_index');
            $table->index('visitor_token_hash', 'artwork_likes_visitor_hash_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('artwork_likes');
    }
};
