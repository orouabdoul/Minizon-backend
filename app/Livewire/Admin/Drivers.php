<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class Drivers extends Component
{
    use WithPagination;

    public string $search       = '';
    public string $kycFilter    = '';
    public string $vehicleFilter = '';

    public function updatingSearch(): void        { $this->resetPage(); }
    public function updatingKycFilter(): void     { $this->resetPage(); }
    public function updatingVehicleFilter(): void { $this->resetPage(); }

    public function approveKyc(int $userId): void
    {
        $user = User::findOrFail($userId);
        $user->profile?->update([
            'kyc_status'  => 'approved',
            'approved_at' => now(),
        ]);
    }

    public function rejectKyc(int $userId): void
    {
        $user = User::findOrFail($userId);
        $user->profile?->update(['kyc_status' => 'rejected']);
    }

    public function approveVehicle(int $userId): void
    {
        $user = User::findOrFail($userId);
        $user->vehicle?->update([
            'verification_status' => 'approved',
            'is_approved'         => true,
            'verified_at'         => now(),
        ]);
    }

    public function rejectVehicle(int $userId): void
    {
        $user = User::findOrFail($userId);
        $user->vehicle?->update([
            'verification_status' => 'rejected',
            'is_approved'         => false,
        ]);
    }

    public function render()
    {
        $driverRoleId = DB::table('roles')->where('name', 'driver')->value('id');

        $query = User::with(['profile', 'vehicle.vehicleType'])
            ->where('role_id', $driverRoleId)
            ->when($this->search, fn($q) => $q->where(function ($q2) {
                $s = '%' . $this->search . '%';
                $q2->where('phone', 'like', $s)
                   ->orWhereHas('profile', fn($p) => $p->where('first_name', 'like', $s)
                       ->orWhere('last_name', 'like', $s));
            }))
            ->when($this->kycFilter, fn($q) => $q->whereHas('profile', fn($p) => $p->where('kyc_status', $this->kycFilter)))
            ->when($this->vehicleFilter, fn($q) => $q->whereHas('vehicle', fn($v) => $v->where('verification_status', $this->vehicleFilter)))
            ->orderByDesc('created_at');

        $stats = [
            'total'    => User::where('role_id', $driverRoleId)->count(),
            'pending'  => User::where('role_id', $driverRoleId)->whereHas('profile', fn($p) => $p->where('kyc_status', 'pending'))->count(),
            'approved' => User::where('role_id', $driverRoleId)->whereHas('profile', fn($p) => $p->where('kyc_status', 'approved'))->count(),
            'rejected' => User::where('role_id', $driverRoleId)->whereHas('profile', fn($p) => $p->where('kyc_status', 'rejected'))->count(),
        ];

        return view('admin.drivers', [
            'drivers' => $query->paginate(15),
            'stats'   => $stats,
        ])->layout('admin.layouts.app', ['title' => 'Conducteurs']);
    }
}
