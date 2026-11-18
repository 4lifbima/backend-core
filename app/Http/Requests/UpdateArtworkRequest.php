<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateArtworkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'participant_name' => ['required', 'string', 'min:2', 'max:120'],
            'title' => ['required', 'string', 'min:2', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'image' => [
                'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:5120',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'participant_name.required' => 'Nama peserta wajib diisi.',
            'participant_name.min' => 'Nama peserta minimal 2 karakter.',
            'participant_name.max' => 'Nama peserta maksimal 120 karakter.',
            'title.required' => 'Judul karya wajib diisi.',
            'title.min' => 'Judul karya minimal 2 karakter.',
            'title.max' => 'Judul karya maksimal 150 karakter.',
            'description.max' => 'Deskripsi karya maksimal 2000 karakter.',
            'image.file' => 'Berkas gambar tidak valid.',
            'image.image' => 'Berkas yang diunggah harus berupa gambar.',
            'image.mimes' => 'Format gambar harus JPG, JPEG, PNG, atau WebP.',
            'image.mimetypes' => 'Tipe MIME gambar harus JPG, JPEG, PNG, atau WebP.',
            'image.max' => 'Ukuran gambar maksimal adalah 5 MB.',
        ];
    }
}
