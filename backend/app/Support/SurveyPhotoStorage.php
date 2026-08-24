<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Store field-survey photos.
 * WebP preferred; JPEG if WebP missing; original file if PHP GD is disabled.
 */
class SurveyPhotoStorage
{
    /**
     * Absolute public URL for a disk-relative path (`surveys/...webp` or `.jpg`).
     * Prefers the current request host:port so local :8000 works even if APP_URL is wrong.
     */
    public static function url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $relative = ltrim($path, '/');
        if (str_starts_with($relative, 'storage/')) {
            $relative = substr($relative, strlen('storage/'));
        }

        $origin = null;
        if (! app()->runningInConsole()) {
            try {
                $origin = request()?->getSchemeAndHttpHost();
            } catch (\Throwable) {
                $origin = null;
            }
        }

        if (! $origin) {
            $origin = rtrim((string) config('app.url'), '/');
        }

        // Serve via /api/media so CORS middleware applies under `php artisan serve`
        // (direct /storage/* is served by the built-in server without Laravel headers).
        return $origin.'/api/media/'.$relative;
    }

    public static function store(
        UploadedFile $file,
        string $directory,
        string $disk = 'public',
        int $quality = 82,
        int $maxWidth = 1600,
    ): string {
        $realPath = $file->getRealPath();
        if ($realPath === false || $realPath === '') {
            throw new RuntimeException('Unable to locate uploaded photo on disk.');
        }

        $binary = @file_get_contents($realPath);
        if ($binary === false || $binary === '') {
            throw new RuntimeException('Unable to read uploaded photo.');
        }

        $directory = trim($directory, '/');
        if ($directory === '') {
            throw new RuntimeException('Survey photo directory is required.');
        }

        // Shared hosts sometimes ship without GD — store original bytes so surveys still save.
        if (! extension_loaded('gd') || ! function_exists('imagecreatefromstring')) {
            return self::storeOriginal($binary, $file, $directory, $disk);
        }

        $source = @imagecreatefromstring($binary);
        if ($source === false) {
            throw new RuntimeException('Unsupported or corrupt image upload.');
        }

        if (function_exists('imagepalettetotruecolor')) {
            @imagepalettetotruecolor($source);
        }
        if (function_exists('imagealphablending')) {
            @imagealphablending($source, true);
        }
        if (function_exists('imagesavealpha')) {
            @imagesavealpha($source, true);
        }

        $width = imagesx($source);
        $height = imagesy($source);
        if ($width < 1 || $height < 1) {
            imagedestroy($source);
            throw new RuntimeException('Invalid image dimensions.');
        }

        $image = $source;
        if ($width > $maxWidth) {
            $newWidth = $maxWidth;
            $newHeight = max(1, (int) round($height * ($maxWidth / $width)));
            $resized = imagecreatetruecolor($newWidth, $newHeight);
            if ($resized === false) {
                imagedestroy($source);
                throw new RuntimeException('Unable to resize photo.');
            }
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
            imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);
            imagealphablending($resized, true);
            imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($source);
            $image = $resized;
        }

        $quality = max(0, min(100, $quality));
        [$encoded, $extension] = self::encodePhoto($image, $quality);
        imagedestroy($image);

        return self::putEncoded($encoded, $extension, $directory, $disk);
    }

    /**
     * Store upload as-is when PHP GD is unavailable (common on some cPanel plans).
     */
    private static function storeOriginal(string $binary, UploadedFile $file, string $directory, string $disk): string
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
        if ($ext === '' || ! in_array($ext, ['jpg', 'png', 'webp', 'gif'], true)) {
            $ext = self::detectExtensionFromBinary($binary) ?? 'jpg';
        }

        return self::putEncoded($binary, $ext, $directory, $disk);
    }

    /** Detect image type from file path (magic bytes) — no php_fileinfo needed. */
    public static function detectExtensionFromPath(string $path): ?string
    {
        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }
        $fh = @fopen($path, 'rb');
        if ($fh === false) {
            return null;
        }
        $header = (string) fread($fh, 16);
        fclose($fh);

        return self::detectExtensionFromBinary($header);
    }

    /**
     * MIME for MediaController — magic bytes first, then file extension.
     * Never uses mime_content_type() (php_fileinfo often missing on shared hosts).
     */
    public static function guessMimeFromPath(string $path): string
    {
        $ext = self::detectExtensionFromPath($path);
        if ($ext === null) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if ($ext === 'jpeg') {
                $ext = 'jpg';
            }
        }

        return match ($ext) {
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'application/octet-stream',
        };
    }

    /** Detect image type from binary header — no php_fileinfo / GD needed. */
    public static function detectExtensionFromBinary(string $binary): ?string
    {
        if ($binary === '') {
            return null;
        }
        // JPEG
        if (str_starts_with($binary, "\xFF\xD8\xFF")) {
            return 'jpg';
        }
        // PNG
        if (str_starts_with($binary, "\x89PNG\r\n\x1A\n")) {
            return 'png';
        }
        // GIF
        if (str_starts_with($binary, 'GIF87a') || str_starts_with($binary, 'GIF89a')) {
            return 'gif';
        }
        // WEBP (RIFF....WEBP)
        if (strlen($binary) >= 12 && str_starts_with($binary, 'RIFF') && substr($binary, 8, 4) === 'WEBP') {
            return 'webp';
        }

        return null;
    }

    private static function putEncoded(string $encoded, string $extension, string $directory, string $disk): string
    {
        $storage = Storage::disk($disk);
        if (! $storage->exists($directory) && ! $storage->makeDirectory($directory)) {
            throw new RuntimeException(
                "Unable to create photo directory [{$directory}] on disk [{$disk}]. Check storage/app/public permissions (775)."
            );
        }

        $path = $directory.'/'.Str::uuid()->toString().'.'.$extension;

        if (! $storage->put($path, $encoded)) {
            throw new RuntimeException(
                "Unable to store photo at [{$path}] on disk [{$disk}]. Check storage/app/public is writable (775)."
            );
        }

        return $path;
    }

    /**
     * Prefer WebP; fall back to JPEG when imagewebp is missing or fails.
     *
     * @return array{0: string, 1: string} [binary, extension]
     */
    private static function encodePhoto($image, int $quality): array
    {
        if (function_exists('imagewebp')) {
            ob_start();
            $ok = @imagewebp($image, null, $quality);
            $webp = ob_get_clean();
            if ($ok && is_string($webp) && $webp !== '') {
                return [$webp, 'webp'];
            }
        }

        if (! function_exists('imagejpeg')) {
            throw new RuntimeException(
                'Photo conversion failed: PHP GD has neither working imagewebp nor imagejpeg. Enable GD JPEG/WebP support.'
            );
        }

        // Flatten alpha onto white for JPEG (no alpha channel).
        $width = imagesx($image);
        $height = imagesy($image);
        $flat = imagecreatetruecolor($width, $height);
        if ($flat === false) {
            throw new RuntimeException('Unable to prepare JPEG canvas for photo conversion.');
        }
        $white = imagecolorallocate($flat, 255, 255, 255);
        imagefilledrectangle($flat, 0, 0, $width, $height, $white);
        imagecopy($flat, $image, 0, 0, 0, 0, $width, $height);

        ob_start();
        $ok = @imagejpeg($flat, null, $quality);
        $jpeg = ob_get_clean();
        imagedestroy($flat);

        if (! $ok || ! is_string($jpeg) || $jpeg === '') {
            throw new RuntimeException(
                'JPEG conversion failed after WebP was unavailable. Check PHP GD JPEG support and free memory.'
            );
        }

        return [$jpeg, 'jpg'];
    }
}
