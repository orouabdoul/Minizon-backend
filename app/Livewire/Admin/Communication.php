<?php

namespace App\Livewire\Admin;

use App\Models\Conversation;
use App\Models\Message;
use Livewire\Component;
use Livewire\WithPagination;

class Communication extends Component
{
    use WithPagination;

    public string $search        = '';
    public string $typeFilter    = '';
    public ?int   $selectedId    = null;

    public function updatingSearch(): void     { $this->resetPage(); }
    public function updatingTypeFilter(): void { $this->resetPage(); }

    public function view(int $id): void   { $this->selectedId = $id; }
    public function closeView(): void     { $this->selectedId = null; }

    public function render()
    {
        $query = Conversation::with(['participants.profile', 'trip', 'messages' => fn($q) => $q->latest()->limit(1)])
            ->when($this->search, fn($q) => $q->whereHas('participants', function ($q2) {
                $s = '%' . $this->search . '%';
                $q2->where('phone', 'like', $s)
                   ->orWhereHas('profile', fn($p) => $p->where('first_name', 'like', $s)
                       ->orWhere('last_name', 'like', $s));
            }))
            ->when($this->typeFilter, fn($q) => $q->where('type', $this->typeFilter))
            ->withCount('messages')
            ->orderByDesc('updated_at');

        $stats = [
            'total'    => Conversation::count(),
            'messages' => Message::count(),
            'today'    => Conversation::whereDate('created_at', today())->count(),
            'flagged'  => Message::whereNotNull('deleted_at')->withTrashed()->count(),
        ];

        $selectedConv = $this->selectedId
            ? Conversation::with([
                'participants.profile',
                'trip',
                'messages.sender.profile',
            ])->find($this->selectedId)
            : null;

        return view('admin.communication', [
            'conversations' => $query->paginate(20),
            'stats'         => $stats,
            'selectedConv'  => $selectedConv,
        ])->layout('admin.layouts.app', ['title' => 'Communication']);
    }
}
