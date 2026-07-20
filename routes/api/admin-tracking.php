<?php

use App\Http\Controllers\Admin\AdminTrackingController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  👑 ADMIN — Suivi des trajets en temps réel
//
//  Flux frontend (polling toutes les 15s) :
//    GET  /api/admin/tracking           → liste des TrackedTrip actifs
//    GET  /api/admin/tracking/stats     → KPIs (barre en haut)
//    GET  /api/admin/tracking/{uuid}    → détail + passagers + historique incidents
//    POST /api/admin/tracking/{uuid}/incident          → signaler incident
//    PATCH /api/admin/tracking/{uuid}/incident/resolve → résoudre incident
//
//  Endpoint driver (push GPS depuis device conducteur) :
//    POST /api/trips/{uuid}/location    → TripController::updateLocation
// ============================================================

Route::middleware('auth:sanctum')->prefix('admin/tracking')->group(function () {

    Route::get('/',           [AdminTrackingController::class, 'activeTrips']);
    Route::get('stats',       [AdminTrackingController::class, 'stats']);
    Route::get('{uuid}',      [AdminTrackingController::class, 'show']);

    Route::post('{uuid}/incident',          [AdminTrackingController::class, 'reportIncident']);
    Route::patch('{uuid}/incident/resolve', [AdminTrackingController::class, 'resolveIncident']);

});
