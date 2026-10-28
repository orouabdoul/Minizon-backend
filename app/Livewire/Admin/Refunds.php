<?php

namespace App\Livewire\Admin;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Withdrawal;
use App\Notifications\WithdrawalProcessed;
use App\Traits\WithConfirmModal;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithPagination;

class Refunds extends Component
{
    use WithPagination, WithConfirmModal;

    public string $search        = '';
    public string $statusFilter  = '';
    public string $methodFilter  = '';
    public string $dateFrom      = '';
    public string $dateTo        = '';
    public ?int   $selectedId    = null;
    public string $rejectReason  = '';
    public bool   $showRejectForm = false;

    public function updatingSearch(): void       { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }
    public function updatingMethodFilter(): void { $this->resetPage(); }
    public function updatingDateFrom(): void     { $this->resetPage(); }
    public function updatingDateTo(): void       { $this->resetPage(); }

    public function view(int $id): void
    {
        $this->selectedId    = $id;
        $this->rejectReason  = '';
        $this->showRejectForm = false;
    }

    public function closeView(): void
    {
        $this->selectedId    = null;
        $this->showRejectForm = false;
        $this->rejectReason  = '';
    }

    public function openRejectForm(): void  { $this->showRejectForm = true; }
    public function closeRejectForm(): void { $this->showRejectForm = false; $this->rejectReason = ''; }

    public function approve(int $id): void
    {
        $w = Withdrawal::with('user')->findOrFail($id);

        if (! $w->isPending()) { return; }

        $w->update(['status' => 'approved', 'processed_at' => now()]);

        try { $w->user?->notify(new WithdrawalProcessed($w)); }
        catch (\Throwable $e) { Log::warning('Refunds.approve: notif échouée', ['err' => $e->getMessage()]); }

        AuditLog::record(
            action:      'withdrawal.approved',
            userId:      auth()->id(),
            actionType:  'remboursement',
            severity:    'info',
            description: "Retrait {$w->reference} approuvé — " . number_format($w->amount) . ' FCFA',
            targetType:  'withdrawal',
            targetName:  $w->reference,
        );
    }

    public function reject(int $id): void
    {
        $w = Withdrawal::with('user')->findOrFail($id);

        if (! $w->isPending()) { $this->showRejectForm = false; return; }

        $w->update([
            'status'        => 'rejected',
            'failed_reason' => $this->rejectReason ?: null,
            'processed_at'  => now(),
        ]);

        $this->showRejectForm = false;
        $this->rejectReason   = '';

        try { $w->user?->notify(new WithdrawalProcessed($w)); }
        catch (\Throwable $e) { Log::warning('Refunds.reject: notif échouée', ['err' => $e->getMessage()]); }

        AuditLog::record(
            action:      'withdrawal.rejected',
            userId:      auth()->id(),
            actionType:  'remboursement',
            severity:    'warning',
            description: "Retrait {$w->reference} refusé — " . number_format($w->amount) . ' FCFA',
            targetType:  'withdrawal',
            targetName:  $w->reference,
        );
    }

    public function markFailed(int $id): void
    {
        $w = Withdrawal::findOrFail($id);

        if (! in_array($w->status, ['pending', 'approved'])) { return; }

        $w->update(['status' => 'failed', 'processed_at' => $w->processed_at ?? now()]);

        AuditLog::record(
            action:      'withdrawal.failed',
            userId:      auth()->id(),
            actionType:  'remboursement',
            severity:    'warning',
            description: "Retrait {$w->reference} marqué échoué — " . number_format($w->amount) . ' FCFA',
            targetType:  'withdrawal',
            targetName:  $w->reference,
        );
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
