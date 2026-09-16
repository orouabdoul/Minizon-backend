<?php

namespace App\Livewire\Admin;

use App\Models\Dispute;
use Livewire\Component;
use Livewire\WithPagination;

class Disputes extends Component
{
    use WithPagination;

    public string $search       = '';
    public string $statusFilter = '';
    public string $reasonFilter = '';

    public ?int $selectedDisputeId = null;
    public string $decisionNotes   = '';
    public string $resolveAs       = '';

    public function updatingSearch(): void       { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }
    public function updatingReasonFilter(): void { $this->resetPage(); }

    public function viewDispute(int $id): void
    {
        $this->selectedDisputeId = $id;
        $dispute = Dispute::find($id);
        $this->decisionNotes = $dispute?->admin_decision_notes ?? '';
        $this->resolveAs = '';
    }

    public function closeView(): void
    {
        $this->selectedDisputeId = null;
        $this->decisionNotes = '';
        $this->resolveAs = '';
    }

    public function setStatus(int $id, string $status): void
    {
        $dispute = Dispute::findOrFail($id);
        $data = ['status' => $status];
        if (in_array($status, ['resolved_reporter', 'resolved_respondent', 'closed'])) {
            $data['resolved_at'] = now();
        }
        if ($this->decisionNotes) {
            $data['admin_decision_notes'] = $this->decisionNotes;
        }
        $dispute->update($data);

        if ($this->selectedDisputeId === $id) {
            $this->closeView();
        }
    }

    public function saveNotes(): void
    {
        if ($this->selectedDisputeId) {
            Dispute::findOrFail($this->selectedDisputeId)
                ->update(['admin_decision_notes' => $this->decisionNotes]);
        }
    }

    public function render()
    {
        $query = Dispute::with(['reporter.profile', 'booking.trip', 'booking.passenger.profile'])
            ->when($this->search, function ($q) {
                $s = '%' . $this->search . '%';
                $q->where('description', 'like', $s)
                  ->orWhereHas('reporter', fn($u) => $u->where('phone', 'like', $s)
                      ->orWhereHas('profile', fn($p) => $p->where('first_name', 'like', $s)->orWhere('last_name', 'like', $s)));
            })
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->reasonFilter, fn($q) => $q->where('reason_type', $this->reasonFilter))
            ->orderByDesc('created_at');

        $stats = [
            'total'         => Dispute::count(),
            'pending'       => Dispute::where('status', 'pending')->count(),
            'investigating' => Dispute::where('status', 'investigating')->count(),
            'resolved'      => Dispute::whereIn('status', ['resolved_reporter', 'resolved_respondent', 'closed'])->count(),
        ];

        $reasons = Dispute::select('reason_type')->distinct()->pluck('reason_type')->filter()->sort()->values();

        $selectedDispute = $this->selectedDisputeId
            ? Dispute::with(['reporter.profile', 'booking.trip', 'booking.passenger.profile'])->find($this->selectedDisputeId)
            : null;

        return view('admin.disputes', [
            'disputes'        => $query->paginate(15),
            'stats'           => $stats,
            'reasons'         => $reasons,
            'selectedDispute' => $selectedDispute,
        ])->layout('admin.layouts.app', ['title' => 'Litiges']);
    }
}
