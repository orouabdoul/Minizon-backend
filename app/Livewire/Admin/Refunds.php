<?php

namespace App\Livewire\Admin;

use App\Models\Withdrawal;
use App\Models\Booking;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class Refunds extends Component
{
    use WithPagination;

    public string $search        = '';
    public string $statusFilter  = '';
    public string $methodFilter  = '';
    public string $dateFrom      = '';
    public string $dateTo        = '';
    public ?int   $selectedId    = null;

    public function updatingSearch(): void       { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }
    public function updatingMethodFilter(): void { $this->resetPage(); }
    public function updatingDateFrom(): void     { $this->resetPage(); }
    public function updatingDateTo(): void       { $this->resetPage(); }

    public function view(int $id): void  { $this->selectedId = $id; }
    public function closeView(): void    { $this->selectedId = null; }

    public function approve(int $id): void
    {
        Withdrawal::findOrFail($id)->update([
            'status'       => 'approved',
            'processed_at' => now(),
        ]);
    }

    public function reject(int $id): void
    {
        Withdrawal::findOrFail($id)->update(['status' => 'rejected']);
    }

    public function markFailed(int $id): void
    {
        Withdrawal::findOrFail($id)->update(['status' => 'failed']);
    }

    public function render()
    {
        $query = Withdrawal::with('user.profile')
            ->when($this->search, fn($q) => $q->where(function ($q2) {
                $s = '%' . $this->search . '%';
                $q2->where('reference', 'like', $s)
                   ->orWhere('phone_number', 'like', $s)
                   ->orWhereHas('user', fn($u) => $u->where('phone', 'like', $s)
                       ->orWhereHas('profile', fn($p) => $p->where('first_name', 'like', $s)
                           ->orWhere('last_name', 'like', $s)));
            }))
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->methodFilter, fn($q) => $q->where('provider', $this->methodFilter))
            ->when($this->dateFrom, fn($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo,   fn($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->orderByDesc('created_at');

        $stats = [
            'total'      => Withdrawal::count(),
            'pending'    => Withdrawal::where('status', 'pending')->count(),
            'approved'   => Withdrawal::where('status', 'approved')->count(),
            'rejected'   => Withdrawal::where('status', 'rejected')->count(),
            'failed'     => Withdrawal::where('status', 'failed')->count(),
            'total_paid' => (int) Withdrawal::where('status', 'approved')->sum('amount'),
            'total_pend' => (int) Withdrawal::where('status', 'pending')->sum('amount'),
            // Refunded bookings count for context
            'refunded_bookings' => Booking::where('payment_status', 'refunded')->count(),
        ];

        $selectedWithdrawal = $this->selectedId
            ? Withdrawal::with('user.profile')->find($this->selectedId)
            : null;

        return view('admin.refunds', [
            'withdrawals'        => $query->paginate(20),
            'stats'              => $stats,
            'selectedWithdrawal' => $selectedWithdrawal,
        ])->layout('admin.layouts.app', ['title' => 'Remboursements']);
    }
}
