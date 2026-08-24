<?php

namespace App\Providers;

use App\Support\ExtensionMimeTypeGuesser;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mime\MimeTypes;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Shared hosts often disable php_fileinfo — register extension/magic-byte guesser
        // so Laravel "image" validation and UploadedFile::getMimeType() still work.
        try {
            MimeTypes::getDefault()->registerGuesser(new ExtensionMimeTypeGuesser);
        } catch (\Throwable) {
            // ignore
        }

        // Reduce SQLite "database is locked" during long imports + concurrent HTTP.
        try {
            if (config('database.default') === 'sqlite') {
                \Illuminate\Support\Facades\DB::statement('PRAGMA busy_timeout = 60000');
                \Illuminate\Support\Facades\DB::statement('PRAGMA journal_mode = WAL');
                \Illuminate\Support\Facades\DB::statement('PRAGMA synchronous = NORMAL');
            }
        } catch (\Throwable) {
            // ignore if DB not ready / locked briefly at boot
        }
    }
}
