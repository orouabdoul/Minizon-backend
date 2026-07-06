<?php

use App\Http\Controllers\Passenger\PassengerSearchController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  👤 PASSENGER — Recherche de trajets (SearchView)
// ============================================================

// Public — accessible sans token (la réservation requiert auth:sanctum)
Route::get('passenger/search', [PassengerSearchController::class, 'search'])
    ->name('passenger.search');
