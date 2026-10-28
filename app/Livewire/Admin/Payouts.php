<?php

namespace App\Livewire\Admin;

use App\Models\AuditLog;
use App\Models\DriverPayout;
use App\Notifications\PayoutPaid;
use App\Traits\WithConfirmModal;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithPagination;

class Payouts extends Component
{
    use WithPagination, WithConfirmModal;

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

    public function process(int $id): void
    {
        $p = DriverPayout::findOrFail($id);
        if ($p->status !== 'en_attente') { return; }

        $p->update([
            'status'       => 'en_traitement',
            'admin_id'     => auth()->id(),
            'processed_at' => now(),
        ]);

        AuditLog::record(
            action:      'payout.process',
            userId:      auth()->id(),
            actionType:  'remboursement',
            severity:    'info',
            description: "Virement {$p->reference} déclenché — " . number_format($p->net_amount) . ' FCFA',
            targetType:  'payout',
            targetName:  $p->reference,
        );
    }

    public function markPaid(int $id): void
    {
        $p = DriverPayout::with('driver')->findOrFail($id);
        if ($p->status !== 'en_traitement') { return; }

        $p->update(['status' => 'payé', 'paid_at' => now()]);

        try { if ($p->driver) $p->driver->notify(new PayoutPaid($p)); }
        catch (\Throwable $e) { Log::warning('Payouts.markPaid: notif échouée', ['err' => $e->getMessage()]); }

        AuditLog::record(
            action:      'payout.paid',
            userId:      auth()->id(),
            actionType:  'remboursement',
            severity:    'info',
            description: "Virement {$p->reference} confirmé payé — " . number_format($p->net_amount) . ' FCFA',
            targetType:  'payout',
            targetName:  $p->reference,
        );
    }

    public function retry(int $id): void
    {
        $p = DriverPayout::findOrFail($id);
        if ($p->status !== 'échoué') { return; }

        $p->update(['status' => 'en_attente', 'failed_reason' => null, 'processed_at' => null]);

        AuditLog::record(
            action:      'payout.retry',
            userId:      auth()->id(),
            actionType:  'remboursement',
            severity:    'info',
            description: "Virement {$p->reference} remis en attente",
            targetType:  'payout',
            targetName:  $p->reference,
        );
    }

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
