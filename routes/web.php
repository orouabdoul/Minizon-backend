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


// Temporary — REMOVE AFTER FIX
Route::get('/debug-dashboard', function () {
    $r = [];
    try {
        $r['step'] = 'roles';
        $driverRoleId    = \Illuminate\Support\Facades\DB::table('roles')->where('name', 'driver')->value('id');
        $passengerRoleId = \Illuminate\Support\Facades\DB::table('roles')->where('name', 'passenger')->value('id');
        $r['driver_role_id']    = $driverRoleId;
        $r['passenger_role_id'] = $passengerRoleId;

        $r['step'] = 'users';
        $r['total_drivers']    = \App\Models\User::where('role_id', $driverRoleId)->count();
        $r['total_passengers'] = \App\Models\User::where('role_id', $passengerRoleId)->count();
        $r['blocked']          = \App\Models\User::where('is_blocked', true)->count();

        $r['step'] = 'trips';
        $r['active_trips']    = \App\Models\Trip::where('status', 'in_progress')->count();
        $r['flagged_trips']   = \App\Models\Trip::where('is_flagged', true)->count();

        $r['step'] = 'payments_sum';
        $r['revenue'] = (float) \App\Models\Payment::where('status', 'success')->sum('commission_amount');

        $r['step'] = 'payments_join';
        $r['top_drivers'] = \App\Models\Payment::join('bookings', 'payments.booking_id', '=', 'bookings.id')
            ->join('trips', 'bookings.trip_id', '=', 'trips.id')
            ->join('users', 'trips.user_id', '=', 'users.id')
            ->leftJoin('profiles', 'users.id', '=', 'profiles.user_id')
            ->where('payments.status', 'success')
            ->selectRaw('users.uuid, users.phone, profiles.first_name, profiles.last_name,
                COUNT(payments.id) as trips_count, SUM(payments.net_amount) as total_earned')
            ->groupBy('users.id', 'users.uuid', 'users.phone', 'profiles.first_name', 'profiles.last_name')
            ->orderByDesc('trips_count')
            ->limit(5)
            ->get()->count();

        $r['step'] = 'disputes';
        $r['open_disputes'] = \App\Models\Dispute::whereIn('status', ['open', 'in_progress', 'pending'])->count();

        $r['step'] = 'profiles';
        $r['pending_kyc'] = \App\Models\Profile::where('kyc_status', 'pending')->count();

        $r['step'] = 'done';
        return response()->json(['ok' => true] + $r);
    } catch (\Throwable $e) {
        return response()->json([
            'ok'    => false,
            'error' => $e->getMessage(),
            'class' => get_class($e),
            'file'  => basename($e->getFile()),
            'line'  => $e->getLine(),
            'so_far'=> $r,
        ]);
    }
});

// Render dashboard blade directly to isolate render errors — REMOVE AFTER FIX
Route::get('/debug-dashboard-render', function () {
    try {
        $alerts      = [];
        $kpis        = [];
        $miniPanels  = [];
        $recentFeed  = [];
        $topDrivers  = [];
        $revenue7d   = array_map(fn($i) => ['label' => now()->subDays($i)->format('d/m'), 'revenue' => 0.0, 'volume' => 0.0], range(6, 0));
        $financials  = ['volume' => '0 FCFA', 'revenue' => '0 FCFA', 'escrow' => '0 FCFA', 'refunded' => '0 FCFA'];
        $feedPage    = 1;
        $driversPage = 1;
        $renderError = '';
        $html = view('admin.dashboard', compact(
            'alerts','kpis','miniPanels','recentFeed','topDrivers',
            'revenue7d','financials','feedPage','driversPage','renderError'
        ))->render();
        return response('<pre>BLADE OK — ' . strlen($html) . ' bytes</pre>' . $html, 200, ['Content-Type' => 'text/html']);
    } catch (\Throwable $e) {
        return response()->json([
            'blade_ok' => false,
            'error'    => $e->getMessage(),
            'class'    => get_class($e),
            'file'     => basename($e->getFile()),
            'line'     => $e->getLine(),
            'trace'    => array_slice(explode("\n", $e->getTraceAsString()), 0, 12),
        ]);
    }
});

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
