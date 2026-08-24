<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Chunked upload
    |--------------------------------------------------------------------------
    |
    | Large files (300 MB+) are sliced in the browser and posted one chunk per
    | request. Only the CHUNK has to fit inside PHP's upload_max_filesize /
    | post_max_size, which is why this works on shared cPanel hosting where
    | those limits are a few MB.
    |
    */

    'chunk_size' => (int) env('UPLOAD_CHUNK_SIZE', 1048576), // 1 MB

    // Hard ceiling for a single upload, in megabytes.
    'max_total_mb' => (int) env('UPLOAD_MAX_TOTAL_MB', 300),

    'allowed_extensions' => ['csv', 'txt', 'xlsx', 'xls'],

    // Disk + folder that temporary chunk parts live in while uploading.
    'disk' => 'local',
    'chunk_dir' => 'chunks',

    // Abandoned uploads older than this are removed by seas:cleanup-chunks.
    'stale_hours' => (int) env('UPLOAD_STALE_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Background parsing
    |--------------------------------------------------------------------------
    |
    | Production runs QUEUE_CONNECTION=sync with no persistent worker, so
    | parsing is driven by `seas:process-uploads` from the scheduler cron.
    | Small files are parsed inline on completion so they still feel instant.
    |
    */

    'inline_parse_max_bytes' => (int) env('UPLOAD_INLINE_PARSE_MAX_BYTES', 8388608), // 8 MB

    'process_limit' => (int) env('UPLOAD_PROCESS_LIMIT', 2),

    'process_memory_limit' => env('UPLOAD_PROCESS_MEMORY_LIMIT', '1024M'),

    // Rows per bulk insert() batch when importing reading files.
    'import_batch_rows' => (int) env('UPLOAD_IMPORT_BATCH_ROWS', 500),

    // A row stuck in "processing" longer than this is requeued as pending.
    'processing_timeout_minutes' => (int) env('UPLOAD_PROCESSING_TIMEOUT_MINUTES', 30),

];
