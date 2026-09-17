<?php

namespace App\Livewire\Admin;

use App\Models\Trip;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class Trips extends Component
{
    use WithPagination;

    public string $search        = '';
    public string $statusFilter  = '';
    public string $cityFilter    = '';
    public string $dateFrom      = '';
    public string $dateTo        = '';
    public string $flagFilter    = '';

    public ?int $selectedTripId = null;

    public function updatingSearch(): void       { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }
    public function updatingCityFilter(): void   { $this->resetPage(); }
    public function updatingDateFrom(): void     { $this->resetPage(); }
    public function updatingDateTo(): void       { $this->resetPage(); }
    public function updatingFlagFilter(): void   { $this->resetPage(); }

    public function viewTrip(int $id): void
    {
        $this->selectedTripId = $id;
    }

    public function closeView(): void
    {
        $this->selectedTripId = null;
    }

    public function toggleFlag(int $tripId): void
    {
        $trip = Trip::findOrFail($tripId);
        $trip->update(['is_flagged' => ! $trip->is_flagged]);
    }

    public function cancelTrip(int $tripId): void
    {
        $trip = Trip::findOrFail($tripId);
        $trip->update(['status' => 'cancelled']);
    }

    public function resetFilters(): void
    {
        $this->search       = '';
        $this->statusFilter = '';
        $this->cityFilter   = '';
        $this->dateFrom     = '';
        $this->dateTo       = '';
        $this->flagFilter   = '';
        $this->resetPage();
    }

    public function render()
    {
        try {
            return $this->doRender();
        } catch (\Throwable $e) {
            $msg = '[' . class_basename($e) . '] ' . $e->getMessage()
                 . ' — ' . basename($e->getFile()) . ':' . $e->getLine();
            logger()->error('[Admin\Trips] render failed: ' . $msg);

            return view('admin.trips', [
                'renderError'  => $msg,
                'trips'        => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15),
                'stats'        => ['total' => 0, 'active' => 0, 'completed' => 0, 'flagged' => 0, 'revenue' => 0],
                'cities'       => collect(),
                'selectedTrip' => null,
            ])->layout('admin.layouts.app', ['title' => 'Trajets']);
        }
    }

    private function doRender()
    {
        $query = Trip::with(['user.profile', 'vehicle', 'bookings'])
            ->when($this->search, function ($q) {
                $s = '%' . $this->search . '%';
                $q->where(function ($q2) use ($s) {
                    $q2->where('departure_city', 'like', $s)
                       ->orWhere('arrival_city', 'like', $s)
                       ->orWhereHas('user', fn($u) => $u->where('phone', 'like', $s)
                           ->orWhereHas('profile', fn($p) => $p->where('first_name', 'like', $s)
                               ->orWhere('last_name', 'like', $s)));
                });
            })
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->cityFilter,   fn($q) => $q->where(function ($q2) {
                $q2->where('departure_city', $this->cityFilter)
                   ->orWhere('arrival_city', $this->cityFilter);
            }))
            ->when($this->dateFrom, fn($q) => $q->whereDate('departure_time', '>=', $this->dateFrom))
            ->when($this->dateTo,   fn($q) => $q->whereDate('departure_time', '<=', $this->dateTo))
            ->when($this->flagFilter === 'flagged',   fn($q) => $q->where('is_flagged', true))
            ->when($this->flagFilter === 'unflagged', fn($q) => $q->where('is_flagged', false))
            ->orderByDesc('departure_time');

        $stats = [
            'total'     => Trip::count(),
            'active'    => Trip::where('status', 'active')->count(),
            'completed' => Trip::where('status', 'completed')->count(),
            'flagged'   => Trip::where('is_flagged', true)->count(),
            'revenue'   => (int) DB::table('bookings')
                ->join('trips', 'trips.id', '=', 'bookings.trip_id')
                ->where('trips.status', 'completed')
                ->where('bookings.payment_status', 'escrow_locked')
                ->sum('bookings.total_price'),
        ];

        $departures = Trip::distinct()->pluck('departure_city');
        $arrivals   = Trip::distinct()->pluck('arrival_city');
        $cities     = $departures->merge($arrivals)->unique()->filter()->sort()->values();

        $selectedTrip = $this->selectedTripId
            ? Trip::with(['user.profile', 'vehicle.vehicleType', 'bookings.passenger.profile', 'bookings.payment', 'incidents'])
                  ->find($this->selectedTripId)
            : null;

        return view('admin.trips', [
            'trips'        => $query->paginate(15),
            'stats'        => $stats,
            'cities'       => $cities,
            'selectedTrip' => $selectedTrip,
        ])->layout('admin.layouts.app', ['title' => 'Trajets']);
    }

}
