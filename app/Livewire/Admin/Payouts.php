<?php

namespace App\Livewire\Admin;

use App\Models\DriverPayout;
use Livewire\Component;
use Livewire\WithPagination;

class Payouts extends Component
{
    use WithPagination;

    public string $search       = '';
    public string $statusFilter = '';
    public string $methodFilter = '';
    public string $dateFrom     = '';
    public string $dateTo       = '';

    public ?int $selectedId = null;

    public function updatingSearch():       void { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }
    public function updatingMethodFilter(): void { $this->resetPage(); }
    public function updatingDateFrom():     void { $this->resetPage(); }
    public function updatingDateTo():       void { $this->resetPage(); }

    public function view(int $id): void    { $this->selectedId = $id; }
    public function closeView(): void      { $this->selectedId = null; }

    public function resetFilters(): void
    {
        $this->search = $this->statusFilter = $this->methodFilter = $this->dateFrom = $this->dateTo = '';
        $this->resetPage();
    }

    public function render()
    {
        $query = DriverPayout::with(['driver.profile'])
            ->when($this->search, function ($q) {
                $s = '%' . $this->search . '%';
                $q->where(function ($q2) use ($s) {
                    $q2->where('reference', 'like', $s)
                       ->orWhere('phone_number', 'like', $s)
                       ->orWhereHas('driver', fn($d) =>
                           $d->where('phone', 'like', $s)
                             ->orWhereHas('profile', fn($p) =>
                                 $p->where('first_name', 'like', $s)
                                   ->orWhere('last_name', 'like', $s)
                             )
                       );
                });
            })
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->methodFilter, fn($q) => $q->where('method', $this->methodFilter))
            ->when($this->dateFrom,     fn($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo,       fn($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->orderByDesc('created_at');

        $stats = [
            'total'       => DriverPayout::count(),
            'pending'     => DriverPayout::where('status', 'en_attente')->count(),
            'processing'  => DriverPayout::where('status', 'en_traitement')->count(),
            'paid'        => DriverPayout::where('status', 'payé')->count(),
            'failed'      => DriverPayout::where('status', 'échoué')->count(),
            'total_paid'  => DriverPayout::where('status', 'payé')->sum('net_amount'),
            'total_pending'=> DriverPayout::whereIn('status', ['en_attente', 'en_traitement'])->sum('net_amount'),
        ];

        $selected = null;
        if ($this->selectedId) {
            $selected = DriverPayout::with(['driver.profile'])->find($this->selectedId);
        }

        return view('admin.payouts', [
            'payouts'  => $query->paginate(15),
            'stats'    => $stats,
            'selected' => $selected,
        ])->layout('admin.layouts.app', ['title' => 'Virements conducteurs']);
    }
}
