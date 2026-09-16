<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class Users extends Component
{
    use WithPagination;

    public string $search     = '';
    public string $tab        = 'all';
    public string $kycFilter  = '';
    public string $statFilter = '';

    public function updatingSearch(): void    { $this->resetPage(); }
    public function updatingTab(): void       { $this->resetPage(); }
    public function updatingKycFilter(): void  { $this->resetPage(); }
    public function updatingStatFilter(): void { $this->resetPage(); }

    public function toggleBlock(int $userId): void
    {
        $user = User::findOrFail($userId);
        $user->update(['is_blocked' => ! $user->is_blocked]);
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

        return view('admin.users', [
            'users'          => $query->paginate(15),
            'stats'          => $stats,
            'driverRoleId'   => $driverRoleId,
        ])->layout('admin.layouts.app', ['title' => 'Utilisateurs']);
    }
}
