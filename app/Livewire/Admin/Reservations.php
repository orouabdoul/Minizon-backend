<?php

namespace App\Livewire\Admin;

use App\Models\Booking;
use Livewire\Component;
use Livewire\WithPagination;

class Reservations extends Component
{
    use WithPagination;

    public string $search        = '';
    public string $statusFilter  = '';
    public string $payFilter     = '';
    public string $dateFrom      = '';
    public string $dateTo        = '';

    public ?int $selectedId = null;

    public function updatingSearch(): void       { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }
    public function updatingPayFilter(): void    { $this->resetPage(); }
    public function updatingDateFrom(): void     { $this->resetPage(); }
    public function updatingDateTo(): void       { $this->resetPage(); }

    public function view(int $id): void   { $this->selectedId = $id; }
    public function closeView(): void     { $this->selectedId = null; }

    public function resetFilters(): void
    {
        $this->search = $this->statusFilter = $this->payFilter = $this->dateFrom = $this->dateTo = '';
        $this->resetPage();
    }

    public function render()
    {
        $query = Booking::with(['trip', 'passenger.profile', 'payment'])
            ->when($this->search, function ($q) {
                $s = '%' . $this->search . '%';
                $q->where(function ($q2) use ($s) {
                    $q2->whereHas('passenger', fn($u) => $u->where('phone', 'like', $s)
                           ->orWhereHas('profile', fn($p) => $p->where('first_name', 'like', $s)->orWhere('last_name', 'like', $s)))
                       ->orWhereHas('trip', fn($t) => $t->where('departure_city', 'like', $s)->orWhere('arrival_city', 'like', $s));
                });
            })
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->payFilter,    fn($q) => $q->where('payment_status', $this->payFilter))
            ->when($this->dateFrom,     fn($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo,       fn($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->orderByDesc('created_at');

        $stats = [
            'total'    => Booking::count(),
            'pending'  => Booking::where('status', 'pending')->count(),
            'accepted' => Booking::where('status', 'accepted')->count(),
            'paid'     => Booking::where('payment_status', 'escrow_locked')->count(),
            'revenue'  => Booking::where('payment_status', 'escrow_locked')->sum('total_price'),
        ];

        $selected = $this->selectedId
            ? Booking::with(['trip.user.profile', 'passenger.profile', 'payment'])->find($this->selectedId)
            : null;

        return view('admin.reservations', [
            'bookings'  => $query->paginate(15),
            'stats'     => $stats,
            'selected'  => $selected,
        ])->layout('admin.layouts.app', ['title' => 'Réservations']);
    }
}
