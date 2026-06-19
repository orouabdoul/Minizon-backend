<?php

use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  👥 ROUTES ADMIN — Gestion des utilisateurs (Back-Office)
// ============================================================

Route::middleware('auth:sanctum')->prefix('admin/users')->group(function () {

    Route::get('metrics',              [UserController::class, 'metrics']);
    Route::get('/',                    [UserController::class, 'index']);
    Route::post('/',                   [UserController::class, 'store']);
    Route::get('{uuid}',               [UserController::class, 'show']);
    Route::put('{uuid}',               [UserController::class, 'update']);
    Route::delete('{uuid}',            [UserController::class, 'destroy']);
    Route::put('{uuid}/approve-kyc',   [UserController::class, 'approveKyc']);
    Route::put('{uuid}/reject-kyc',    [UserController::class, 'rejectKyc']);
    Route::put('{uuid}/suspend',       [UserController::class, 'suspend']);
    Route::put('{uuid}/unsuspend',     [UserController::class, 'unsuspend']);
});
