<?php

namespace App\Livewire\Admin;

use App\Helpers\GeoHelper;
use App\Models\Trip;
use Livewire\Component;

class Tracking extends Component
{
    public string $cityFilter   = '';
    public string $statusFilter = '';   // '' = tous | 'active' | 'pending' | 'completed'
    public ?int   $selectedId   = null;

    public function updatingCityFilter(): void   { $this->selectedId = null; }
    public function updatingStatusFilter(): void { $this->selectedId = null; }

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

        // Position pour la carte : GPS actuel OU point de départ OU point d'arrivée
        [$mapLat, $mapLng] = $this->resolveMapPosition($trip);

        [$depLat, $depLng] = GeoHelper::bestCoords(
            $trip->departure_latitude, $trip->departure_longitude,
            $trip->departure_city           ?? '',
            $trip->departure_arrondissement ?? null,
            $trip->departure_neighborhood   ?? null,
        );
        [$arrLat, $arrLng] = GeoHelper::bestCoords(
            $trip->arrival_latitude, $trip->arrival_longitude,
            $trip->arrival_city           ?? '',
            $trip->arrival_arrondissement ?? null,
            $trip->arrival_neighborhood   ?? null,
        );

        $this->dispatch('trip-focused', [
            'id'            => $trip->id,
            'uuid'          => $trip->uuid,
            'lat'           => $mapLat,
            'lng'           => $mapLng,
            'status'        => $trip->status,
            'has_incident'  => $trip->activeIncident !== null,
            'from'          => $trip->departure_city,
            'to'            => $trip->arrival_city,
            'driver_name'   => $name,
            'driver_phone'  => $trip->user?->phone,
            'departure_lat' => $depLat,
            'departure_lng' => $depLng,
            'arrival_lat'   => $arrLat,
            'arrival_lng'   => $arrLng,
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
        $trips = $this->buildQuery()->get();

        $positions = $trips->map(fn(Trip $t) => $this->tripToPosition($t))->filter()->values()->toArray();

        $this->dispatch('map-positions-updated', positions: $positions);
    }

    public function render()
    {
        $trips = $this->buildQuery()
            ->with(['bookings'])
            ->get();

        $stats = [
            'active'    => Trip::where('status', 'active')->count(),
            'pending'   => Trip::where('status', 'pending')->count(),
            'completed' => Trip::where('status', 'completed')->count(),
            'with_gps'  => Trip::whereIn('status', ['active', 'pending'])
                ->whereNotNull('current_latitude')->whereNotNull('current_longitude')->count(),
            'avg_speed' => (float) Trip::where('status', 'active')
                ->whereNotNull('current_speed')->avg('current_speed'),
        ];

        $cities = Trip::whereIn('status', ['active', 'pending', 'completed'])
            ->distinct()
            ->get(['departure_city', 'arrival_city'])
            ->flatMap(fn($t) => [$t->departure_city, $t->arrival_city])
            ->filter()->unique()->sort()->values();

        $selectedTrip = $this->selectedId
            ? Trip::with(['user.profile', 'vehicle', 'bookings'])->find($this->selectedId)
            : null;

        $positions = $trips->map(fn(Trip $t) => $this->tripToPosition($t))->filter()->values()->toArray();

        return view('admin.tracking', [
            'trips'        => $trips,
            'stats'        => $stats,
            'cities'       => $cities,
            'selectedTrip' => $selectedTrip,
            'positions'    => $positions,
        ])->layout('admin.layouts.app', ['title' => 'Suivi Temps Réel']);
    }

    // ── Helpers privés ────────────────────────────────────────────────────────

    private function buildQuery()
    {
        $statuses = $this->statusFilter !== ''
            ? [$this->statusFilter]
            : ['active', 'pending', 'completed'];

        return Trip::with(['user.profile', 'vehicle', 'activeIncident'])
            ->whereIn('status', $statuses)
            ->when($this->cityFilter, fn($q) => $q->where(function ($q2) {
                $q2->where('departure_city', $this->cityFilter)
                   ->orWhere('arrival_city', $this->cityFilter);
            }))
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'pending' THEN 1 ELSE 2 END")
            ->orderByDesc('created_at');
    }

    private function tripToPosition(Trip $t): ?array
    {
        [$lat, $lng] = $this->resolveMapPosition($t);

        if (! $lat || ! $lng) {
            return null;
        }

        $profile = $t->user?->profile;
        $name    = trim(($profile?->first_name ?? '') . ' ' . ($profile?->last_name ?? '')) ?: 'Conducteur';

        [$depLat, $depLng] = GeoHelper::bestCoords(
            $t->departure_latitude, $t->departure_longitude,
            $t->departure_city ?? '',
            $t->departure_arrondissement ?? null,
            $t->departure_neighborhood   ?? null,
        );
        [$arrLat, $arrLng] = GeoHelper::bestCoords(
            $t->arrival_latitude, $t->arrival_longitude,
            $t->arrival_city ?? '',
            $t->arrival_arrondissement ?? null,
            $t->arrival_neighborhood   ?? null,
        );

        return [
            'id'            => $t->id,
            'uuid'          => $t->uuid,
            'lat'           => $lat,
            'lng'           => $lng,
            'has_gps'       => (bool) ($t->current_latitude && $t->current_longitude),
            'speed'         => $t->current_speed,
            'status'        => $t->status,
            'has_incident'  => $t->activeIncident !== null,
            'is_flagged'    => (bool) $t->is_flagged,
            'from'          => $t->departure_city,
            'to'            => $t->arrival_city,
            'driver_name'   => $name,
            'driver_phone'  => $t->user?->phone,
            'departure_lat' => $depLat,
            'departure_lng' => $depLng,
            'arrival_lat'   => $arrLat,
            'arrival_lng'   => $arrLng,
            'departure_time'=> $t->departure_time?->format('H:i'),
        ];
    }

    private function resolveMapPosition(Trip $t): array
    {
        // 1. GPS actuel (trajet en cours)
        if ($t->current_latitude && $t->current_longitude) {
            return [(float) $t->current_latitude, (float) $t->current_longitude];
        }
        // 2. Point de départ (trajet en attente ou sans signal)
        if ($t->departure_latitude && $t->departure_longitude) {
            return [(float) $t->departure_latitude, (float) $t->departure_longitude];
        }
        // 3. Point d'arrivée (trajet terminé sans GPS)
        if ($t->arrival_latitude && $t->arrival_longitude) {
            return [(float) $t->arrival_latitude, (float) $t->arrival_longitude];
        }

        return [null, null];
    }
}
