<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithPagination;

class Users extends Component
{
    use WithPagination;

    public string $search     = '';
    public string $tab        = 'all';
    public string $kycFilter  = '';
    public string $statFilter = '';

    public ?int $selectedUserId = null;

    public function updatingSearch(): void    { $this->resetPage(); }
    public function updatingTab(): void       { $this->resetPage(); }
    public function updatingKycFilter(): void  { $this->resetPage(); }
    public function updatingStatFilter(): void { $this->resetPage(); }

    public function viewUser(int $id): void
    {
        $this->selectedUserId = $id;
    }

    public function closeView(): void
    {
        $this->selectedUserId = null;
    }

    public function approveKyc(int $userId): void
    {
        $user = User::findOrFail($userId);
        $user->profile?->update(['kyc_status' => 'approved', 'approved_at' => now()]);
    }

    public function rejectKyc(int $userId): void
    {
        $user = User::findOrFail($userId);
        $user->profile?->update(['kyc_status' => 'rejected']);
    }

    public function toggleBlock(int $userId): void
    {
        $user = User::findOrFail($userId);
        $user->update(['is_blocked' => ! $user->is_blocked]);
    }

    public function deleteUser(int $userId): void
    {
        $user = User::findOrFail($userId);
        // Close panel if deleting the viewed user
        if ($this->selectedUserId === $userId) {
            $this->selectedUserId = null;
        }
        $user->delete();
    }

    public function render()
    {
        $driverRoleId    = DB::table('roles')->where('name', 'driver')->value('id');
        $passengerRoleId = DB::table('roles')->where('name', 'passenger')->value('id');
        $roleIds         = array_filter([$driverRoleId, $passengerRoleId]);

        $query = User::with(['profile', 'role'])
            ->when($this->tab === 'driver',    fn($q) => $q->where('role_id', $driverRoleId))
            ->when($this->tab === 'passenger', fn($q) => $q->where('role_id', $passengerRoleId))
            ->when($this->tab === 'all',       fn($q) => $q->whereIn('role_id', $roleIds))
            ->when($this->search, fn($q) => $q->where(function ($q2) {
                $s = '%' . $this->search . '%';
                $q2->where('phone', 'like', $s)
                   ->orWhereHas('profile', fn($p) => $p->where('first_name', 'like', $s)
                       ->orWhere('last_name', 'like', $s)
                       ->orWhere('email', 'like', $s));
            }))
            ->when($this->kycFilter, fn($q) => $q->whereHas('profile', fn($p) => $p->where('kyc_status', $this->kycFilter)))
            ->when($this->statFilter === 'active',  fn($q) => $q->where('is_blocked', false))
            ->when($this->statFilter === 'blocked', fn($q) => $q->where('is_blocked', true))
            ->orderByDesc('created_at');

        $stats = [
            'total'      => User::whereIn('role_id', $roleIds)->count(),
            'drivers'    => User::where('role_id', $driverRoleId)->count(),
            'passengers' => User::where('role_id', $passengerRoleId)->count(),
            'blocked'    => User::whereIn('role_id', $roleIds)->where('is_blocked', true)->count(),
        ];

        // Load selected user with full relations for the panel
        $selectedUser = $this->selectedUserId
            ? User::with(['profile', 'role', 'vehicle.vehicleType'])->find($this->selectedUserId)
            : null;

        return view('admin.users', [
            'users'        => $query->paginate(15),
            'stats'        => $stats,
            'driverRoleId' => $driverRoleId,
            'selectedUser' => $selectedUser,
        ])->layout('admin.layouts.app', ['title' => 'Utilisateurs']);
    }

    // Helper accessible from the view via @php
    public static function storageUrl(?string $path): ?string
    {
        if (!$path) return null;
        if (str_starts_with($path, 'http')) return $path;
        return Storage::disk('public')->exists($path) ? Storage::disk('public')->url($path) : null;
    }
}
