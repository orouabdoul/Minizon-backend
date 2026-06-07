<?php

/**
 * ============================================================
 *  ROUTES — AUTHENTIFICATION & KYC
 *  Fichier : routes/api/auth.php
 * ============================================================
 *
 *  Préfixe  : /api/auth
 *  Middleware public  : throttle:10,1  (10 req/min anti-brute-force)
 *  Middleware privé   : auth:sanctum
 */

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

// -----------------------------------------------------------------------
// 🔓 ROUTES PUBLIQUES (sans token)
// -----------------------------------------------------------------------

Route::prefix('auth')->group(function () {

    // Étape 1 : Demande d'OTP
    Route::post('send-otp',   [AuthController::class, 'sendOtp'])
        ->middleware('throttle:10,1')
        ->name('auth.send-otp');

    // Étape 2 : Vérification de l'OTP → délivrance du token
    Route::post('verify-otp', [AuthController::class, 'verifyOtp'])
        ->middleware('throttle:10,1')
        ->name('auth.verify-otp');

    // Étape 3 : Inscription + soumission KYC
    Route::post('register',   [AuthController::class, 'register'])
        ->name('auth.register');

    // Connexion Back-Office (email/password)
    Route::post('admin/login', [AuthController::class, 'adminLogin'])
        ->middleware('throttle:5,1')
        ->name('auth.admin.login');

    // -----------------------------------------------------------------------
    // 🔒 ROUTES PRIVÉES (token Sanctum requis)
    // -----------------------------------------------------------------------

    Route::middleware('auth:sanctum')->group(function () {

        // Session
        Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('me',      [AuthController::class, 'me'])->name('auth.me');

        // CRUD Utilisateur
        Route::get('users/{uuid}',    [AuthController::class, 'show'])->name('auth.users.show');
        Route::put('users/{uuid}',    [AuthController::class, 'update'])->name('auth.users.update');
        Route::delete('users/{uuid}', [AuthController::class, 'delete'])->name('auth.users.delete');

        // Panel Admin
        Route::prefix('admin')->name('auth.admin.')->group(function () {
            Route::get('users',                              [AuthController::class, 'index'])->name('users.index');
            Route::post('users/{uuid}/validate-kyc',         [AuthController::class, 'validateKyc'])->name('users.validate-kyc');
            Route::post('users/{uuid}/toggle-block',         [AuthController::class, 'toggleBlock'])->name('users.toggle-block');
        });
    });
});