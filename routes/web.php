<?php

use App\Livewire\Admin\Login;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('admin.login');
});

// ── Admin web routes ──────────────────────────────────────────────────────────
Route::prefix('admin')->name('admin.')->group(function () {

    // Guest only
    Route::middleware('guest:admin')->group(function () {
        Route::get('login', Login::class)->name('login');
    });

    // Logout
    Route::post('logout', function () {
        Auth::guard('admin')->logout();
        session()->invalidate();
        session()->regenerateToken();
        return redirect()->route('admin.login');
    })->name('logout');

    // Protected admin pages
    Route::middleware(\App\Http\Middleware\AdminAuth::class)->group(function () {
        Route::get('dashboard', function () {
            return view('admin.dashboard');
        })->name('dashboard');
    });
});
