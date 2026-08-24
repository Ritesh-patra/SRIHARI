<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConsumerSurveyApprovalController;
use App\Http\Controllers\Api\FieldApiController;
use App\Http\Controllers\Api\SubstationSurveyApiController;
use App\Http\Controllers\Api\TeamAuditController;
use App\Http\Controllers\MediaController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

/** Public media (CORS via api/*) — used by Flutter Image.network */
Route::get('/media/{path}', [MediaController::class, 'show'])->where('path', '.*');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me', [AuthController::class, 'updateProfile']);
    Route::post('/me/password', [AuthController::class, 'changePassword']);
    Route::post('/me/avatar', [AuthController::class, 'updateAvatar']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/dashboard', [FieldApiController::class, 'dashboard']);
    Route::get('/surveys', [FieldApiController::class, 'surveys']);
    Route::get('/my-progress', [FieldApiController::class, 'myProgress']);
    Route::post('/surveys', [FieldApiController::class, 'storeSurvey']);
    Route::get('/surveys/by-dtr', [FieldApiController::class, 'surveyByDtr']);
    Route::get('/surveys/{survey}', [FieldApiController::class, 'surveyShow']);
    Route::post('/surveys/{survey}', [FieldApiController::class, 'updateSurvey']);
    Route::put('/surveys/{survey}', [FieldApiController::class, 'managerUpdateSurvey']);
    Route::patch('/surveys/{survey}', [FieldApiController::class, 'managerUpdateSurvey']);
    Route::delete('/surveys/{survey}', [FieldApiController::class, 'managerDeleteSurvey']);
    Route::delete('/my-dtr-surveys/{survey}', [FieldApiController::class, 'destroyOwnDtrSurvey']);
    Route::post('/surveys/{survey}/submit', [FieldApiController::class, 'submitSurvey']);

    Route::get('/meta/survey-options', [FieldApiController::class, 'surveyOptions']);
    Route::get('/hierarchy/regions', [FieldApiController::class, 'hierarchy']);
    Route::get('/hierarchy/circles', [FieldApiController::class, 'circles']);
    Route::get('/hierarchy/divisions', [FieldApiController::class, 'divisions']);
    Route::get('/hierarchy/zones', [FieldApiController::class, 'zones']);
    Route::get('/hierarchy/zones/{zone}/ancestry', [FieldApiController::class, 'zoneAncestry']);
    Route::get('/hierarchy/substations', [FieldApiController::class, 'substations']);
    Route::get('/hierarchy/feeders', [FieldApiController::class, 'feeders']);
    Route::get('/hierarchy/dtrs', [FieldApiController::class, 'dtrs']);
    Route::post('/hierarchy/dtrs', [FieldApiController::class, 'storeDtr']);
    Route::post('/dtr/check-code', [FieldApiController::class, 'checkDtrCode']);
    Route::get('/hierarchy/bundle', [FieldApiController::class, 'hierarchyBundle']);
    Route::get('/hierarchy/assignable-zones', [FieldApiController::class, 'assignableZones']);
    Route::get('/hierarchy/zones/{zone}/feeders', [FieldApiController::class, 'zoneFeeders']);
    Route::get('/zones/{zone}/feeders', [FieldApiController::class, 'zoneFeeders']);

    Route::get('/approvals', [FieldApiController::class, 'pendingApprovals']);
    Route::post('/surveys/{survey}/approve', [FieldApiController::class, 'approve']);
    Route::post('/surveys/{survey}/reject', [FieldApiController::class, 'reject']);
    Route::post('/surveys/{survey}/unlock', [FieldApiController::class, 'unlockSurvey']);

    Route::get('/consumer/approved', [FieldApiController::class, 'approvedForConsumer']);
    Route::get('/consumer/feeder-dtrs', [FieldApiController::class, 'feederDtrsForConsumer']);
    Route::get('/consumer/search', [FieldApiController::class, 'searchConsumer']);
    Route::get('/consumer/reactivate-requests', [FieldApiController::class, 'listDtrReactivationRequests']);
    Route::get('/consumer/{survey}/poles', [FieldApiController::class, 'poles']);
    Route::post('/consumer/{survey}/poles', [FieldApiController::class, 'storePole']);
    Route::put('/consumer/{survey}/poles/{pole}', [FieldApiController::class, 'updatePole']);
    // POST twin so the Flutter app can send an optional pole photo as multipart.
    Route::post('/consumer/{survey}/poles/{pole}', [FieldApiController::class, 'updatePole']);
    Route::delete('/consumer/{survey}/poles/{pole}', [FieldApiController::class, 'destroyPole']);
    Route::post('/consumer/{survey}/verify', [FieldApiController::class, 'verifyConsumer']);
    Route::post('/consumer/{survey}/exception', [FieldApiController::class, 'exceptionConsumer']);
    Route::post('/consumer/{survey}/finish', [FieldApiController::class, 'finishConsumer']);
    Route::post('/consumer/{survey}/reactivate-request', [FieldApiController::class, 'requestDtrReactivation']);
    Route::get('/consumer/{survey}/my-surveys', [FieldApiController::class, 'listOwnConsumerSurveys']);
    Route::delete('/my-consumer-surveys/{consumerSurvey}', [FieldApiController::class, 'destroyOwnConsumerSurvey']);
    Route::delete('/my-feeder-surveys/{feederSurvey}', [FieldApiController::class, 'destroyOwnFeederSurvey']);

    Route::get('/consumer-surveys', [ConsumerSurveyApprovalController::class, 'index']);
    Route::post('/consumer-surveys/bulk-action', [ConsumerSurveyApprovalController::class, 'bulkAction']);
    Route::get('/consumer-surveys/{consumerSurvey}', [ConsumerSurveyApprovalController::class, 'show']);

    Route::get('/substation-surveys', [SubstationSurveyApiController::class, 'index']);
    Route::post('/substation-surveys', [SubstationSurveyApiController::class, 'store']);
    Route::get('/substation-surveys/{substationSurvey}', [SubstationSurveyApiController::class, 'show']);
    Route::post('/substation-surveys/{substationSurvey}', [SubstationSurveyApiController::class, 'update']);
    Route::post('/substation-surveys/{substationSurvey}/submit', [SubstationSurveyApiController::class, 'submit']);
    Route::post('/substation-surveys/{substationSurvey}/approve', [SubstationSurveyApiController::class, 'approve']);
    Route::post('/substation-surveys/{substationSurvey}/reject', [SubstationSurveyApiController::class, 'reject']);
    Route::post('/substation-surveys/{substationSurvey}/unlock', [SubstationSurveyApiController::class, 'unlock']);

    Route::get('/feeder-surveys/status', [FieldApiController::class, 'feederSurveyStatus']);
    Route::get('/feeder-surveys', [FieldApiController::class, 'feederSurveys']);
    Route::get('/feeder-surveys/{feederSurvey}', [FieldApiController::class, 'showFeederSurvey']);
    Route::post('/feeder-surveys', [FieldApiController::class, 'storeFeederSurvey']);
    Route::post('/feeder-surveys/{feederSurvey}', [FieldApiController::class, 'updateFeederSurvey']);
    Route::put('/feeder-surveys/{feederSurvey}', [FieldApiController::class, 'managerUpdateFeederSurvey']);
    Route::patch('/feeder-surveys/{feederSurvey}', [FieldApiController::class, 'managerUpdateFeederSurvey']);
    Route::delete('/feeder-surveys/{feederSurvey}', [FieldApiController::class, 'managerDeleteFeederSurvey']);
    Route::post('/feeder-surveys/{feederSurvey}/finish-dtr', [FieldApiController::class, 'finishFeederDtr']);
    Route::post('/feeder-surveys/{feederSurvey}/sld', [FieldApiController::class, 'uploadFeederSld']);
    Route::post('/feeder-surveys/{feederSurvey}/approve', [FieldApiController::class, 'approveFeederSurvey']);
    Route::post('/feeder-surveys/{feederSurvey}/reject', [FieldApiController::class, 'rejectFeederSurvey']);
    Route::post('/feeder-surveys/{feederSurvey}/unlock', [FieldApiController::class, 'unlockFeederSurvey']);

    Route::get('/assignments', [FieldApiController::class, 'assignments']);
    Route::post('/assignments', [FieldApiController::class, 'storeAssignment']);
    Route::get('/work-assignments', [FieldApiController::class, 'workAssignments']);
    Route::post('/work-assignments', [FieldApiController::class, 'storeWorkAssignments']);
    Route::post('/work-assignments/{assignment}/reassign', [FieldApiController::class, 'reassignWorkAssignment']);
    Route::delete('/work-assignments/{assignment}', [FieldApiController::class, 'cancelWorkAssignment']);
    Route::get('/field-executives', [FieldApiController::class, 'fieldExecutives']);
    Route::get('/team-audit', [TeamAuditController::class, 'index']);
    Route::get('/team-audit/export', [TeamAuditController::class, 'export']);
    Route::get('/team-audit/{user}', [TeamAuditController::class, 'show']);
    Route::get('/field-executives/{user}/zone-scopes', [FieldApiController::class, 'teamZoneScopes']);
    Route::put('/field-executives/{user}/zone-scopes', [FieldApiController::class, 'updateTeamZoneScopes']);
    Route::get('/activity', [FieldApiController::class, 'activity']);
    Route::get('/notifications', [FieldApiController::class, 'notifications']);
    Route::post('/notifications/read-all', [FieldApiController::class, 'markAllNotificationsRead']);
    Route::post('/notifications/{notification}/read', [FieldApiController::class, 'markNotificationRead']);
});
