<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    /**
     * Serve imagens públicas (avatares, logos, fotos de barbeiro) direto do disco
     * "public", sem depender de storage:link (frágil no Windows/artisan serve).
     * São públicas por natureza (aparecem na equipe/agendamento), então sem auth.
     */
    public function show(Request $request, string $path): StreamedResponse
    {
        // Barra path traversal; o Storage já fica preso à raiz do disco, mas reforçamos.
        abort_if(str_contains($path, '..'), 404);

        $disk = Storage::disk('public');
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
