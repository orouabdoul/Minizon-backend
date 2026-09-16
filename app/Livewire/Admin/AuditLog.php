<?php

namespace App\Livewire\Admin;

use App\Models\AuditLog as AuditLogModel;
use Livewire\Component;
use Livewire\WithPagination;

class AuditLog extends Component
{
    use WithPagination;

    public string $search        = '';
    public string $severityFilter = '';
    public string $typeFilter    = '';
    public string $dateFrom      = '';
    public string $dateTo        = '';
    public ?int   $selectedId    = null;

    public function updatingSearch(): void         { $this->resetPage(); }
    public function updatingSeverityFilter(): void { $this->resetPage(); }
    public function updatingTypeFilter(): void     { $this->resetPage(); }
    public function updatingDateFrom(): void       { $this->resetPage(); }
    public function updatingDateTo(): void         { $this->resetPage(); }

    public function view(int $id): void   { $this->selectedId = $id; }
    public function closeView(): void     { $this->selectedId = null; }

    public function render()
    {
        $query = AuditLogModel::with('user.profile')
            ->when($this->search, fn($q) => $q->where(function ($q2) {
                $s = '%' . $this->search . '%';
                $q2->where('action', 'like', $s)
                   ->orWhere('description', 'like', $s)
                   ->orWhere('ip_address', 'like', $s)
                   ->orWhere('target_name', 'like', $s);
            }))
            ->when($this->severityFilter, fn($q) => $q->where('severity', $this->severityFilter))
            ->when($this->typeFilter,     fn($q) => $q->where('action_type', $this->typeFilter))
            ->when($this->dateFrom,       fn($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo,         fn($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->orderByDesc('created_at');

        $stats = [
            'total'    => AuditLogModel::count(),
            'critical' => AuditLogModel::where('severity', 'critical')->count(),
            'warning'  => AuditLogModel::where('severity', 'warning')->count(),
            'today'    => AuditLogModel::whereDate('created_at', today())->count(),
        ];

        $actionTypes = AuditLogModel::distinct()->whereNotNull('action_type')
            ->pluck('action_type')->sort()->values();

        $selectedLog = $this->selectedId
            ? AuditLogModel::with('user.profile')->find($this->selectedId)
            : null;

        return view('admin.audit', [
            'logs'        => $query->paginate(25),
            'stats'       => $stats,
            'actionTypes' => $actionTypes,
            'selectedLog' => $selectedLog,
        ])->layout('admin.layouts.app', ['title' => "Journal d'Audit"]);
    }
}
