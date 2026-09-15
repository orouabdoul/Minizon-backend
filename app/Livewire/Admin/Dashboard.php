<?php

namespace App\Livewire\Admin;

use App\Models\Booking;
use App\Models\Dispute;
use App\Models\Payment;
use App\Models\Trip;
use App\Models\User;
use App\Models\Withdrawal;
use Livewire\Component;

class Dashboard extends Component
{
    public array $kpis     = [];
    public array $activity = [];

    public function mount(): void
    {
        $this->loadData();
    }

    private function loadData(): void
    {
        $totalDrivers    = User::whereHas('role', fn($q) => $q->where('name', 'driver'))->count();
        $totalPassengers = User::whereHas('role', fn($q) => $q->where('name', 'passenger'))->count();
        $newThisMonth    = User::whereMonth('created_at', now()->month)->count();
        $pendingKyc      = \App\Models\Profile::where('kyc_status', 'pending')->count();

        $activeTrips     = Trip::where('status', 'in_progress')->count();
        $completedTrips  = Trip::where('status', 'completed')->count();
        $cancelledTrips  = Trip::where('status', 'cancelled')->count();
        $tripsThisMonth  = Trip::whereMonth('created_at', now()->month)->count();

        $totalBookings    = Booking::count();
        $pendingBookings  = Booking::where('status', 'pending')->count();
        $cancelledBookings= Booking::where('status', 'cancelled')->count();

        $platformRevenue = Payment::where('status', 'success')->sum('commission_amount') ?? 0;
        $escrowLocked    = Payment::where('status', 'pending')->sum('amount') ?? 0;

        $openDisputes    = Dispute::whereIn('status', ['open', 'in_progress'])->count();
        $totalDisputes   = Dispute::count();

        $this->kpis = [
            ['label' => 'Utilisateurs Totaux',   'value' => number_format($totalDrivers + $totalPassengers), 'badge' => '+' . $newThisMonth . ' ce mois',        'variant' => 'success', 'iconBg' => '#D6E8F7', 'iconColor' => '#1A5FB4', 'icon' => 'users'],
            ['label' => 'Conducteurs Actifs',     'value' => number_format($totalDrivers),                    'badge' => $totalDrivers . ' enregistrés',           'variant' => 'success', 'iconBg' => '#DCFCE7', 'iconColor' => '#1A5FB4', 'icon' => 'drivers'],
            ['label' => 'Trajets Actifs',         'value' => number_format($activeTrips),                     'badge' => $tripsThisMonth . ' ce mois',             'variant' => $activeTrips > 0 ? 'success' : 'neutral', 'iconBg' => '#F3E8FF', 'iconColor' => '#9333EA', 'icon' => 'trips'],
            ['label' => 'Revenus Plateforme',     'value' => $this->formatFcfa($platformRevenue),             'badge' => 'Escrow : ' . $this->formatFcfa($escrowLocked), 'variant' => 'success', 'iconBg' => '#FEF9C3', 'iconColor' => '#F4B400', 'icon' => 'revenue'],
            ['label' => 'Passagers',              'value' => number_format($totalPassengers),                  'badge' => 'KYC : ' . $pendingKyc . ' en attente',  'variant' => $pendingKyc > 0 ? 'warning' : 'success', 'iconBg' => '#E0E7FF', 'iconColor' => '#4F46E5', 'icon' => 'passengers'],
            ['label' => 'Réservations',           'value' => number_format($totalBookings),                    'badge' => $pendingBookings . ' en attente',         'variant' => $cancelledBookings > 0 ? 'warning' : 'success', 'iconBg' => '#CCFBF1', 'iconColor' => '#0D9488', 'icon' => 'bookings'],
            ['label' => 'Litiges',                'value' => number_format($totalDisputes),                    'badge' => $openDisputes . ' ouverts',               'variant' => $openDisputes > 0 ? 'error' : 'success', 'iconBg' => '#FEE2E2', 'iconColor' => '#E53935', 'icon' => 'disputes'],
            ['label' => 'Trajets Terminés',       'value' => number_format($completedTrips),                   'badge' => $cancelledTrips . ' annulés',             'variant' => $cancelledTrips > 0 ? 'warning' : 'success', 'iconBg' => '#DCFCE7', 'iconColor' => '#1A5FB4', 'icon' => 'completed'],
        ];

        $this->activity = Trip::with(['user.profile'])
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(fn($trip) => [
                'uuid'   => $trip->uuid,
                'driver' => $trip->user?->profile
                    ? trim(($trip->user->profile->first_name ?? '') . ' ' . ($trip->user->profile->last_name ?? ''))
                    : ($trip->user?->phone ?? '—'),
                'from'   => $trip->departure_city ?? $trip->departure_address ?? '—',
                'to'     => $trip->arrival_city   ?? $trip->arrival_address   ?? '—',
                'status' => $trip->status,
                'date'   => $trip->created_at?->format('d/m H:i'),
            ])->toArray();
    }

    private function formatFcfa(float $amount): string
    {
        if ($amount >= 1_000_000) return number_format($amount / 1_000_000, 1) . 'M FCFA';
        if ($amount >= 1_000)     return number_format($amount / 1_000, 0)     . 'K FCFA';
        return number_format($amount) . ' FCFA';
    }

    public function render()
    {
        return view('admin.dashboard')
            ->layout('admin.layouts.app', ['title' => 'Dashboard']);
    }
}
