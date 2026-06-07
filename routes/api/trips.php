<?php

use App\Http\Controllers\Trip\TripController;
use Illuminate\Support\Facades\Route;

// =============================================================================
//  ROUTES TRAJETS — routes/trips.php
//  Chargé dans bootstrap/app.php via :
//      ->withRouting(using: function () {
//          Route::middleware('api')->prefix('api')->group(base_path('routes/trips.php'));
//      })
//  OU dans routes/api.php via :
//      require __DIR__ . '/trips.php';
// =============================================================================


// -----------------------------------------------------------------------------
//  Endpoints PUBLICS — aucune authentification requise
// -----------------------------------------------------------------------------

Route::prefix('trips')->group(function () {

    // GET /api/trips                   — Rechercher des trajets disponibles
    Route::get('/', [TripController::class, 'index']);

    // GET /api/trips/{uuid}            — Consulter la fiche d'un trajet
    Route::get('/{uuid}', [TripController::class, 'show']);

    // GET /api/trips/{uuid}/tracking   — Position GPS live du conducteur
    Route::get('/{uuid}/tracking', [TripController::class, 'getTracking']);
});


// -----------------------------------------------------------------------------
//  Endpoints AUTHENTIFIÉS — conducteurs
// -----------------------------------------------------------------------------

Route::middleware('auth:sanctum')->group(function () {

    Route::prefix('trips')->group(function () {

        // POST   /api/trips            — Publier une offre de trajet
        Route::post('/', [TripController::class, 'store']);

        // PUT    /api/trips/{uuid}     — Modifier un trajet (status = pending uniquement)
        Route::put('/{uuid}', [TripController::class, 'update']);

        // DELETE /api/trips/{uuid}     — Annuler / supprimer un trajet
        Route::delete('/{uuid}', [TripController::class, 'destroy']);

        // POST   /api/trips/{uuid}/start    — Démarrer le voyage
        Route::post('/{uuid}/start', [TripController::class, 'startTrip']);

        // POST   /api/trips/{uuid}/end      — Clôturer le trajet
        Route::post('/{uuid}/end', [TripController::class, 'endTrip']);

        // POST   /api/trips/{uuid}/location — Envoyer coordonnées GPS
        Route::post('/{uuid}/location', [TripController::class, 'updateLocation']);
    });

    // GET /api/driver/trips            — Historique du conducteur connecté
    Route::get('/driver/trips', [TripController::class, 'driverTrips']);

});


// -----------------------------------------------------------------------------
//  Endpoints ADMIN uniquement
// -----------------------------------------------------------------------------

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {

    // GET /api/admin/trips             — Supervision globale de tous les trajets
    Route::get('/trips', [TripController::class, 'adminIndex']);

});