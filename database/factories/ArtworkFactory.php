<?php

namespace Database\Factories;

use App\Models\Artwork;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Artwork>
 */
class ArtworkFactory extends Factory
{
    protected $model = Artwork::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'participant_name' => $this->faker->name(),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'image_path' => 'artworks/'.$this->faker->uuid().'.png',
        ];
    }
}
