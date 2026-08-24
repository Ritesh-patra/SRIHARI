<?php

use App\Http\Controllers\ChunkedUploadController;
use App\Http\Controllers\ReadingUploadController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Chunked uploads + Reading Upload
|--------------------------------------------------------------------------
|
| Loaded from routes/web.php via `require __DIR__.'/uploads.php';`.
| Same gate as Report Analysis: auth + verified + web.admin.
|
*/

Route::middleware(['auth', 'verified', 'web.admin'])->group(function () {

    // Resumable chunked upload transport (shared by every large-file feature).
    Route::post('/uploads/chunk/init', [ChunkedUploadController::class, 'init'])->name('uploads.chunk.init');
    Route::post('/uploads/chunk/{uuid}/part', [ChunkedUploadController::class, 'part'])->name('uploads.chunk.part');
    Route::post('/uploads/chunk/{uuid}/complete', [ChunkedUploadController::class, 'complete'])->name('uploads.chunk.complete');
    Route::get('/uploads/chunk/{uuid}/status', [ChunkedUploadController::class, 'status'])->name('uploads.chunk.status');
    Route::delete('/uploads/chunk/{uuid}', [ChunkedUploadController::class, 'abort'])->name('uploads.chunk.abort');

    // Reading Upload — Feeder / DTR / Consumer consumption files
    Route::get('/reading-uploads', [ReadingUploadController::class, 'index'])->name('reading-uploads.index');
    Route::get('/reading-uploads/status', [ReadingUploadController::class, 'status'])->name('reading-uploads.status');
    Route::get('/reading-uploads/export', [ReadingUploadController::class, 'export'])->name('reading-uploads.export');
    Route::get('/reading-uploads/{readingUpload}', [ReadingUploadController::class, 'show'])->name('reading-uploads.show');
    Route::post('/reading-uploads/{readingUpload}/reprocess', [ReadingUploadController::class, 'reprocess'])->name('reading-uploads.reprocess');
    Route::delete('/reading-uploads/{readingUpload}', [ReadingUploadController::class, 'destroy'])->name('reading-uploads.destroy');
});
