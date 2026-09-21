<?php

namespace App\Livewire\Admin;

use App\Models\Trip;
use Livewire\Component;

class Tracking extends Component
{
    public string $cityFilter = '';
    public ?int   $selectedId = null;

    public function view(int $id): void
    {
        $this->selectedId = $id;

        $trip = Trip::with([
            'user.profile',
            'activeIncident',
            'locations' => fn($q) => $q->orderBy('recorded_at')->limit(500),
        ])->find($id);

        if (! $trip) {
            return;
        }

        $path    = $trip->locations->map(fn($l) => ['lat' => (float) $l->lat, 'lng' => (float) $l->lng])->values()->toArray();
        $profile = $trip->user?->profile;
        $name    = trim(($profile?->first_name ?? '') . ' ' . ($profile?->last_name ?? '')) ?: 'Conducteur';

        $this->dispatch('trip-focused', [
            'id'            => $trip->id,
            'uuid'          => $trip->uuid,
            'lat'           => $trip->current_latitude,
            'lng'           => $trip->current_longitude,
            'status'        => $trip->status,
            'has_incident'  => $trip->activeIncident !== null,
            'from'          => $trip->departure_city,
            'to'            => $trip->arrival_city,
            'driver_name'   => $name,
            'driver_phone'  => $trip->user?->phone,
            'departure_lat' => $trip->departure_latitude,
            'departure_lng' => $trip->departure_longitude,
            'arrival_lat'   => $trip->arrival_latitude,
            'arrival_lng'   => $trip->arrival_longitude,
            'path'          => $path,
        ]);
    }

    public function closeView(): void
    {
        $this->selectedId = null;
        $this->dispatch('trip-closed');
    }

    public function refreshPositions(): void
    {
        $positions = Trip::with(['user.profile', 'activeIncident'])
            ->where('status', 'active')
            ->get()
            ->map(function (Trip $t) {
                $profile = $t->user?->profile;
                $name    = trim(($profile?->first_name ?? '') . ' ' . ($profile?->last_name ?? '')) ?: 'Conducteur';

                return [
                    'id'            => $t->id,
                    'uuid'          => $t->uuid,
                    'lat'           => $t->current_latitude,
                    'lng'           => $t->current_longitude,
                    'speed'         => $t->current_speed,
                    'status'        => $t->status,
                    'has_incident'  => $t->activeIncident !== null,
                    'is_flagged'    => (bool) $t->is_flagged,
                    'from'          => $t->departure_city,
                    'to'            => $t->arrival_city,
                    'driver_name'   => $name,
                    'driver_phone'  => $t->user?->phone,
                    'departure_lat' => $t->departure_latitude,
                    'departure_lng' => $t->departure_longitude,
                    'arrival_lat'   => $t->arrival_latitude,
                    'arrival_lng'   => $t->arrival_longitude,
                ];
            })->toArray();

        $this->dispatch('map-positions-updated', positions: $positions);
    }

    public function render()
    {
        $trips = Trip::with(['user.profile', 'vehicle', 'bookings', 'activeIncident'])
            ->where('status', 'active')
            ->when($this->cityFilter, fn($q) => $q->where(function ($q2) {
                $q2->where('departure_city', $this->cityFilter)
                   ->orWhere('arrival_city', $this->cityFilter);
            }))
            ->orderByDesc('started_at')
            ->get();

        $stats = [
            'active'      => Trip::where('status', 'active')->count(),
            'with_gps'    => Trip::where('status', 'active')
                ->whereNotNull('current_latitude')->whereNotNull('current_longitude')->count(),
            'without_gps' => Trip::where('status', 'active')
                ->where(fn($q) => $q->whereNull('current_latitude')->orWhereNull('current_longitude'))->count(),
            'avg_speed'   => (float) Trip::where('status', 'active')->whereNotNull('current_speed')->avg('current_speed'),
        ];

        $cities = Trip::where('status', 'active')
            ->distinct()
            ->get(['departure_city', 'arrival_city'])
            ->flatMap(fn($t) => [$t->departure_city, $t->arrival_city])
            ->filter()->unique()->sort()->values();

        $selectedTrip = $this->selectedId
            ? Trip::with(['user.profile', 'vehicle', 'bookings'])->find($this->selectedId)
            : null;

        $positions = $trips->map(function (Trip $t) {
            $profile = $t->user?->profile;
            $name    = trim(($profile?->first_name ?? '') . ' ' . ($profile?->last_name ?? '')) ?: 'Conducteur';
            return [
                'id'            => $t->id,
                'uuid'          => $t->uuid,
                'lat'           => $t->current_latitude,
                'lng'           => $t->current_longitude,
                'speed'         => $t->current_speed,
                'status'        => $t->status,
                'has_incident'  => $t->activeIncident !== null,
                'is_flagged'    => (bool) $t->is_flagged,
                'from'          => $t->departure_city,
                'to'            => $t->arrival_city,
                'driver_name'   => $name,
                'driver_phone'  => $t->user?->phone,
                'departure_lat' => $t->departure_latitude,
                'departure_lng' => $t->departure_longitude,
                'arrival_lat'   => $t->arrival_latitude,
                'arrival_lng'   => $t->arrival_longitude,
            ];
        })->toArray();

        return view('admin.tracking', [
            'trips'        => $trips,
            'stats'        => $stats,
            'cities'       => $cities,
            'selectedTrip' => $selectedTrip,
            'positions'    => $positions,
        ])->layout('admin.layouts.app', ['title' => 'Suivi Temps Réel']);
    }
}
