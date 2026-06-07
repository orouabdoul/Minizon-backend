<?php

/**
 * ============================================================
 *  ROUTES API — POINT D'ENTRÉE PRINCIPAL
 *  Fichier : routes/api.php
 * ============================================================
 *
 *  Ce fichier charge chaque module de routes depuis routes/api/.
 *  Ajoute un nouveau fichier dans routes/api/ et inclus-le ici.
 *
 *  Préfixe global : /api  (défini dans bootstrap/app.php)
 */

use Illuminate\Support\Facades\Route;

// -----------------------------------------------------------------------
// Healthcheck (utile pour les load balancers / monitoring)
// -----------------------------------------------------------------------
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

// À décommenter au fur et à mesure de l'avancement du projet :
// require __DIR__ . '/api/rides.php';
// require __DIR__ . '/api/deliveries.php';
// require __DIR__ . '/api/payments.php';
// require __DIR__ . '/api/notifications.php';
// require __DIR__ . '/api/admin.php';