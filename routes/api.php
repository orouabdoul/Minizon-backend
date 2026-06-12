<?php

/**
 * ============================================================
 *  ROUTES API — POINT D'ENTRÉE PRINCIPAL
 *  Fichier : routes/api.php
 * ============================================================
 */

use Illuminate\Support\Facades\Route;

// Healthcheck
Route::get('ping', fn () => response()->json([
    'success' => true,
    'message' => 'Minizon API is alive 🚀',
    'version' => '1.0.0',
    'env'     => app()->environment(),
]))->name('api.ping');

// -----------------------------------------------------------------------
// Modules de routes
// -----------------------------------------------------------------------

require __DIR__ . '/api/auth.php';
require __DIR__ . '/api/trips.php';
require __DIR__ . '/api/vehicles.php';
require __DIR__ . '/api/bookings.php';
require __DIR__ . '/api/payments.php';