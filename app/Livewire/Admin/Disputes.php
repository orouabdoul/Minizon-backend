<?php

namespace App\Livewire\Admin;

use App\Models\AuditLog;
use App\Models\Dispute;
use App\Models\TripValidation;
use App\Notifications\DisputeResolvedForUser;
use App\Notifications\DisputeStatusChanged;
use App\Traits\WithConfirmModal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithPagination;

class Disputes extends Component
{
    use WithPagination, WithConfirmModal;

    public string $search       = '';
    public string $statusFilter = '';
    public string $reasonFilter = '';

    public ?int $selectedDisputeId = null;
    public string $decisionNotes   = '';

    public function updatingSearch(): void       { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }
    public function updatingReasonFilter(): void { $this->resetPage(); }

    public function viewDispute(int $id): void
    {
        $this->selectedDisputeId = $id;
        $dispute = Dispute::find($id);
        $this->decisionNotes = $dispute?->admin_decision_notes ?? '';
    }

    public function closeView(): void
    {
        $this->selectedDisputeId = null;
        $this->decisionNotes = '';
    }

    // ── Prise en charge (pending → investigating) ────────────────────────────
    public function takeCharge(int $id): void
    {
        $dispute = Dispute::with(['reporter'])->findOrFail($id);

        if (! $dispute->isPending()) {
            return;
        }

        $dispute->update([
            'status'            => 'investigating',
            'assigned_admin_id' => auth()->id(),
        ]);

        try {
            $dispute->fresh(['booking.trip.user', 'reporter'])
                ->reporter
                ->notify(new DisputeStatusChanged($dispute->fresh()));
        } catch (\Throwable) {}
    }

    public function saveNotes(): void
    {
        if ($this->selectedDisputeId) {
            Dispute::findOrFail($this->selectedDisputeId)
                ->update(['admin_decision_notes' => $this->decisionNotes]);
        }
    }

    // ── Décision : faveur requérant → rembourser le passager ────────────────
    public function resolveForReporter(int $id): void
    {
        $dispute = Dispute::with(['booking.payment', 'reporter'])->findOrFail($id);

        if ($dispute->isResolved()) {
            session()->flash('error', 'Ce litige est déjà résolu.');
            return;
        }

        $payment = $dispute->booking?->payment;

        // Vérifier que le paiement est encore remboursable
        if ($payment && ! in_array($payment->status, ['locked', 'pending'], true)) {
            session()->flash('error', 'Ce paiement ne peut plus être remboursé (statut : ' . $payment->status . ').');
            return;
        }

        // ── FedaPay AVANT toute mise à jour DB ───────────────────────────────
        if ($payment?->provider_reference) {
            $baseUrl = config('fedapay.environment') === 'live'
                ? 'https://api.fedapay.com/v1'
                : 'https://sandbox-api.fedapay.com/v1';

            try {
                $fedaResponse = Http::withToken(config('fedapay.secret_key'))
                    ->post("{$baseUrl}/transactions/{$payment->provider_reference}/refund");

                if (! $fedaResponse->successful()) {
                    $msg = $fedaResponse->json('message') ?? $fedaResponse->json('error') ?? 'Erreur FedaPay';
                    Log::error('Livewire Disputes resolveForReporter: FedaPay échoué', [
                        'dispute_id' => $id,
                        'response'   => $fedaResponse->body(),
                    ]);
                    session()->flash('error', 'FedaPay a refusé le remboursement : ' . $msg);
                    return;
                }
            } catch (\Throwable $e) {
                Log::error('Livewire Disputes resolveForReporter: exception FedaPay', ['error' => $e->getMessage()]);
                session()->flash('error', 'Erreur lors de l\'appel FedaPay : ' . $e->getMessage());
                return;
            }
        }

        // ── Mise à jour DB ───────────────────────────────────────────────────
        DB::transaction(function () use ($dispute, $payment, $id) {
            $validation = TripValidation::where('booking_id', $dispute->booking_id)->first();

            $payment?->update(['status' => 'refunded']);
            $validation?->update(['status' => 'cancelled']);
            $dispute->booking?->update(['payment_status' => 'refunded']);

            $dispute->update([
                'status'               => 'resolved_refunded',
                'admin_decision_notes' => $this->decisionNotes ?: $dispute->admin_decision_notes,
                'assigned_admin_id'    => auth()->id(),
                'resolved_at'          => now(),
            ]);

            AuditLog::record(
                action:      'dispute.refund',
                userId:      auth()->id(),
                ip:          request()->ip(),
                actionType:  'remboursement',
                severity:    'avertissement',
                description: "Litige #{$id} résolu (Livewire) — passager remboursé"
                             . ($payment ? ' (' . number_format($payment->gross_amount, 0, ',', ' ') . ' FCFA)' : ''),
                targetType:  'dispute',
                targetName:  (string) $id,
                userAgent:   request()->userAgent(),
            );
        });

        // ── Notifications ────────────────────────────────────────────────────
        $fresh = $dispute->fresh(['booking.trip.user', 'reporter']);
        try { $fresh->reporter->notify(new DisputeStatusChanged($fresh)); }          catch (\Throwable) {}
        try { $fresh->reporter->notify(new DisputeResolvedForUser($fresh, 'refunded', 'passenger')); } catch (\Throwable) {}
        try {
            $driver = $fresh->booking?->trip?->user;
            $driver?->notify(new DisputeResolvedForUser($fresh, 'refunded', 'driver'));
        } catch (\Throwable) {}

        $this->closeView();
        session()->flash('success', 'Litige résolu — passager remboursé.');
    }

