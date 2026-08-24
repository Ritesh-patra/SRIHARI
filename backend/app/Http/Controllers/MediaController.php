<?php

namespace App\Http\Controllers;

use App\Support\SurveyPhotoStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serve public-disk photos through Laravel so CORS applies under php artisan serve
 * (built-in PHP server otherwise serves /storage symlink files without middleware).
 */
class MediaController extends Controller
{
    public function show(Request $request, string $path): BinaryFileResponse
    {
        $path = str_replace('\\', '/', $path);
        $path = ltrim($path, '/');
        abort_if($path === '' || str_contains($path, '..'), 404);

        $disk = Storage::disk('public');
        abort_unless($disk->exists($path), 404);

        $full = $disk->path($path);
        // Never call mime_content_type() — requires php_fileinfo on shared hosts.
        $mime = SurveyPhotoStorage::guessMimeFromPath($full);

        return response()->file($full, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}

