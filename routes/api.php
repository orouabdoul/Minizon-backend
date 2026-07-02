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
require __DIR__ . '/api/withdrawals.php';
require __DIR__ . '/api/notifications.php';
require __DIR__ . '/api/chat.php';
require __DIR__ . '/api/disputes.php';
require __DIR__ . '/api/roles.php';


// ── Driver (conducteur) — par page ────────────────────────────────────────
require __DIR__ . '/api/driver-home.php';
require __DIR__ . '/api/driver-profile.php';
require __DIR__ . '/api/driver-add-trip.php';
require __DIR__ . '/api/driver-trip-detail.php';
require __DIR__ . '/api/driver-trips.php';
require __DIR__ . '/api/driver-active-trip.php';
require __DIR__ . '/api/driver-end-trip.php';
require __DIR__ . '/api/driver-interactive-map.php';
require __DIR__ . '/api/driver-messager.php';
require __DIR__ . '/api/driver-notifications.php';
require __DIR__ . '/api/driver-payment-history.php';
require __DIR__ . '/api/driver-bookings.php';


// ── Admin — gestion des conducteurs & passagers ───────────────────────────
require __DIR__ . '/api/drivers.php';
require __DIR__ . '/api/passengers.php';

// Routes pour l'administration (protégées par auth:admin)
require __DIR__ . '/api/admin.php';
require __DIR__ . '/api/admin-users.php';
require __DIR__ . '/api/admin-trips.php';
require __DIR__ . '/api/admin-reservations.php';
require __DIR__ . '/api/admin-payments.php';
require __DIR__ . '/api/admin-disputes.php';
require __DIR__ . '/api/admin-support.php';
require __DIR__ . '/api/admin-notifications.php';
require __DIR__ . '/api/admin-settings.php';
require __DIR__ . '/api/admin-vehicles.php';

// Routes sandbox (jamais en production)
if (! app()->environment('production')) {
    require __DIR__ . '/api/sandbox.php';
}
