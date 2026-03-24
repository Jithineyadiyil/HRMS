<?php
// Add these routes inside your existing authenticated API group
// in routes/api.php

// ── Contracts ─────────────────────────────────────────────────────────────────
Route::prefix('contracts')->group(function () {

    // Stats
    Route::get('stats', [ContractController::class, 'stats']);

    // Renewals list & detail (before the resource so {id} doesn't clash)
    Route::get('renewals',             [ContractController::class, 'renewals']);
    Route::get('renewals/{renewal}',   [ContractController::class, 'showRenewal']);
    Route::post('renewals/{renewal}/approve', [ContractController::class, 'approveRenewal']);
    Route::post('renewals/{renewal}/reject',  [ContractController::class, 'rejectRenewal']);

    // CRUD
    Route::get('/',          [ContractController::class, 'index']);
    Route::post('/',         [ContractController::class, 'store']);
    Route::get('{contract}', [ContractController::class, 'show']);
    Route::put('{contract}', [ContractController::class, 'update']);

    // Manual renewal trigger
    Route::post('{contract}/trigger-renewal', [ContractController::class, 'triggerRenewal']);
});
