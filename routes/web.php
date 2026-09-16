<?php

use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\Login;
use App\Livewire\Admin\Drivers;
use App\Livewire\Admin\Users;
use App\Livewire\Admin\Trips;
use App\Livewire\Admin\Payments;
use App\Livewire\Admin\Disputes;
use App\Livewire\Admin\Support;
use App\Livewire\Admin\Reservations;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::guard('admin')->check()) {
        return redirect()->route('panel.dashboard');
    }
    return redirect()->route('panel.login');
});

// ── Admin web routes ──────────────────────────────────────────────────────────
Route::prefix('admin')->name('panel.')->group(function () {

    // Guest only — si déjà connecté, aller au dashboard
    Route::get('login', Login::class)->name('login')->middleware('guest:admin');

    // Logout
    Route::post('logout', function () {
        Auth::guard('admin')->logout();
        session()->invalidate();
        session()->regenerateToken();
        return redirect()->route('panel.login');
    })->name('logout');

    // Protected admin pages
    Route::middleware(\App\Http\Middleware\AdminAuth::class)->group(function () {
        Route::get('dashboard', Dashboard::class)->name('dashboard');
        Route::get('users',     Users::class)->name('users');
        Route::get('drivers',   Drivers::class)->name('drivers');
        Route::get('trips',     Trips::class)->name('trips');
        Route::get('payments',  Payments::class)->name('payments');
        Route::get('disputes',  Disputes::class)->name('disputes');
        Route::get('support',       Support::class)->name('support');
        Route::get('reservations',  Reservations::class)->name('reservations');
    });
});
