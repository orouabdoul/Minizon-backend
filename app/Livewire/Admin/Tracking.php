<?php

namespace App\Livewire\Admin;

use App\Models\Trip;
use Livewire\Component;

class Tracking extends Component
{
    public string $cityFilter = '';
    public ?int   $selectedId = null;

    public function view(int $id): void   { $this->selectedId = $id; }
    public function closeView(): void     { $this->selectedId = null; }

    public function render()
    {
        $activeTrips = Trip::with(['user.profile', 'vehicle', 'bookings'])
            ->where('status', 'active')
            ->when($this->cityFilter, fn($q) => $q->where(function ($q2) {
                $q2->where('departure_city', $this->cityFilter)
                   ->orWhere('arrival_city', $this->cityFilter);
            }))
            ->orderByDesc('started_at');

        $stats = [
            'active'        => Trip::where('status', 'active')->count(),
            'with_gps'      => Trip::where('status', 'active')
                ->whereNotNull('current_latitude')
                ->whereNotNull('current_longitude')
                ->count(),
            'without_gps'   => Trip::where('status', 'active')
                ->where(fn($q) => $q->whereNull('current_latitude')
                    ->orWhereNull('current_longitude'))
                ->count(),
            'avg_speed'     => (float) Trip::where('status', 'active')
                ->whereNotNull('current_speed')
                ->avg('current_speed'),
        ];

        $cities = Trip::where('status', 'active')
            ->distinct()
            ->get(['departure_city', 'arrival_city'])
            ->flatMap(fn($t) => [$t->departure_city, $t->arrival_city])
            ->filter()->unique()->sort()->values();

        $selectedTrip = $this->selectedId
            ? Trip::with(['user.profile', 'vehicle', 'bookings'])->find($this->selectedId)
            : null;

        return view('admin.tracking', [
            'trips'        => $activeTrips->get(),
            'stats'        => $stats,
            'cities'       => $cities,
            'selectedTrip' => $selectedTrip,
        ])->layout('admin.layouts.app', ['title' => 'Suivi Temps Réel']);
    }
}
