<?php
/**
 * Add these routes to your routes/api.php
 * inside the auth:sanctum middleware group (Route::prefix('v1')...)
 *
 * Also add to your existing RequestController::stats() method:
 *   'mp_pending'  => \App\Models\ManpowerRequest::where('status','pending_hr')->count(),
 *   'mp_approved' => \App\Models\ManpowerRequest::where('status','approved')->count(),
 *
 * And add to top of api.php:
 *   use App\Http\Controllers\ManpowerRequestController;
 */

Route::prefix('manpower-requests')->group(function () {
    Route::get('/',                          [ManpowerRequestController::class, 'index']);
    Route::post('/',                         [ManpowerRequestController::class, 'store']);
    Route::get('/stats',                     [ManpowerRequestController::class, 'stats']);
    Route::get('/{manpowerRequest}',         [ManpowerRequestController::class, 'show']);
    Route::put('/{manpowerRequest}',         [ManpowerRequestController::class, 'update']);
    Route::post('/{manpowerRequest}/submit', [ManpowerRequestController::class, 'submit']);
    Route::post('/{manpowerRequest}/approve',[ManpowerRequestController::class, 'approve']);
    Route::post('/{manpowerRequest}/reject', [ManpowerRequestController::class, 'reject']);
});

/**
 * Also add manpower_request_id column to your jobs table if not present:
 *
 * Schema::table('jobs', function (Blueprint $table) {
 *     $table->unsignedBigInteger('manpower_request_id')->nullable()->after('id');
 *     $table->string('source')->nullable()->after('manpower_request_id'); // 'manpower_request' | 'direct'
 *     $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
 * });
 */
