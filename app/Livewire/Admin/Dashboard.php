<?php

namespace App\Livewire\Admin;

use App\Models\Booking;
use App\Models\Dispute;
use App\Models\Payment;
use App\Models\Profile;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Dashboard extends Component
{
    public array  $alerts       = [];
    public array  $kpis         = [];
    public array  $miniPanels   = [];
    public array  $recentFeed   = [];
    public array  $topDrivers   = [];
    public array  $revenue7d    = [];
    public array  $financials   = [];
    public string $renderError  = '';

    public int    $feedPage     = 1;
    public int    $driversPage  = 1;

    private const FEED_PER    = 8;
    private const DRIVERS_PER = 8;

    public function feedNext(): void    { $this->feedPage++; }
    public function feedPrev(): void    { if ($this->feedPage > 1) $this->feedPage--; }
    public function driversNext(): void { $this->driversPage++; }
    public function driversPrev(): void { if ($this->driversPage > 1) $this->driversPage--; }

    public function mount(): void
    {
        try {
            $this->loadData();
        } catch (\Throwable $e) {
            $this->renderError = get_class($e) . ': ' . $e->getMessage()
                . ' in ' . basename($e->getFile()) . ':' . $e->getLine();
        }
    }

    private function loadData(): void
    {
        // ── Roles ────────────────────────────────────────────────
        $driverRoleId    = DB::table('roles')->where('name', 'driver')->value('id');
        $passengerRoleId = DB::table('roles')->where('name', 'passenger')->value('id');

        // ── Users ────────────────────────────────────────────────
        $totalDrivers    = User::where('role_id', $driverRoleId)->count();
        $totalPassengers = User::where('role_id', $passengerRoleId)->count();
        $newThisWeek     = User::where('created_at', '>=', now()->startOfWeek())->count();
        $newThisMonth    = User::whereMonth('created_at', now()->month)->count();
        $blockedUsers    = User::where('is_blocked', true)->count();
        $pendingKyc      = Profile::where('kyc_status', 'pending')->count();

        // ── Trips ────────────────────────────────────────────────
        $activeTrips      = Trip::where('status', 'in_progress')->count();
        $completedTrips   = Trip::where('status', 'completed')->count();
        $cancelledTrips   = Trip::where('status', 'cancelled')->count();
        $tripsToday       = Trip::whereDate('created_at', today())->count();
        $flaggedTrips     = Trip::where('is_flagged', true)->count();

        // ── Bookings ─────────────────────────────────────────────
        $totalBookings    = Booking::count();
        $pendingBookings  = Booking::where('status', 'pending')->count();

        // ── Payments ─────────────────────────────────────────────
        $revenueTotal     = (float) Payment::where('status', 'success')->sum('commission_amount');
        $revenueToday     = (float) Payment::where('status', 'success')->whereDate('created_at', today())->sum('commission_amount');
        $volumeTotal      = (float) Payment::where('status', 'success')->sum('gross_amount');
        $escrowLocked     = (float) Payment::where('status', 'pending')->sum('gross_amount');
        $refunded         = (float) Payment::where('status', 'refunded')->sum('gross_amount');

        // ── Disputes ─────────────────────────────────────────────
        $openDisputes     = Dispute::whereIn('status', ['open', 'in_progress', 'pending'])->count();
        $totalDisputes    = Dispute::count();
        $resolvedDisputes = Dispute::where('status', 'resolved')->count();

        // ── Revenue 7 jours ──────────────────────────────────────
        $this->revenue7d = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $this->revenue7d[] = [
                'label'   => $day->format('d/m'),
                'revenue' => (float) Payment::where('status', 'success')->whereDate('created_at', $day)->sum('commission_amount'),
                'volume'  => (float) Payment::where('status', 'success')->whereDate('created_at', $day)->sum('gross_amount'),
            ];
        }

        // ── Top Conducteurs ──────────────────────────────────────
        $this->topDrivers = Payment::join('bookings', 'payments.booking_id', '=', 'bookings.id')
            ->join('trips', 'bookings.trip_id', '=', 'trips.id')
            ->join('users', 'trips.user_id', '=', 'users.id')
            ->leftJoin('profiles', 'users.id', '=', 'profiles.user_id')
            ->where('payments.status', 'success')
            ->selectRaw('users.uuid, users.phone,
                profiles.first_name, profiles.last_name,
                COUNT(payments.id) as trips_count,
                SUM(payments.net_amount) as total_earned')
            ->groupBy('users.id', 'users.uuid', 'users.phone', 'profiles.first_name', 'profiles.last_name')
            ->orderByDesc('trips_count')
            ->limit(20)
            ->get()
            ->map(fn($d) => [
                'name'   => trim(($d->first_name ?? '') . ' ' . ($d->last_name ?? '')) ?: $d->phone,
                'trips'  => $d->trips_count,
                'earned' => $this->formatFcfa($d->total_earned ?? 0),
            ])->toArray();

        // ── Alertes urgentes ─────────────────────────────────────
        $this->alerts = [];
        if ($openDisputes > 0)
            $this->alerts[] = ['type' => 'error', 'message' => $openDisputes . ' litige(s) ouvert(s) nécessitent une attention immédiate', 'link' => '/admin/disputes', 'label' => 'Voir les litiges'];
        if ($pendingKyc > 0)
            $this->alerts[] = ['type' => 'warning', 'message' => $pendingKyc . ' dossier(s) KYC en attente de validation', 'link' => '/admin/drivers', 'label' => 'Valider KYC'];
        if ($flaggedTrips > 0)
            $this->alerts[] = ['type' => 'warning', 'message' => $flaggedTrips . ' trajet(s) signalé(s) en attente de modération', 'link' => '/admin/trips', 'label' => 'Modérer'];
        if ($blockedUsers > 0)
            $this->alerts[] = ['type' => 'info', 'message' => $blockedUsers . ' compte(s) actuellement bloqué(s)', 'link' => '/admin/users', 'label' => 'Voir'];

        // ── KPIs prioritaires ────────────────────────────────────
        $this->kpis = [
            [
                'label'      => 'Trajets actifs',
                'value'      => number_format($activeTrips),
                'sub'        => $tripsToday . ' créés aujourd\'hui',
                'trend'      => $activeTrips > 0 ? 'up' : 'neutral',
                'iconBg'     => '#EDE9FE',
                'iconColor'  => '#7C3AED',
                'icon'       => 'trips',
                'link'       => '/admin/trips',
            ],
            [
                'label'      => 'Revenus plateforme',
                'value'      => $this->formatFcfa($revenueTotal),
                'sub'        => 'Aujourd\'hui : ' . $this->formatFcfa($revenueToday),
                'trend'      => $revenueToday > 0 ? 'up' : 'neutral',
                'iconBg'     => '#FEF9C3',
                'iconColor'  => '#D97706',
                'icon'       => 'revenue',
                'link'       => '/admin/payments',
            ],
            [
                'label'      => 'Nouveaux utilisateurs',
                'value'      => number_format($newThisWeek),
                'sub'        => number_format($newThisMonth) . ' ce mois · ' . number_format($totalDrivers + $totalPassengers) . ' total',
                'trend'      => $newThisWeek > 0 ? 'up' : 'neutral',
                'iconBg'     => '#DCFCE7',
                'iconColor'  => '#16A34A',
                'icon'       => 'users',
                'link'       => '/admin/users',
            ],
            [
                'label'      => 'Litiges ouverts',
                'value'      => number_format($openDisputes),
                'sub'        => $resolvedDisputes . ' résolus · ' . $totalDisputes . ' total',
                'trend'      => $openDisputes > 0 ? 'down' : 'up',
                'urgent'     => $openDisputes > 0,
                'iconBg'     => $openDisputes > 0 ? '#FEE2E2' : '#DCFCE7',
                'iconColor'  => $openDisputes > 0 ? '#DC2626' : '#16A34A',
                'icon'       => 'disputes',
                'link'       => '/admin/disputes',
            ],
        ];

        // ── Mini panneaux droite ─────────────────────────────────
        $this->miniPanels = [
            'users' => [
                ['label' => 'Total inscrits',      'value' => number_format($totalDrivers + $totalPassengers), 'color' => '#1A5FB4'],
                ['label' => 'Conducteurs',          'value' => number_format($totalDrivers),  'color' => '#1A5FB4'],
                ['label' => 'Passagers',            'value' => number_format($totalPassengers), 'color' => '#4F46E5'],
                ['label' => 'Nouveaux ce mois',     'value' => '+' . $newThisMonth, 'color' => '#16A34A'],
                ['label' => 'KYC en attente',       'value' => $pendingKyc, 'color' => $pendingKyc > 0 ? '#D97706' : '#16A34A'],
                ['label' => 'Comptes bloqués',      'value' => $blockedUsers, 'color' => $blockedUsers > 0 ? '#DC2626' : '#6B7684'],
            ],
            'payments' => [
                ['label' => 'Volume total',         'value' => $this->formatFcfa($volumeTotal),  'color' => '#1F2933'],
                ['label' => 'Revenus plateforme',   'value' => $this->formatFcfa($revenueTotal), 'color' => '#1A5FB4'],
                ['label' => 'En escrow',            'value' => $this->formatFcfa($escrowLocked), 'color' => '#D97706'],
                ['label' => 'Remboursés',           'value' => $this->formatFcfa($refunded),     'color' => '#DC2626'],
            ],
            'trips' => [
                ['label' => 'Actifs maintenant',    'value' => $activeTrips,   'color' => '#7C3AED'],
                ['label' => 'Terminés',             'value' => $completedTrips, 'color' => '#16A34A'],
                ['label' => 'Annulés',              'value' => $cancelledTrips, 'color' => $cancelledTrips > 0 ? '#DC2626' : '#6B7684'],
                ['label' => 'Réservations totales', 'value' => $totalBookings,  'color' => '#0D9488'],
                ['label' => 'En attente',           'value' => $pendingBookings,'color' => $pendingBookings > 0 ? '#D97706' : '#6B7684'],
                ['label' => 'Signalés',             'value' => $flaggedTrips,   'color' => $flaggedTrips > 0 ? '#DC2626' : '#6B7684'],
            ],
        ];

        // ── Feed récent ──────────────────────────────────────────
        $recentTrips = Trip::with(['user.profile'])
            ->orderByDesc('updated_at')
            ->limit(30)
            ->get()
            ->map(fn($t) => [
                'type'   => 'trip',
                'title'  => ($t->departure_city ?? '?') . ' → ' . ($t->arrival_city ?? '?'),
                'sub'    => $t->user?->profile
                    ? trim(($t->user->profile->first_name ?? '') . ' ' . ($t->user->profile->last_name ?? ''))
                    : ($t->user?->phone ?? '—'),
                'status' => $t->status,
                'time'   => $t->updated_at?->diffForHumans(),
                'ts'     => $t->updated_at?->timestamp ?? 0,
                'link'   => '/admin/trips',
            ]);

        $recentUsers = User::with('profile')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn($u) => [
                'type'   => 'user',
                'title'  => $u->profile
                    ? trim(($u->profile->first_name ?? '') . ' ' . ($u->profile->last_name ?? '')) ?: $u->phone
                    : $u->phone,
                'sub'    => $u->role_id == $driverRoleId ? 'Conducteur' : 'Passager',
                'status' => $u->is_verified ? 'verified' : 'pending',
                'time'   => $u->created_at?->diffForHumans(),
                'ts'     => $u->created_at?->timestamp ?? 0,
                'link'   => '/admin/users',
            ]);

        $this->recentFeed = $recentTrips->toBase()
            ->concat($recentUsers)
            ->sortByDesc('ts')
            ->values()
            ->toArray();

        // ── Financials summary ───────────────────────────────────
        $this->financials = [
            'volume'  => $this->formatFcfa($volumeTotal),
            'revenue' => $this->formatFcfa($revenueTotal),
            'escrow'  => $this->formatFcfa($escrowLocked),
            'refunded'=> $this->formatFcfa($refunded),
        ];
    }

    private function formatFcfa(float $amount): string
    {
        if ($amount >= 1_000_000) return number_format($amount / 1_000_000, 1) . 'M FCFA';
        if ($amount >= 1_000)     return number_format($amount / 1_000, 0)     . 'K FCFA';
        return number_format($amount, 0) . ' FCFA';
    }

    public function render()
    {
        return view('admin.dashboard')
            ->layout('admin.layouts.app', ['title' => 'Dashboard']);
    }
}
