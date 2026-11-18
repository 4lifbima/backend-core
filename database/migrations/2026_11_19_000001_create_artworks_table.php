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
        Schema::create('artworks', function (Blueprint $table) {
            $table->id();
            $table->string('participant_name', 120);
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->string('image_path', 255);
            $table->timestamps();

            $table->index(['created_at', 'id'], 'artworks_created_at_id_index');
            $table->index('participant_name', 'artworks_participant_name_index');
            $table->index('title', 'artworks_title_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('artworks');
    }
};
