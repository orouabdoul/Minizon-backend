<?php

namespace App\Livewire\Admin;

use App\Models\AdminNotification;
use Livewire\Component;
use Livewire\WithPagination;

class Notifications extends Component
{
    use WithPagination;

    public string $typeFilter     = '';
    public string $priorityFilter = '';
    public string $statusFilter   = '';

    public function updatingTypeFilter(): void     { $this->resetPage(); }
    public function updatingPriorityFilter(): void { $this->resetPage(); }
    public function updatingStatusFilter(): void   { $this->resetPage(); }

    public function markRead(int $id): void
    {
        AdminNotification::findOrFail($id)->update([
            'status'  => 'read',
            'read_at' => now(),
        ]);
    }

    public function markHandled(int $id): void
    {
        AdminNotification::findOrFail($id)->update([
            'status'     => 'handled',
            'handled_at' => now(),
            'read_at'    => now(),
        ]);
    }

    public function markAllRead(): void
    {
        AdminNotification::where('status', 'unread')
            ->update(['status' => 'read', 'read_at' => now()]);
    }

    public function delete(int $id): void
    {
        AdminNotification::findOrFail($id)->delete();
    }

    public function render()
    {
        $query = AdminNotification::with('user.profile')
            ->when($this->typeFilter,     fn($q) => $q->where('type', $this->typeFilter))
            ->when($this->priorityFilter, fn($q) => $q->where('priority', $this->priorityFilter))
            ->when($this->statusFilter,   fn($q) => $q->where('status', $this->statusFilter))
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END")
            ->orderByDesc('created_at');

        $stats = [
            'unread'  => AdminNotification::where('status', 'unread')->count(),
            'urgent'  => AdminNotification::where('priority', 'urgent')->where('status', 'unread')->count(),
            'high'    => AdminNotification::where('priority', 'high')->where('status', 'unread')->count(),
            'handled' => AdminNotification::where('status', 'handled')->count(),
        ];

        return view('admin.notifications', [
            'notifications' => $query->paginate(20),
            'stats'         => $stats,
        ])->layout('admin.layouts.app', ['title' => 'Notifications']);
    }
}
