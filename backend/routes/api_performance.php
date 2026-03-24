<?php
use App\Http\Controllers\PerformanceController;
use Illuminate\Support\Facades\Route;

Route::prefix('performance')->middleware(['auth:sanctum'])->group(function () {

    // Stats
    Route::get('stats', [PerformanceController::class, 'stats']);

    // Cycles
    Route::get   ('cycles',               [PerformanceController::class, 'cyclesIndex']);
    Route::post  ('cycles',               [PerformanceController::class, 'cyclesStore']);
    Route::put   ('cycles/{cycle}',       [PerformanceController::class, 'cyclesUpdate']);
    Route::post  ('cycles/{cycle}/activate', [PerformanceController::class, 'cyclesActivate']);
    Route::post  ('cycles/{cycle}/close',    [PerformanceController::class, 'cyclesClose']);

    // Reviews
    Route::get   ('reviews',              [PerformanceController::class, 'reviewsIndex']);
    Route::post  ('reviews',              [PerformanceController::class, 'reviewsStore']);
    Route::get   ('reviews/{review}',     [PerformanceController::class, 'reviewsShow']);
    Route::put   ('reviews/{review}',     [PerformanceController::class, 'reviewsUpdate']);
    Route::post  ('reviews/{review}/self-assessment',   [PerformanceController::class, 'selfAssessment']);
    Route::post  ('reviews/{review}/manager-evaluation',[PerformanceController::class, 'managerEvaluation']);

    // Goals
    Route::get   ('goals',                [PerformanceController::class, 'goalsIndex']);
    Route::post  ('goals',                [PerformanceController::class, 'goalsStore']);
    Route::put   ('goals/{goal}',         [PerformanceController::class, 'goalsUpdate']);
    Route::patch ('goals/{goal}',         [PerformanceController::class, 'goalsProgressUpdate']);

    // KPIs
    Route::get   ('kpis',                 [PerformanceController::class, 'kpisIndex']);
    Route::post  ('kpis',                 [PerformanceController::class, 'kpisStore']);
    Route::put   ('kpis/{kpi}',           [PerformanceController::class, 'kpisUpdate']);

    // 360 Feedback
    Route::get   ('feedback',             [PerformanceController::class, 'feedbackIndex']);
    Route::post  ('feedback',             [PerformanceController::class, 'feedbackStore']);
});
