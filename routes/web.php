<?php

use App\Livewire\Admin\Login;
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
        Route::get('dashboard', function () {
            return view('admin.dashboard');
        })->name('dashboard');
    });
});
