<?php

namespace App\Livewire\Admin;

use App\Models\User;
use App\Models\Trip;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\DriverPayout;
use App\Models\Dispute;
use App\Models\SupportTicket;
use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Reports extends Component
{
    public string $period = '30'; // 7, 30, 90, 365

    public function render()
    {
        $days  = (int) $this->period;
        $from  = now()->subDays($days)->startOfDay();
        $today = now();

        // ── Global platform stats ─────────────────────────────────────────
        $overview = [
            'users_total'    => User::count(),
            'users_new'      => User::where('created_at', '>=', $from)->count(),
            'drivers_total'  => User::whereHas('role', fn($q) => $q->where('name', 'driver'))->count(),
            'drivers_new'    => User::whereHas('role', fn($q) => $q->where('name', 'driver'))->where('created_at', '>=', $from)->count(),
            'trips_total'    => Trip::count(),
            'trips_period'   => Trip::where('created_at', '>=', $from)->count(),
            'bookings_total' => Booking::count(),
            'bookings_period'=> Booking::where('created_at', '>=', $from)->count(),
            'revenue_total'  => Payment::where('status', 'completed')->sum('gross_amount'),
            'revenue_period' => Payment::where('status', 'completed')->where('created_at', '>=', $from)->sum('gross_amount'),
            'commission_period' => Payment::where('status', 'completed')->where('created_at', '>=', $from)->sum('commission_amount'),
            'disputes_period' => Dispute::where('created_at', '>=', $from)->count(),
            'open_disputes'   => Dispute::whereIn('status', ['pending', 'investigating'])->count(),
            'tickets_period'  => SupportTicket::where('created_at', '>=', $from)->count(),
            'open_tickets'    => SupportTicket::whereIn('status', ['new', 'in_progress'])->count(),
        ];

        // ── Top cities by trips ───────────────────────────────────────────
        $topCities = Trip::select('departure_city', DB::raw('count(*) as cnt'))
            ->groupBy('departure_city')
            ->orderByDesc('cnt')
            ->limit(8)
            ->get();

        // ── Trips by status ───────────────────────────────────────────────
        $tripsByStatus = Trip::select('status', DB::raw('count(*) as cnt'))
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        // ── Revenue by day (last period) ──────────────────────────────────
        $revenueByDay = Payment::select(
                DB::raw('DATE(created_at) as day'),
                DB::raw('SUM(gross_amount) as total'),
                DB::raw('SUM(commission_amount) as commission')
            )
            ->where('status', 'completed')
            ->where('created_at', '>=', $from)
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        // ── Top drivers ───────────────────────────────────────────────────
        $topDrivers = Trip::select('user_id', DB::raw('count(*) as trips_count'), DB::raw('sum(completed_at is not null) as completed'))
            ->where('trips.status', 'completed')
            ->where('created_at', '>=', $from)
            ->groupBy('user_id')
            ->orderByDesc('trips_count')
            ->with('user.profile')
            ->limit(5)
            ->get();

        // ── Payments by method ────────────────────────────────────────────
        $payByMethod = Payment::select('provider', DB::raw('count(*) as cnt'), DB::raw('sum(gross_amount) as total'))
            ->where('status', 'completed')
            ->groupBy('provider')
            ->get();

        return view('admin.reports', [
            'overview'      => $overview,
            'topCities'     => $topCities,
            'tripsByStatus' => $tripsByStatus,
            'revenueByDay'  => $revenueByDay,
            'topDrivers'    => $topDrivers,
            'payByMethod'   => $payByMethod,
            'period'        => $days,
            'from'          => $from,
        ])->layout('admin.layouts.app', ['title' => 'Rapports']);
    }
}
