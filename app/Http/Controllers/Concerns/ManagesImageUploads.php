<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait ManagesImageUploads
{
    /**
     * Regras de validação de imagem reaproveitadas pelos endpoints de foto.
     * Limite de 4MB cobre foto de celular sem precisar de lib de redimensionamento.
     */
    protected function imageRules(string $field): array
    {
        return [$field => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096']];
    }

    /**
     * Guarda a imagem no disco "public" sob $dir e apaga a anterior (se houver).
     * Retorna o caminho relativo a salvar no banco (ex.: "avatars/users/abc.jpg").
     */
    protected function storeImage(UploadedFile $file, string $dir, ?string $previous = null): string
    {
        $path = $file->store($dir, 'public');

        if ($previous && $previous !== $path) {
            Storage::disk('public')->delete($previous);
        }

        return $path;
    }

    protected function deleteImage(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
