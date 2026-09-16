<?php

namespace App\Livewire\Admin;

use App\Models\Payment;
use Livewire\Component;
use Livewire\WithPagination;

class Payments extends Component
{
    use WithPagination;

    public string $search        = '';
    public string $statusFilter  = '';
    public string $providerFilter = '';
    public string $dateFrom      = '';
    public string $dateTo        = '';

    public ?int $selectedPaymentId = null;

    public function updatingSearch(): void         { $this->resetPage(); }
    public function updatingStatusFilter(): void   { $this->resetPage(); }
    public function updatingProviderFilter(): void { $this->resetPage(); }
    public function updatingDateFrom(): void       { $this->resetPage(); }
    public function updatingDateTo(): void         { $this->resetPage(); }

    public function viewPayment(int $id): void
    {
        $this->selectedPaymentId = $id;
    }

    public function closeView(): void
    {
        $this->selectedPaymentId = null;
    }

    public function resetFilters(): void
    {
        $this->search         = '';
        $this->statusFilter   = '';
        $this->providerFilter = '';
        $this->dateFrom       = '';
        $this->dateTo         = '';
        $this->resetPage();
    }

    public function render()
    {
        $query = Payment::with(['user.profile', 'booking.trip', 'booking.passenger.profile'])
            ->when($this->search, function ($q) {
                $s = '%' . $this->search . '%';
                $q->where(function ($q2) use ($s) {
                    $q2->where('transaction_reference', 'like', $s)
                       ->orWhere('provider_reference', 'like', $s)
                       ->orWhere('phone_number', 'like', $s)
                       ->orWhereHas('user', fn($u) => $u->where('phone', 'like', $s)
                           ->orWhereHas('profile', fn($p) => $p->where('first_name', 'like', $s)
                               ->orWhere('last_name', 'like', $s)));
                });
            })
            ->when($this->statusFilter,   fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->providerFilter, fn($q) => $q->where('provider', $this->providerFilter))
            ->when($this->dateFrom, fn($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo,   fn($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->orderByDesc('created_at');

        $stats = [
            'total'        => Payment::count(),
            'success'      => Payment::where('status', 'success')->count(),
            'pending'      => Payment::where('status', 'pending')->count(),
            'failed'       => Payment::where('status', 'failed')->count(),
            'gross_total'  => Payment::where('status', 'success')->sum('gross_amount'),
            'commission'   => Payment::where('status', 'success')->sum('commission_amount'),
        ];

        $providers = Payment::select('provider')->distinct()->pluck('provider')->filter()->sort()->values();

        $selectedPayment = $this->selectedPaymentId
            ? Payment::with(['user.profile', 'booking.trip', 'booking.passenger.profile'])->find($this->selectedPaymentId)
            : null;

        return view('admin.payments', [
            'payments'        => $query->paginate(15),
            'stats'           => $stats,
            'providers'       => $providers,
            'selectedPayment' => $selectedPayment,
        ])->layout('admin.layouts.app', ['title' => 'Paiements']);
    }
}
