<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MasterController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ConsumerSurveyApprovalController;
use App\Http\Controllers\DtrReactivationController;
use App\Http\Controllers\DtrSurveyBrowseController;
use App\Http\Controllers\PoleSurveyBrowseController;
use App\Http\Controllers\ReportAnalysisController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return view('marketing.home');
})->name('home');

Route::get('/mobile-only', function () {
    return view('auth.mobile-only');
})->name('mobile.only');

Route::middleware(['auth', 'verified', 'web.admin'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.readAll');
    Route::get('/notifications/{notification}', [NotificationController::class, 'markRead'])->name('notifications.read');

    // Substation survey browse + approval (Substation → Feeder → DTR → Pole order)
    Route::get('/substation-surveys', [\App\Http\Controllers\SubstationSurveyController::class, 'index'])->name('substation-surveys.index');
    Route::get('/substation-surveys/export', [\App\Http\Controllers\SubstationSurveyController::class, 'export'])->name('substation-surveys.export');
    Route::post('/substation-surveys/bulk-delete', [\App\Http\Controllers\SubstationSurveyController::class, 'bulkDelete'])->name('substation-surveys.bulk-delete');
    Route::get('/substation-surveys/{substationSurvey}', [\App\Http\Controllers\SubstationSurveyController::class, 'show'])->name('substation-surveys.show');
    Route::post('/substation-surveys/{substationSurvey}/approve', [\App\Http\Controllers\SubstationSurveyController::class, 'approve'])->name('substation-surveys.approve');
    Route::post('/substation-surveys/{substationSurvey}/reject', [\App\Http\Controllers\SubstationSurveyController::class, 'reject'])->name('substation-surveys.reject');
    Route::post('/substation-surveys/{substationSurvey}/unlock', [\App\Http\Controllers\SubstationSurveyController::class, 'unlock'])->name('substation-surveys.unlock');

    // Feeder / DTR / Pole survey browse (filter + download + bulk delete; no bulk approve/reject)
    Route::get('/feeder-surveys', [\App\Http\Controllers\FeederSurveyController::class, 'index'])->name('feeder-surveys.index');
    Route::get('/feeder-surveys/export', [\App\Http\Controllers\FeederSurveyController::class, 'export'])->name('feeder-surveys.export');
    Route::post('/feeder-surveys/bulk-delete', [\App\Http\Controllers\FeederSurveyController::class, 'bulkDelete'])->name('feeder-surveys.bulk-delete');
    Route::get('/feeder-surveys/{feederSurvey}', [\App\Http\Controllers\FeederSurveyController::class, 'show'])->name('feeder-surveys.show');
    Route::post('/feeder-surveys/{feederSurvey}/approve', [\App\Http\Controllers\FeederSurveyController::class, 'approve'])->name('feeder-surveys.approve');
    Route::post('/feeder-surveys/{feederSurvey}/reject', [\App\Http\Controllers\FeederSurveyController::class, 'reject'])->name('feeder-surveys.reject');
    Route::post('/feeder-surveys/{feederSurvey}/unlock', [\App\Http\Controllers\FeederSurveyController::class, 'unlock'])->name('feeder-surveys.unlock');

    Route::get('/dtr-surveys', [DtrSurveyBrowseController::class, 'index'])->name('dtr-surveys.index');
    Route::post('/dtr-surveys/bulk-delete', [DtrSurveyBrowseController::class, 'bulkDelete'])->name('dtr-surveys.bulk-delete');
    Route::post('/dtr-surveys/{survey}/reopen-consumer', [DtrReactivationController::class, 'reopenSurvey'])->name('dtr-surveys.reopen-consumer');
    Route::get('/dtr-mapping-corrections', [\App\Http\Controllers\DtrMappingCorrectionController::class, 'index'])->name('dtr-mapping-corrections.index');
    Route::post('/dtr-mapping-corrections/{survey}/approve', [\App\Http\Controllers\DtrMappingCorrectionController::class, 'approve'])->name('dtr-mapping-corrections.approve');
    Route::post('/dtr-mapping-corrections/{survey}/reject', [\App\Http\Controllers\DtrMappingCorrectionController::class, 'reject'])->name('dtr-mapping-corrections.reject');
    Route::get('/dtr-reactivation', [DtrReactivationController::class, 'index'])->name('dtr-reactivation.index');
    Route::post('/dtr-reactivation/{reactivation}/approve', [DtrReactivationController::class, 'approve'])->name('dtr-reactivation.approve');
    Route::post('/dtr-reactivation/{reactivation}/reject', [DtrReactivationController::class, 'reject'])->name('dtr-reactivation.reject');
    Route::get('/pole-surveys', [PoleSurveyBrowseController::class, 'index'])->name('pole-surveys.index');
    Route::post('/pole-surveys/bulk-delete', [PoleSurveyBrowseController::class, 'bulkDelete'])->name('pole-surveys.bulk-delete');

    // Report Analysis (multi-source upload + compare setup — Phase 1)
    Route::get('/report-analysis', [ReportAnalysisController::class, 'index'])->name('report-analysis.index');
    Route::post('/report-analysis/upload', [ReportAnalysisController::class, 'upload'])->name('report-analysis.upload');
    Route::post('/report-analysis/selection', [ReportAnalysisController::class, 'saveSelection'])->name('report-analysis.selection');

    // Admin oversight reports (web)
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/surveyors', [ReportController::class, 'surveyors'])->name('reports.surveyors');
    Route::get('/reports/surveyors/{user}', [ReportController::class, 'surveyorShow'])->name('reports.surveyors.show');
    Route::get('/reports/export/surveyors', [ReportController::class, 'exportSurveyors'])->name('reports.export.surveyors');
    Route::get('/reports/export/feeder-surveys', [ReportController::class, 'exportFeederSurveys'])->name('reports.export.feeder-surveys');
    Route::get('/reports/export/surveys', [ReportController::class, 'exportSurveys'])->name('reports.export.surveys');
    Route::get('/reports/export/consumers', [ReportController::class, 'exportConsumers'])->name('reports.export.consumers');
    Route::get('/reports/print/{survey}', [ReportController::class, 'printable'])->name('reports.print');
    Route::post('/reports/surveys/delete', [ReportController::class, 'destroySurvey'])->name('reports.surveys.delete');

    Route::get('/consumer-approval', [ConsumerSurveyApprovalController::class, 'index'])->name('consumer-approval.index');
    Route::post('/consumer-approval/bulk', [ConsumerSurveyApprovalController::class, 'bulkAction'])->name('consumer-approval.bulk');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [AdminController::class, 'users'])->name('users');
        Route::post('/users', [AdminController::class, 'storeUser'])->name('users.store');
        Route::get('/users/{user}/edit', [AdminController::class, 'editUser'])->name('users.edit');
        Route::put('/users/{user}', [AdminController::class, 'updateUser'])->name('users.update');
        Route::patch('/users/{user}/toggle', [AdminController::class, 'toggleUser'])->name('users.toggle');
        Route::delete('/users/{user}', [AdminController::class, 'destroyUser'])->name('users.destroy');
        Route::patch('/users/{user}/assign', [AdminController::class, 'updateAssignment'])->name('users.assign');
        Route::patch('/users/{user}/scopes', [AdminController::class, 'updateScopes'])->name('users.scopes');
        Route::get('/hierarchy', [AdminController::class, 'hierarchy'])->name('hierarchy');
        Route::get('/poles', [AdminController::class, 'poles'])->name('poles');
        Route::delete('/poles/{pole}', [AdminController::class, 'destroyPole'])->name('poles.destroy');
        Route::get('/activity', [AdminController::class, 'activity'])->name('activity');
        Route::get('/settings', [AdminController::class, 'settings'])->name('settings');

        Route::get('/masters', [MasterController::class, 'index'])->name('masters');
        Route::get('/masters/dtrs', [MasterController::class, 'dtrs'])->name('masters.dtrs.index');
        Route::get('/masters/consumers', [MasterController::class, 'consumers'])->name('masters.consumers.index');
        Route::post('/masters/regions', [MasterController::class, 'storeRegion'])->name('masters.regions');
        Route::post('/masters/circles', [MasterController::class, 'storeCircle'])->name('masters.circles');
        Route::post('/masters/divisions', [MasterController::class, 'storeDivision'])->name('masters.divisions');
        Route::post('/masters/zones', [MasterController::class, 'storeZone'])->name('masters.zones');
        Route::post('/masters/substations', [MasterController::class, 'storeSubstation'])->name('masters.substations');
        Route::post('/masters/feeders', [MasterController::class, 'storeFeeder'])->name('masters.feeders');
        Route::post('/masters/dtrs', [MasterController::class, 'storeDtr'])->name('masters.dtrs');
        Route::post('/masters/consumers', [MasterController::class, 'storeConsumer'])->name('masters.consumers');
        Route::get('/masters/import', [MasterController::class, 'importForm'])->name('masters.import');
        Route::post('/masters/import', [MasterController::class, 'import'])->name('masters.import.store');
        Route::get('/masters/export/{type}', [MasterController::class, 'export'])->name('masters.export');
    });
});

// Chunked uploads (300 MB) + Reading Upload — Feeder / DTR / Consumer consumption
require __DIR__.'/uploads.php';

require __DIR__.'/auth.php';
