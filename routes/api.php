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
    'message' => 'Minizon API is alive',
    'version' => '1.0.0',
    'env'     => app()->environment(),
]))->name('api.ping');


// -----------------------------------------------------------------------
// Modules de routes
// -----------------------------------------------------------------------

require __DIR__ . '/api/auth.php';


// ── Driver (conducteur) — par page ────────────────────────────────────────
require __DIR__ . '/api/driver-arrival.php';
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
require __DIR__ . '/api/driver-reviews.php';
require __DIR__ . '/api/driver-statistics.php';
require __DIR__ . '/api/driver-safety.php';
require __DIR__ . '/api/driver-support.php';
require __DIR__ . '/api/driver-vehicles.php';
require __DIR__ . '/api/driver-withdraw.php';


// ── Passenger (passager) — par page ──────────────────────────────────────
require __DIR__ . '/api/passenger-home.php';
require __DIR__ . '/api/passenger-profile.php';
require __DIR__ . '/api/passenger-messager.php';
require __DIR__ . '/api/passenger-notifications.php';
require __DIR__ . '/api/passenger-refund.php';
require __DIR__ . '/api/passenger-reservations.php';
require __DIR__ . '/api/passenger-trip-detail.php';
require __DIR__ . '/api/passenger-safety.php';
require __DIR__ . '/api/passenger-search.php';
require __DIR__ . '/api/passenger-support.php';
require __DIR__ . '/api/passenger-trip-confirmation.php';
require __DIR__ . '/api/passenger-trip-history.php';


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

