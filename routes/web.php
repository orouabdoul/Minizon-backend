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

// ── Temporary debug routes — REMOVE AFTER FIX ────────────────────────────────

// Show last 80 lines of Laravel log to see the actual exception
Route::get('/debug-log', function () {
    $path = storage_path('logs/laravel.log');
    if (! file_exists($path)) return response()->json(['msg' => 'no log file found']);
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $last  = array_slice($lines, -80);
    return response('<pre style="font-size:11px;font-family:monospace;white-space:pre-wrap">'
        . implode("\n", array_map('htmlspecialchars', $last))
        . '</pre>', 200, ['Content-Type' => 'text/html']);
});

// Render trips blade template directly (bypassing Livewire) to isolate blade errors
Route::get('/debug-trips-render', function () {
    try {
        $trips = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15);
        $stats = ['total' => 0, 'active' => 0, 'completed' => 0, 'flagged' => 0, 'revenue' => 0];
        $cities       = collect();
        $selectedTrip = null;
        // Simulate Livewire component properties
        $search = $statusFilter = $cityFilter = $dateFrom = $dateTo = $flagFilter = '';
        $selectedTripId = null;
        $paginators = [];

        $html = view('admin.trips', compact(
            'trips', 'stats', 'cities', 'selectedTrip',
            'search', 'statusFilter', 'cityFilter',
            'dateFrom', 'dateTo', 'flagFilter',
            'selectedTripId', 'paginators'
        ))->render();

        return response('<pre style="font-size:11px">BLADE OK — ' . strlen($html) . ' bytes</pre>'
            . $html, 200, ['Content-Type' => 'text/html']);
    } catch (\Throwable $e) {
        return response()->json([
            'blade_ok' => false,
            'error'    => $e->getMessage(),
            'class'    => class_basename($e),
            'file'     => basename($e->getFile()),
            'line'     => $e->getLine(),
            'trace'    => array_slice(explode("\n", $e->getTraceAsString()), 0, 15),
        ]);
    }
});

Route::get('/debug-trips', function () {
    $r = [];
    try {
        $r['db']        = config('database.default');
        $r['trip_count']= \App\Models\Trip::count();
        $r['active']    = \App\Models\Trip::where('status', 'active')->count();
        $r['flagged']   = \App\Models\Trip::where('is_flagged', true)->count();
        $r['revenue']   = (int) \Illuminate\Support\Facades\DB::table('bookings')
            ->join('trips', 'trips.id', '=', 'bookings.trip_id')
            ->where('trips.status', 'completed')
            ->where('bookings.payment_status', 'escrow_locked')
            ->sum('bookings.total_price');
        $r['cities']    = \App\Models\Trip::distinct()->pluck('departure_city')->take(5)->toArray();
        $r['paginate']  = \App\Models\Trip::with(['user.profile', 'vehicle', 'bookings'])
            ->orderByDesc('departure_time')->paginate(3)->total();
        return response()->json(['ok' => true] + $r);
    } catch (\Throwable $e) {
        return response()->json([
            'ok'    => false,
            'step'  => array_key_last($r),
            'error' => $e->getMessage(),
            'class' => class_basename($e),
            'file'  => basename($e->getFile()),
            'line'  => $e->getLine(),
            'so_far'=> $r,
        ], 200);
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
