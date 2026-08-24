# INTEGRATION — 300 MB chunked uploads + Reading Upload

Two files could not be touched because another agent is editing them. Apply these
two snippets by hand and the feature is fully wired.

**Until snippet 1 is applied, `/report-analysis` will throw**
`Route [uploads.chunk.init] not defined` — the Blade view builds the uploader URLs
from those route names. Apply snippet 1 first, then run `php artisan optimize:clear`.

---

## 1. `backend/routes/web.php` — load the new route file

The new routes live in `backend/routes/uploads.php` and declare their own
`['auth', 'verified', 'web.admin']` group, so the `require` goes at file scope,
**outside** the existing middleware group — right next to the existing
`require __DIR__.'/auth.php';` at the bottom of the file.

**Find the last lines of the file:**

```php
        Route::get('/masters/import', [MasterController::class, 'importForm'])->name('masters.import');
        Route::post('/masters/import', [MasterController::class, 'import'])->name('masters.import.store');
        Route::get('/masters/export/{type}', [MasterController::class, 'export'])->name('masters.export');
    });
});

require __DIR__.'/auth.php';
```

**Replace with:**

```php
        Route::get('/masters/import', [MasterController::class, 'importForm'])->name('masters.import');
        Route::post('/masters/import', [MasterController::class, 'import'])->name('masters.import.store');
        Route::get('/masters/export/{type}', [MasterController::class, 'export'])->name('masters.export');
    });
});

// Chunked uploads (300 MB+) + Reading Upload — Feeder / DTR / Consumer consumption
require __DIR__.'/uploads.php';

require __DIR__.'/auth.php';
```

No `use` statement is needed in `web.php`; `routes/uploads.php` imports its own
controllers.

---

## 2. `backend/resources/views/layouts/app.blade.php` — sidebar entry

Add **Reading Upload** to the `Overview` section, directly after the existing
Report Analysis item (around line 499).

**Find:**

```blade
            <p class="al-section">Overview</p>
            @foreach([
                ['dashboard', 'dashboard', 'Dashboard', 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9.5z'],
                ['reports.surveyors', 'reports.surveyors*', 'Audit Report', 'M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2m-6 9l2 2 4-4'],
                ['reports.index', 'reports.index', 'Analytics', 'M4 19h16M7 16V9M12 16V5M17 16v-7'],
                ['report-analysis.index', 'report-analysis.*', 'Report Analysis', 'M9 17v-6M13 17V7M17 17v-3M5 21h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2z'],
            ] as [$route, $match, $label, $icon])
```

**Replace with:**

```blade
            <p class="al-section">Overview</p>
            @foreach([
                ['dashboard', 'dashboard', 'Dashboard', 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9.5z'],
                ['reports.surveyors', 'reports.surveyors*', 'Audit Report', 'M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2m-6 9l2 2 4-4'],
                ['reports.index', 'reports.index', 'Analytics', 'M4 19h16M7 16V9M12 16V5M17 16v-7'],
                ['report-analysis.index', 'report-analysis.*', 'Report Analysis', 'M9 17v-6M13 17V7M17 17v-3M5 21h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2z'],
                ['reading-uploads.index', 'reading-uploads.*', 'Reading Upload', 'M12 16V4m0 0L8 8m4-4 4 4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2'],
            ] as [$route, $match, $label, $icon])
```

The only change is the one added `['reading-uploads.index', ...]` line. The
`@foreach` body underneath stays exactly as it is.

---

## After applying both

```bash
cd /home/mrhari/mrhari.co.in      # or backend/ locally
php artisan optimize:clear
php artisan route:list --name=uploads
php artisan route:list --name=reading-uploads
```

You should see:

```
POST     uploads/chunk/init ................ uploads.chunk.init
POST     uploads/chunk/{uuid}/part ......... uploads.chunk.part
POST     uploads/chunk/{uuid}/complete ..... uploads.chunk.complete
GET      uploads/chunk/{uuid}/status ....... uploads.chunk.status
DELETE   uploads/chunk/{uuid} .............. uploads.chunk.abort
GET      reading-uploads ................... reading-uploads.index
GET      reading-uploads/status ............ reading-uploads.status
GET      reading-uploads/export ............ reading-uploads.export
GET      reading-uploads/{readingUpload} ... reading-uploads.show
POST     reading-uploads/{id}/reprocess .... reading-uploads.reprocess
DELETE   reading-uploads/{readingUpload} ... reading-uploads.destroy
```

Nothing else in `web.php`, `api.php`, `layouts/app.blade.php` or
`Api/FieldApiController.php` was changed by this work.
