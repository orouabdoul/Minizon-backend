<?php

namespace App\Livewire\Admin;

use App\Models\Vehicle;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Storage;

class Vehicles extends Component
{
    use WithPagination;

    public string $search        = '';
    public string $statusFilter  = '';
    public string $typeFilter    = '';

    public ?int $selectedId      = null;
    public string $rejectReason  = '';
    public bool $showRejectForm  = false;

    public function updatingSearch():       void { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }
    public function updatingTypeFilter():   void { $this->resetPage(); }

    public function view(int $id): void
    {
        $this->selectedId    = $id;
        $this->rejectReason  = '';
        $this->showRejectForm = false;
    }

    public function closeView(): void { $this->selectedId = null; }

    public function approve(int $id): void
    {
        Vehicle::where('id', $id)->update([
            'verification_status' => 'approved',
            'is_approved'         => true,
            'verified_at'         => now(),
        ]);
        $this->selectedId = null;
    }

    public function openRejectForm(): void  { $this->showRejectForm = true; }

    public function reject(int $id): void
    {
        Vehicle::where('id', $id)->update([
            'verification_status' => 'rejected',
            'is_approved'         => false,
            'rejection_reason'    => $this->rejectReason ?: null,
        ]);
        $this->selectedId     = null;
        $this->showRejectForm = false;
        $this->rejectReason   = '';
    }

    public function suspend(int $id): void
    {
        Vehicle::where('id', $id)->update([
            'verification_status' => 'suspended',
            'is_approved'         => false,
        ]);
        $this->selectedId = null;
    }

    public function resetFilters(): void
    {
        $this->search = $this->statusFilter = $this->typeFilter = '';
        $this->resetPage();
    }

    public function render()
    {
        $query = Vehicle::with(['user.profile', 'vehicleType'])
            ->when($this->search, function ($q) {
                $s = '%' . $this->search . '%';
                $q->where(function ($q2) use ($s) {
                    $q2->where('brand', 'like', $s)
                       ->orWhere('model', 'like', $s)
                       ->orWhere('license_plate', 'like', $s)
                       ->orWhere('color', 'like', $s)
                       ->orWhereHas('user', fn($u) =>
                           $u->where('phone', 'like', $s)
                             ->orWhereHas('profile', fn($p) =>
                                 $p->where('first_name', 'like', $s)
                                   ->orWhere('last_name', 'like', $s)
                             )
                       );
                });
            })
            ->when($this->statusFilter, fn($q) => $q->where('verification_status', $this->statusFilter))
            ->when($this->typeFilter,   fn($q) => $q->where('vehicle_type_id', $this->typeFilter))
            ->orderByDesc('created_at');

        $types = \App\Models\VehicleType::orderBy('name')->get();

        $stats = [
            'total'    => Vehicle::count(),
            'pending'  => Vehicle::where('verification_status', 'pending')->count(),
            'approved' => Vehicle::where('verification_status', 'approved')->count(),
            'rejected' => Vehicle::where('verification_status', 'rejected')->count(),
            'suspended'=> Vehicle::where('verification_status', 'suspended')->count(),
        ];

        $selected = null;
        if ($this->selectedId) {
            $selected = Vehicle::with(['user.profile', 'vehicleType', 'trips' => fn($q) => $q->latest()->limit(5)])
                ->find($this->selectedId);
        }

        return view('admin.vehicles', [
            'vehicles' => $query->paginate(15),
            'stats'    => $stats,
            'types'    => $types,
            'selected' => $selected,
        ])->layout('admin.layouts.app', ['title' => 'Véhicules']);
    }
}
