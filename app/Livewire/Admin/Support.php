<?php

namespace App\Livewire\Admin;

use App\Models\SupportTicket;
use Livewire\Component;
use Livewire\WithPagination;

class Support extends Component
{
    use WithPagination;

    public string $search          = '';
    public string $statusFilter    = '';
    public string $priorityFilter  = '';

    public ?int $selectedTicketId = null;

    public function updatingSearch(): void         { $this->resetPage(); }
    public function updatingStatusFilter(): void   { $this->resetPage(); }
    public function updatingPriorityFilter(): void { $this->resetPage(); }

    public function viewTicket(int $id): void
    {
        $this->selectedTicketId = $id;
    }

    public function closeView(): void
    {
        $this->selectedTicketId = null;
    }

    public function updateStatus(int $id, string $status): void
    {
        $ticket = SupportTicket::findOrFail($id);
        $data = ['status' => $status];
        if (in_array($status, ['resolved', 'closed'])) {
            $data['resolved_at'] = now();
        }
        $ticket->update($data);
        if ($this->selectedTicketId === $id) {
            $this->closeView();
        }
    }

    public function render()
    {
        $query = SupportTicket::with(['user.profile'])
            ->when($this->search, function ($q) {
                $s = '%' . $this->search . '%';
                $q->where('subject', 'like', $s)
                  ->orWhere('description', 'like', $s)
                  ->orWhereHas('user', fn($u) => $u->where('phone', 'like', $s)
                      ->orWhereHas('profile', fn($p) => $p->where('first_name', 'like', $s)->orWhere('last_name', 'like', $s)));
            })
            ->when($this->statusFilter,   fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->priorityFilter, fn($q) => $q->where('priority', $this->priorityFilter))
            ->orderByRaw("CASE WHEN priority='urgent' THEN 0 WHEN priority='high' THEN 1 WHEN priority='medium' THEN 2 ELSE 3 END")
            ->orderByDesc('created_at');

        $stats = [
            'total'       => SupportTicket::count(),
            'new'         => SupportTicket::where('status', 'new')->count(),
            'in_progress' => SupportTicket::where('status', 'in_progress')->count(),
            'resolved'    => SupportTicket::whereIn('status', ['resolved', 'closed'])->count(),
            'urgent'      => SupportTicket::where('priority', 'urgent')->whereNotIn('status', ['resolved', 'closed'])->count(),
        ];

        $selectedTicket = $this->selectedTicketId
            ? SupportTicket::with(['user.profile'])->find($this->selectedTicketId)
            : null;

        return view('admin.support', [
            'tickets'        => $query->paginate(15),
            'stats'          => $stats,
            'selectedTicket' => $selectedTicket,
        ])->layout('admin.layouts.app', ['title' => 'Support']);
    }
}
