<?php

namespace App\Services;

use App\Models\Artwork;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImageStorageService
{
    /**
     * Allowed verified MIME types mapped to safe file extensions.
     *
     * @var array<string, string>
     */
    private const ALLOWED_MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    private const MAX_IMAGE_DIMENSION = 10000;

    /**
     * Validate actual file MIME type and image binary integrity, then store safely.
     *
     * @throws ValidationException
     */
    public function storeArtworkImage(UploadedFile $file): string
    {
        $realPath = $file->getRealPath();
        if (! $realPath || ! is_file($realPath)) {
            throw ValidationException::withMessages([
                'image' => ['File gambar tidak valid atau gagal dibaca.'],
            ]);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->file($realPath) ?: $file->getMimeType();

        if (! isset(self::ALLOWED_MIME_EXTENSIONS[$detectedMime])) {
            throw ValidationException::withMessages([
                'image' => ['Format gambar tidak didukung. Gunakan JPG, JPEG, PNG, atau WebP.'],
            ]);
        }

        $imageInfo = @getimagesize($realPath);
        if ($imageInfo === false || empty($imageInfo[0]) || empty($imageInfo[1])) {
            throw ValidationException::withMessages([
                'image' => ['File yang diunggah bukan file gambar yang valid.'],
            ]);
        }

        if ($imageInfo[0] > self::MAX_IMAGE_DIMENSION || $imageInfo[1] > self::MAX_IMAGE_DIMENSION) {
            throw ValidationException::withMessages([
                'image' => ['Dimensi gambar terlalu besar (maksimal 10000x10000 piksel).'],
            ]);
        }

        $extension = self::ALLOWED_MIME_EXTENSIONS[$detectedMime];
        $filename = Str::uuid()->toString().'.'.$extension;
        $storedPath = Storage::disk('public')->putFileAs('artworks', $file, $filename);

        if ($storedPath === false) {
            throw new \RuntimeException('Failed to store uploaded artwork image.');
        }

        return $storedPath;
    }

    /**
     * Delete an image file from storage only if no other artwork references it.
     */
    public function deleteIfUnused(?string $imagePath, ?int $excludeArtworkId = null): void
    {
        if (! $imagePath) {
            return;
        }

        $query = Artwork::query()->where('image_path', $imagePath);
        if ($excludeArtworkId !== null) {
            $query->where('id', '!=', $excludeArtworkId);
        }

        if ($query->exists()) {
            return;
        }

        if (Storage::disk('public')->exists($imagePath)) {
            Storage::disk('public')->delete($imagePath);
        }
    }
}
