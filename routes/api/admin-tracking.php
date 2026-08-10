<?php

use App\Http\Controllers\Admin\AdminTrackingController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  👑 ADMIN — Suivi des trajets (temps réel + historique)
//
//  Lecture / KPIs :
//    GET  /api/admin/tracking/trips                    → liste paginée tous statuts + filtres
//    GET  /api/admin/tracking/live                     → positions GPS seules (polling 5s)
//    GET  /api/admin/tracking/stats                    → KPIs enrichis
//    GET  /api/admin/tracking/incidents                → tous incidents filtrables
//
//  Détail d'un trajet :
//    GET  /api/admin/tracking/{uuid}                   → détail + passagers + pickup/dropoff GPS + timeline
//
//  Actions admin sur un trajet :
//    POST  /api/admin/tracking/{uuid}/incident         → signaler incident
//    PATCH /api/admin/tracking/{uuid}/incident/resolve → résoudre incident
//    PATCH /api/admin/tracking/{uuid}/flag             → flaguer / déflaguer (modération)
//    POST  /api/admin/tracking/{uuid}/notify-driver    → envoyer FCM au conducteur
//
//  Endpoint driver (push GPS depuis l'app conducteur) :
//    POST /api/trips/{uuid}/location                   → TripController::updateLocation
// ============================================================

Route::middleware('auth:sanctum')->prefix('admin/tracking')->group(function () {

    // ── Routes nommées AVANT le wildcard {uuid} ───────────────────────────────
    Route::get('trips',     [AdminTrackingController::class, 'activeTrips']);
    Route::get('live',      [AdminTrackingController::class, 'live']);
    Route::get('stats',     [AdminTrackingController::class, 'stats']);
    Route::get('incidents', [AdminTrackingController::class, 'incidents']);

    // ── Wildcard — doit rester APRÈS les routes nommées ───────────────────────
    Route::get('{uuid}', [AdminTrackingController::class, 'show']);

    // ── Actions sur un trajet ──────────────────────────────────────────────────
    Route::post ('{uuid}/incident',          [AdminTrackingController::class, 'reportIncident']);
    Route::patch('{uuid}/incident/resolve',  [AdminTrackingController::class, 'resolveIncident']);
    Route::patch('{uuid}/flag',              [AdminTrackingController::class, 'flagTrip']);
    Route::post ('{uuid}/notify-driver',     [AdminTrackingController::class, 'notifyDriver']);

});
