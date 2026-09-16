<?php

namespace App\Livewire\Admin;

use App\Models\Review;
use Livewire\Component;
use Livewire\WithPagination;

class Reviews extends Component
{
    use WithPagination;

    public string $search       = '';
    public string $ratingFilter = '';
    public string $statusFilter = '';
    public ?int   $selectedId   = null;

    public function updatingSearch(): void       { $this->resetPage(); }
    public function updatingRatingFilter(): void { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }

    public function view(int $id): void  { $this->selectedId = $id; }
    public function closeView(): void    { $this->selectedId = null; }

    public function approve(int $id): void
    {
        Review::findOrFail($id)->update(['status' => 'approved']);
    }

    public function hide(int $id): void
    {
        Review::findOrFail($id)->update(['status' => 'hidden']);
    }

    public function delete(int $id): void
    {
        Review::findOrFail($id)->delete();
        $this->selectedId = null;
    }

    public function render()
    {
        $query = Review::with(['reviewer.profile', 'reviewee.profile', 'trip'])
            ->when($this->search, fn($q) => $q->where(function ($q2) {
                $s = '%' . $this->search . '%';
                $q2->where('comment', 'like', $s)
                   ->orWhereHas('reviewer.profile', fn($p) => $p->where('first_name', 'like', $s)
                       ->orWhere('last_name', 'like', $s))
                   ->orWhereHas('reviewee.profile', fn($p) => $p->where('first_name', 'like', $s)
                       ->orWhere('last_name', 'like', $s));
            }))
            ->when($this->ratingFilter, fn($q) => $q->where('rating', $this->ratingFilter))
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('created_at');

        $stats = [
            'total'    => Review::count(),
            'avg'      => round((float) Review::avg('rating'), 1),
            'pending'  => Review::where('status', 'pending')->count(),
            'hidden'   => Review::where('status', 'hidden')->count(),
            'flagged'  => Review::where('report_count', '>', 0)->count(),
            'five_star'=> Review::where('rating', 5)->count(),
            'one_star' => Review::where('rating', 1)->count(),
        ];

        $selectedReview = $this->selectedId
            ? Review::with(['reviewer.profile', 'reviewee.profile', 'trip'])->find($this->selectedId)
            : null;

        return view('admin.reviews', [
            'reviews'        => $query->paginate(20),
            'stats'          => $stats,
            'selectedReview' => $selectedReview,
        ])->layout('admin.layouts.app', ['title' => 'Évaluations']);
    }
}
