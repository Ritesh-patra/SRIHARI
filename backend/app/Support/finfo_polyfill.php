<?php

/**
 * Shared hosts often omit php_fileinfo. League Flysystem still does
 * `new finfo(...)` inside FinfoMimeTypeDetector::__construct when any
 * Storage::disk('local'|'public') is resolved — that fatals with
 * Class "finfo" not found during survey photo store.
 *
 * Load this BEFORE vendor/autoload (public/index.php / artisan).
 * Only defines stubs when the real extension/class is absent.
 */

if (! defined('FILEINFO_NONE')) {
    define('FILEINFO_NONE', 0);
}
if (! defined('FILEINFO_MIME_TYPE')) {
    define('FILEINFO_MIME_TYPE', 16);
}
if (! defined('FILEINFO_MIME')) {
    define('FILEINFO_MIME', 1040);
}
if (! defined('FILEINFO_MIME_ENCODING')) {
    define('FILEINFO_MIME_ENCODING', 1024);
}

if (! class_exists('finfo', false)) {
    /**
     * Minimal stand-in for PHP's finfo. Methods return false so callers
     * fall back to extension-based detection (Flysystem / Symfony).
     */
    class finfo
    {
        public function __construct(int $flags = 0, ?string $magic_database = null)
        {
        }

        public function file(string $filename, int $flags = FILEINFO_NONE, $context = null): string|false
        {
            return false;
        }

        public function buffer(string $string, int $flags = FILEINFO_NONE, $context = null): string|false
        {
            return false;
        }

        public function set_flags(int $flags): bool
        {
            return true;
        }
    }
}

if (! function_exists('finfo_open')) {
    function finfo_open(int $flags = 0, ?string $magic_database = null): \finfo|false
    {
        try {
            return new \finfo($flags, $magic_database);
        } catch (\Throwable) {
            return false;
        }
    }
}

if (! function_exists('finfo_close')) {
    function finfo_close($finfo): bool
    {
        return true;
    }
}

if (! function_exists('finfo_file')) {
    function finfo_file($finfo, string $filename, int $flags = FILEINFO_NONE, $context = null): string|false
    {
        return ($finfo instanceof \finfo) ? $finfo->file($filename, $flags, $context) : false;
    }
}

if (! function_exists('finfo_buffer')) {
    function finfo_buffer($finfo, string $string, int $flags = FILEINFO_NONE, $context = null): string|false
    {
        return ($finfo instanceof \finfo) ? $finfo->buffer($string, $flags, $context) : false;
    }
}

if (! function_exists('finfo_set_flags')) {
    function finfo_set_flags($finfo, int $flags): bool
    {
        return ($finfo instanceof \finfo) ? $finfo->set_flags($flags) : false;
    }
}

if (! function_exists('mime_content_type')) {
    function mime_content_type(string $filename): string|false
    {
        return false;
    }
}
