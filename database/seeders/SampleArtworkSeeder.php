<?php

namespace Database\Seeders;

use App\Models\Artwork;
use App\Models\ArtworkLike;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class SampleArtworkSeeder extends Seeder
{
    /**
     * Seed crisp graphic-design sample artworks so initial catalog & dashboard have rich preview content.
     */
    public function run(): void
    {
        if (Artwork::query()->exists()) {
            return;
        }

        Storage::disk('public')->makeDirectory('artworks');

        $samples = [
            [
                'participant_name' => 'Nadia Kusuma',
                'title' => 'Sistem Grid Editorial — Edisi Pameran 01',
                'description' => 'Eksplorasi tata letak majalah berbasis grid Swiss 12 kolom dengan kontras tipografi sans-serif dan aksen geometris primer.',
                'filename' => 'sample-editorial-grid.png',
                'palette' => [[248, 249, 252], [43, 57, 255], [23, 23, 23], [250, 246, 40]],
                'variant' => 'grid',
                'likes' => 14,
                'hours_ago' => 36,
            ],
            [
                'participant_name' => 'Raka Pratama',
                'title' => 'Identitas Visual Festival Arsitektur Muda',
                'description' => 'Perancangan poster pameran dengan hierarki visual tegas, modul persegi proporsional, dan keseimbangan ruang negatif.',
                'filename' => 'sample-arch-poster.png',
                'palette' => [[255, 255, 255], [23, 23, 23], [43, 57, 255], [229, 231, 235]],
                'variant' => 'blocks',
                'likes' => 11,
                'hours_ago' => 28,
            ],
            [
                'participant_name' => 'Salsabila Putri',
                'title' => 'Eksperimen Tipografi Kinetik & Proporsi',
                'description' => 'Studi ritme visual menggunakan pengulangan garis diagonal, lingkaran terpotong, dan komposisi warna terbatas.',
                'filename' => 'sample-kinetic-type.png',
                'palette' => [[23, 23, 23], [250, 246, 40], [43, 57, 255], [255, 255, 255]],
                'variant' => 'circles',
                'likes' => 9,
                'hours_ago' => 20,
            ],
            [
                'participant_name' => 'Dimas Wijaya',
                'title' => 'Desain Kemasan Kopi Nusantara Minimalis',
                'description' => 'Konsep label kemasan modern yang mengutamakan keterbacaan informasi asal biji kopi dan identitas merek yang bersih.',
                'filename' => 'sample-coffee-pack.png',
                'palette' => [[244, 244, 245], [23, 23, 23], [43, 57, 255], [250, 246, 40]],
                'variant' => 'bauhaus',
                'likes' => 7,
                'hours_ago' => 12,
            ],
            [
                'participant_name' => 'Alifia Rahma',
                'title' => 'Monogram Studio Kreatif — Seri 04',
                'description' => 'Eksplorasi logo monogram modular dengan konstruksi rasio emas untuk kebutuhan branding studio desain.',
                'filename' => 'sample-monogram-04.png',
                'palette' => [[43, 57, 255], [255, 255, 255], [250, 246, 40], [23, 23, 23]],
                'variant' => 'monogram',
                'likes' => 5,
                'hours_ago' => 6,
            ],
            [
                'participant_name' => 'Fajar Nugroho',
                'title' => 'Poster Kampanye Literasi Digital Kampus',
                'description' => 'Media komunikasi visual bertema literasi digital dengan komposisi asimetris yang terstruktur dan ramah dibaca.',
                'filename' => 'sample-literacy-poster.png',
                'palette' => [[255, 255, 255], [43, 57, 255], [250, 246, 40], [23, 23, 23]],
                'variant' => 'stripes',
                'likes' => 3,
                'hours_ago' => 2,
            ],
        ];

        foreach ($samples as $index => $item) {
            $relativePath = 'artworks/'.$item['filename'];
            $this->generateGraphicPoster(
                Storage::disk('public')->path($relativePath),
                $item['palette'],
                $item['variant'],
                $index + 1,
            );

            $timestamp = now()->subHours($item['hours_ago']);
            $artwork = Artwork::query()->create([
                'participant_name' => $item['participant_name'],
                'title' => $item['title'],
                'description' => $item['description'],
                'image_path' => $relativePath,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            for ($likeIdx = 1; $likeIdx <= $item['likes']; $likeIdx++) {
                ArtworkLike::query()->create([
                    'artwork_id' => $artwork->id,
                    'visitor_token_hash' => hash_hmac('sha256', "seed-visitor-{$artwork->id}-{$likeIdx}", (string) config('app.key')),
                    'created_at' => $timestamp->copy()->addMinutes($likeIdx * 5),
                ]);
            }
        }
    }

    /**
     * Render a crisp 800x1000 (4:5) geometric graphic-design poster PNG using GD.
     *
     * @param  array<int, array{0: int, 1: int, 2: int}>  $palette
     */
    private function generateGraphicPoster(string $targetPath, array $palette, string $variant, int $number): void
    {
        $width = 800;
        $height = 1000;
        $img = imagecreatetruecolor($width, $height);
        imageantialias($img, true);

        [$bgRgb, $primaryRgb, $darkRgb, $accentRgb] = $palette;
        $bg = imagecolorallocate($img, $bgRgb[0], $bgRgb[1], $bgRgb[2]);
        $primary = imagecolorallocate($img, $primaryRgb[0], $primaryRgb[1], $primaryRgb[2]);
        $dark = imagecolorallocate($img, $darkRgb[0], $darkRgb[1], $darkRgb[2]);
        $accent = imagecolorallocate($img, $accentRgb[0], $accentRgb[1], $accentRgb[2]);
        $lineGray = imagecolorallocate($img, 212, 212, 216);

        imagefill($img, 0, 0, $bg);

        // Subtle Swiss grid lines
        for ($x = 80; $x < $width; $x += 160) {
            imageline($img, $x, 60, $x, $height - 60, $lineGray);
        }

        match ($variant) {
            'grid' => $this->drawGridPoster($img, $primary, $dark, $accent),
            'blocks' => $this->drawBlocksPoster($img, $primary, $dark, $accent),
            'circles' => $this->drawCirclesPoster($img, $primary, $dark, $accent),
            'bauhaus' => $this->drawBauhausPoster($img, $primary, $dark, $accent),
            'monogram' => $this->drawMonogramPoster($img, $primary, $dark, $accent),
            default => $this->drawStripesPoster($img, $primary, $dark, $accent),
        };

        // Crisp editorial poster footer bar
        imagefilledrectangle($img, 64, 880, 340, 888, $dark);
        imagefilledrectangle($img, 64, 908, 220, 914, $primary);
        imagefilledellipse($img, 710, 896, 44, 44, $accent);
        imagestring($img, 5, 64, 72, sprintf('KARYA #%02d / STUDIO PELATIHAN', $number), $dark);

        imagepng($img, $targetPath, 6);
        imagedestroy($img);
    }

    private function drawGridPoster($img, int $primary, int $dark, int $accent): void
    {
        imagefilledrectangle($img, 64, 150, 736, 510, $primary);
        imagefilledellipse($img, 540, 330, 260, 260, $accent);
        imagefilledrectangle($img, 64, 540, 380, 820, $dark);
        imagefilledrectangle($img, 410, 540, 736, 670, $accent);
        imagefilledrectangle($img, 410, 700, 736, 820, $primary);
    }

    private function drawBlocksPoster($img, int $primary, int $dark, int $accent): void
    {
        imagefilledrectangle($img, 64, 160, 440, 620, $primary);
        imagefilledrectangle($img, 470, 160, 736, 400, $dark);
        imagefilledrectangle($img, 470, 430, 736, 620, $accent);
        imagefilledrectangle($img, 64, 650, 736, 820, $dark);
    }

    private function drawCirclesPoster($img, int $primary, int $dark, int $accent): void
    {
        imagefilledellipse($img, 400, 390, 460, 460, $primary);
        imagefilledellipse($img, 400, 390, 260, 260, $accent);
        imagefilledellipse($img, 400, 390, 110, 110, $dark);
        imagefilledrectangle($img, 64, 680, 736, 810, $primary);
    }

    private function drawBauhausPoster($img, int $primary, int $dark, int $accent): void
    {
        imagefilledarc($img, 400, 520, 520, 520, 180, 360, $dark, IMG_ARC_PIE);
        imagefilledrectangle($img, 140, 540, 660, 780, $primary);
        imagefilledellipse($img, 540, 340, 160, 160, $accent);
    }

    private function drawMonogramPoster($img, int $primary, int $dark, int $accent): void
    {
        imagefilledrectangle($img, 110, 190, 690, 770, $primary);
        imagefilledellipse($img, 400, 480, 360, 360, $accent);
        imagefilledrectangle($img, 290, 370, 510, 590, $dark);
        imagefilledellipse($img, 400, 480, 120, 120, $primary);
    }

    private function drawStripesPoster($img, int $primary, int $dark, int $accent): void
    {
        for ($i = 0; $i < 6; $i++) {
            $y = 170 + ($i * 105);
            $color = match ($i % 3) {
                0 => $primary,
                1 => $dark,
                default => $accent,
            };
            imagefilledrectangle($img, 64 + ($i * 20), $y, 736 - ($i * 15), $y + 72, $color);
        }
    }
}
