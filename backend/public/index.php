<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Shared hosts without php_fileinfo: stub \finfo BEFORE Flysystem/Symfony load.
// (File upload → Storage::disk → LocalFilesystemAdapter → new FinfoMimeTypeDetector)
$finfoPolyfill = __DIR__.'/../app/Support/finfo_polyfill.php';
if (is_file($finfoPolyfill)) {
    require_once $finfoPolyfill;
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