    // ── Décision : faveur défendeur → libérer les fonds au conducteur ────────
    public function resolveForRespondent(int $id): void
    {
        $dispute = Dispute::with(['booking.payment', 'reporter'])->findOrFail($id);

        if ($dispute->isResolved()) {
            session()->flash('error', 'Ce litige est déjà résolu.');
            return;
        }

        DB::transaction(function () use ($dispute, $id) {
            $payment    = $dispute->booking?->payment;
            $validation = TripValidation::where('booking_id', $dispute->booking_id)->first();

            $payment?->update(['status' => 'success']);
            $validation?->update(['status' => 'released', 'released_at' => now()]);
            $dispute->booking?->update(['payment_status' => 'released_to_driver']);

            $dispute->update([
                'status'               => 'resolved_paid_to_driver',
                'admin_decision_notes' => $this->decisionNotes ?: $dispute->admin_decision_notes,
                'assigned_admin_id'    => auth()->id(),
                'resolved_at'          => now(),
            ]);

            AuditLog::record(
                action:      'dispute.pay_driver',
                userId:      auth()->id(),
                ip:          request()->ip(),
                actionType:  'remboursement',
                severity:    'avertissement',
                description: "Litige #{$id} résolu (Livewire) — fonds libérés au conducteur"
                             . ($payment ? ' (' . number_format($payment->net_amount, 0, ',', ' ') . ' FCFA)' : ''),
                targetType:  'dispute',
                targetName:  (string) $id,
                userAgent:   request()->userAgent(),
            );
        });

        $fresh = $dispute->fresh(['booking.trip.user', 'reporter']);
        try { $fresh->reporter->notify(new DisputeStatusChanged($fresh)); } catch (\Throwable) {}
        try { $fresh->reporter->notify(new DisputeResolvedForUser($fresh, 'paid_to_driver', 'passenger')); } catch (\Throwable) {}
        try {
            $driver = $fresh->booking?->trip?->user;
            $driver?->notify(new DisputeResolvedForUser($fresh, 'paid_to_driver', 'driver'));
        } catch (\Throwable) {}

        $this->closeView();
        session()->flash('success', 'Litige résolu — fonds libérés au conducteur.');
    }

    // ── Fermeture sans décision financière (→ résolu en faveur défendeur) ────
    public function closeDispute(int $id): void
    {
        $dispute = Dispute::findOrFail($id);

        if ($dispute->isResolved()) {
            $this->closeView();
            return;
        }

        $notes = trim(($this->decisionNotes ? $this->decisionNotes . ' ' : '') . '[Fermé sans remboursement]');

        $dispute->update([
            'status'               => 'resolved_paid_to_driver',
            'admin_decision_notes' => $notes,
            'assigned_admin_id'    => auth()->id(),
            'resolved_at'          => now(),
        ]);

        try {
            AuditLog::record(
                action:      'dispute.close',
                userId:      auth()->id(),
                ip:          request()->ip(),
                actionType:  'remboursement',
                severity:    'info',
                description: "Litige #{$id} fermé sans remboursement",
                targetType:  'dispute',
                targetName:  (string) $id,
                userAgent:   request()->userAgent(),
            );
        } catch (\Throwable) {}

        $this->closeView();
        session()->flash('success', 'Litige fermé.');
    }

    public function render()
    {
        $query = Dispute::with(['reporter.profile', 'booking.trip', 'booking.passenger.profile'])
            ->when($this->search, function ($q) {
                $s = '%' . $this->search . '%';
                $q->where('description', 'like', $s)
                  ->orWhereHas('reporter', fn ($u) => $u->where('phone', 'like', $s)
                      ->orWhereHas('profile', fn ($p) => $p->where('first_name', 'like', $s)->orWhere('last_name', 'like', $s)));
            })
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->reasonFilter, fn ($q) => $q->where('reason_type', $this->reasonFilter))
            ->orderByDesc('created_at');

        $stats = [
            'total'         => Dispute::count(),
            'pending'       => Dispute::where('status', 'pending')->count(),
            'investigating' => Dispute::where('status', 'investigating')->count(),
            'resolved'      => Dispute::whereIn('status', ['resolved_refunded', 'resolved_paid_to_driver'])->count(),
        ];

        $reasons = Dispute::select('reason_type')->distinct()->pluck('reason_type')->filter()->sort()->values();

        $selectedDispute = $this->selectedDisputeId
            ? Dispute::with(['reporter.profile', 'booking.trip', 'booking.passenger.profile'])->find($this->selectedDisputeId)
            : null;

        return view('admin.disputes', [
            'disputes'        => $query->paginate(15),
            'stats'           => $stats,
            'reasons'         => $reasons,
            'selectedDispute' => $selectedDispute,
        ])->layout('admin.layouts.app', ['title' => 'Litiges']);
    }
}
