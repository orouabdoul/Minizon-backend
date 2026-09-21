<?php

namespace App\Livewire\Admin;

use App\Models\User;
use App\Models\Booking;
use App\Models\Payment;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;

class Passengers extends Component
{
    use WithPagination;

    public string $search      = '';
    public string $kycFilter   = '';
    public string $blockFilter = '';
    public string $dateFrom    = '';
    public string $dateTo      = '';

    public ?int $selectedId = null;

    public function updatingSearch(): void      { $this->resetPage(); }
    public function updatingKycFilter(): void   { $this->resetPage(); }
    public function updatingBlockFilter(): void { $this->resetPage(); }
    public function updatingDateFrom(): void    { $this->resetPage(); }
    public function updatingDateTo(): void      { $this->resetPage(); }

    public function view(int $id): void  { $this->selectedId = $id; }
    public function closeView(): void    { $this->selectedId = null; }

    public function approveKyc(int $id): void
    {
        $user = User::find($id);
        if ($user) $user->profile?->update(['kyc_status' => 'approved', 'approved_at' => now()]);
    }

    public function rejectKyc(int $id): void
    {
        $user = User::find($id);
        if ($user) $user->profile?->update(['kyc_status' => 'rejected']);
    }

    public function toggleBlock(int $id): void
    {
        $user = User::find($id);
        if ($user) {
            $user->update(['is_blocked' => !$user->is_blocked]);
        }
    }

    public function resetFilters(): void
    {
        $this->search = $this->kycFilter = $this->blockFilter = $this->dateFrom = $this->dateTo = '';
        $this->resetPage();
    }

    public function render()
    {
        $query = User::with(['profile', 'bookings'])
            ->whereHas('role', fn($q) => $q->where('name', 'passenger'))
            ->when($this->search, function ($q) {
                $s = '%' . $this->search . '%';
                $q->where(function ($q2) use ($s) {
                    $q2->where('phone', 'like', $s)
                       ->orWhereHas('profile', fn($p) =>
                           $p->where('first_name', 'like', $s)
                             ->orWhere('last_name', 'like', $s)
                             ->orWhere('email', 'like', $s)
                             ->orWhere('city', 'like', $s)
                       );
                });
            })
            ->when($this->kycFilter, function ($q) {
                $q->whereHas('profile', fn($p) => $p->where('kyc_status', $this->kycFilter));
            })
            ->when($this->blockFilter !== '', function ($q) {
                $q->where('is_blocked', $this->blockFilter === '1');
            })
            ->when($this->dateFrom, fn($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo,   fn($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->orderByDesc('created_at');

        $stats = [
            'total'    => User::whereHas('role', fn($q) => $q->where('name', 'passenger'))->count(),
            'verified' => User::whereHas('role', fn($q) => $q->where('name', 'passenger'))
                              ->whereHas('profile', fn($p) => $p->where('kyc_status', 'approved'))->count(),
            'pending'  => User::whereHas('role', fn($q) => $q->where('name', 'passenger'))
                              ->whereHas('profile', fn($p) => $p->where('kyc_status', 'pending'))->count(),
            'blocked'  => User::whereHas('role', fn($q) => $q->where('name', 'passenger'))
                              ->where('is_blocked', true)->count(),
            'bookings' => Booking::count(),
        ];

        $selected = null;
        if ($this->selectedId) {
            $selected = User::with([
                'profile',
                'bookings.trip',
                'bookings.payment',
                'reviewsReceived' => fn($q) => $q->where('status', 'visible')->latest()->limit(5),
                'penalties',
                'emergencyContacts',
            ])->find($this->selectedId);
        }

        return view('admin.passengers', [
            'passengers' => $query->paginate(15),
            'stats'      => $stats,
            'selected'   => $selected,
        ])->layout('admin.layouts.app', ['title' => 'Passagers']);
    }
}
