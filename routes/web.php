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
use App\Livewire\Admin\Passengers;
use App\Livewire\Admin\Vehicles;
use App\Livewire\Admin\Payouts;
use App\Livewire\Admin\Reports;
use App\Livewire\Admin\Settings;
use App\Livewire\Admin\Notifications;
use App\Livewire\Admin\AuditLog;
use App\Livewire\Admin\Communication;
use App\Livewire\Admin\Tracking;
use App\Livewire\Admin\Reviews;
use App\Livewire\Admin\Refunds;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;



// ── Callback FedaPay — page de retour après paiement (publique, interceptée par le WebView Flutter) ──
Route::get('/payment/return', function () {
    return response(
        '<html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#f5f5f5}'
        . '.card{background:#fff;padding:32px 24px;border-radius:16px;text-align:center;box-shadow:0 4px 24px rgba(0,0,0,.08)}'
        . 'h2{color:#16a34a;margin:0 0 8px}p{color:#6b7280;margin:0}'
        . '</style></head><body><div class="card"><h2>&#10003; Paiement traité</h2>'
        . '<p>Retournez dans l\'application MINIZON.</p></div></body></html>',
        200,
        ['Content-Type' => 'text/html']
    );
})->name('payment.return');

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
        Route::get('passengers',    Passengers::class)->name('passengers');
        Route::get('vehicles',      Vehicles::class)->name('vehicles');
        Route::get('payouts',       Payouts::class)->name('payouts');
        Route::get('reports',        Reports::class)->name('reports');
        Route::get('settings',       Settings::class)->name('settings');
        Route::get('notifications',  Notifications::class)->name('notifications');
        Route::get('audit',          AuditLog::class)->name('audit');
        Route::get('messaging',      Communication::class)->name('messaging');
        Route::get('tracking',       Tracking::class)->name('tracking');
        Route::get('reviews',        Reviews::class)->name('reviews');
        Route::get('refunds',        Refunds::class)->name('refunds');
    });
});
