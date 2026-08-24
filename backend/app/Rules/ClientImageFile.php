<?php

namespace App\Rules;

use App\Support\SurveyPhotoStorage;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Image validation that does not require php_fileinfo / Symfony mime guessers.
 */
class ClientImageFile implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail("The {$attribute} must be an image file.");

            return;
        }

        if (! $value->isValid()) {
            $fail("The {$attribute} upload is invalid.");

            return;
        }

        $ext = strtolower((string) $value->getClientOriginalExtension());
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (in_array($ext, $allowed, true)) {
            return;
        }

        $path = $value->getRealPath();
        if (is_string($path) && SurveyPhotoStorage::detectExtensionFromPath($path) !== null) {
            return;
        }

        $fail("The {$attribute} must be an image (jpg, png, webp, gif).");
    }
}
