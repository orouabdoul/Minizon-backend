<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Review;
use App\Models\TripValidation;
use App\Notifications\EscrowReleased;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReleaseFunds extends Command
{
    protected $signature   = 'minizon:release-funds';
    protected $description = 'Libère automatiquement les fonds en escrow après 24h sans litige ni avis critique.';

    public function handle(): void
    {
        $validations = TripValidation::with(['booking.payment', 'booking.dispute', 'booking.trip'])
            ->where('status', 'waiting')
            ->where('auto_release_at', '<=', now())
            ->whereHas('booking.trip', fn ($q) => $q->where('status', 'completed'))
            ->get();

        if ($validations->isEmpty()) {
            $this->info('Aucun fonds à libérer pour le moment.');
            return;
        }

        $released = 0;
        $blocked  = 0;

        foreach ($validations as $validation) {
            $booking = $validation->booking;

            // ── Condition 1 : litige actif → admin gère via module Litiges ───
            if ($booking?->dispute && ! $booking->dispute->isResolved()) {
                $this->line("  ⏸  Litige actif — Booking #{$validation->booking_id}");
                $blocked++;
                continue;
            }

            // ── Condition 2 : avis critique (≤ 2 étoiles) — notifier l'admin, libérer quand même
            // Un avis négatif seul ne bloque pas : le passager doit soumettre une demande
            // de remboursement explicite (→ Condition 1 via dispute) pour bloquer les fonds.
            $criticalReview = Review::where('trip_id', $booking?->trip_id)
                ->where('reviewer_id', $booking?->passenger_id)
                ->where('rating', '<=', 2)
                ->first();

            if ($criticalReview) {
                $this->notifyAdmin($validation, $criticalReview);
                $this->line("  ⚠️  Avis critique ({$criticalReview->rating}★) — admin notifié, fonds libérés quand même — Booking #{$validation->booking_id}");
                Log::info('ReleaseFunds: avis critique signalé à l\'admin (libération maintenue)', [
                    'booking_id' => $validation->booking_id,
                    'rating'     => $criticalReview->rating,
                ]);
            }

            // ── Libération automatique ────────────────────────────────────────
            $validation->update(['status' => 'released', 'released_at' => now()]);
            $booking?->update(['payment_status' => 'released_to_driver']);
            $booking?->payment?->update(['status' => 'success']);

            // Notifier le conducteur que les fonds sont disponibles
            try {
                $driver = $booking?->trip?->user;
                if ($driver && $booking?->payment) {
                    $driver->notify(new EscrowReleased($booking->payment));
                }
            } catch (\Throwable $e) {
                Log::warning('ReleaseFunds: notif conducteur échouée', ['err' => $e->getMessage()]);
            }

            $amount = $booking?->payment?->net_amount;
            $this->line("  ✅ Fonds libérés — Booking #{$validation->booking_id}");
            Log::info('ReleaseFunds: fonds libérés', [
                'booking_id' => $validation->booking_id,
                'amount'     => $amount,
            ]);

            try {
                AuditLog::record(
                    action:      'payment.auto_release',
                    userId:      0,
                    ip:          'system',
                    actionType:  'remboursement',
                    severity:    'info',
                    description: 'Libération automatique escrow — Booking #' . $validation->booking_id
                                 . ($amount ? ' (' . number_format($amount, 0, ',', ' ') . ' FCFA)' : ''),
                    targetType:  'booking',
                    targetName:  (string) $validation->booking_id,
                    userAgent:   'artisan:minizon:release-funds',
                );
            } catch (\Throwable $e) {
                Log::warning('ReleaseFunds: AuditLog échoué', ['error' => $e->getMessage()]);
            }

            $released++;
        }

        $this->info("{$released} libéré(s). {$blocked} bloqué(s) par litige actif.");

        // ── Passe 2 : paiements locked sans TripValidation (anciens trajets) ──────
        // Couvre les trajets terminés avant la création automatique de TripValidation
        // dans endTrip(). Condition : trip completed +2h, aucun litige actif.
        $orphanPayments = Payment::with(['booking.dispute', 'booking.trip'])
            ->where('status', 'locked')
            ->whereHas('booking', fn ($q) => $q->where('status', 'accepted'))
            ->whereHas('booking.trip', fn ($q) => $q
                ->where('status', 'completed')
                ->where('completed_at', '<=', now()->subHours(2))
            )
            ->whereDoesntHave('booking.tripValidation', fn ($q) => $q->whereIn('status', ['released', 'disputed']))
            ->get();

        $orphanReleased = 0;

        foreach ($orphanPayments as $payment) {
            $booking = $payment->booking;

            if ($booking?->dispute && ! $booking->dispute->isResolved()) {
                continue;
            }

            try {
                $payment->update(['status' => 'success']);
                $booking?->update(['payment_status' => 'released_to_driver']);

                // Marquer TripValidation comme released si elle existe
                $booking?->tripValidation?->update(['status' => 'released', 'released_at' => now()]);

                // Notifier le conducteur
                try {
                    $driver = $booking?->trip?->user;
                    if ($driver) {
                        $driver->notify(new EscrowReleased($payment));
                    }
                } catch (\Throwable $e) {
                    Log::warning('ReleaseFunds passe2: notif conducteur échouée', ['err' => $e->getMessage()]);
                }

                Log::info('ReleaseFunds passe2: fonds libérés (orphan)', [
                    'booking_id' => $booking?->id,
                    'amount'     => $payment->net_amount,
                ]);

                AuditLog::record(
                    action:      'payment.auto_release',
                    userId:      0,
                    ip:          'system',
                    actionType:  'remboursement',
                    severity:    'info',
                    description: 'Libération auto (orphan) — Booking #' . ($booking?->id ?? '?')
                                 . ($payment->net_amount ? ' (' . number_format($payment->net_amount, 0, ',', ' ') . ' FCFA)' : ''),
                    targetType:  'payment',
                    targetName:  $payment->transaction_reference ?? (string) $payment->id,
                    userAgent:   'artisan:minizon:release-funds',
                );

                $orphanReleased++;
            } catch (\Throwable $e) {
                Log::error('ReleaseFunds passe2: erreur', [
                    'payment_id' => $payment->id,
                    'err'        => $e->getMessage(),
                ]);
            }
        }

        if ($orphanReleased > 0) {
            $this->info("{$orphanReleased} libéré(s) (anciens trajets sans TripValidation).");
        }
    }

    private function notifyAdmin(TripValidation $validation, Review $review): void
    {
        $booking = $validation->booking;
        $trip    = $booking?->trip;

        $from = $trip?->departure_city ?? '—';
        $to   = $trip?->arrival_city   ?? '—';

        try {
            DB::table('notifications')->insert([
                'id'              => (string) Str::uuid(),
                'type'            => 'critical_review_escrow',
                'notifiable_type' => 'App\Models\User',
                'notifiable_id'   => 1, // admin principal (ID 1)
                'data'            => json_encode([
                    'title'        => 'Avis critique — Décision requise',
                    'body'         => "Un passager a donné {$review->rating}★ pour le trajet {$from} → {$to}. Les fonds ont été libérés au conducteur. Vérifiez si une action est nécessaire.",
                    'booking_uuid' => $booking?->uuid,
                    'payment_uuid' => $booking?->payment?->uuid,
                    'rating'       => $review->rating,
                    'comment'      => $review->comment ?? null,
                    'trip'         => "{$from} → {$to}",
                    'action_required' => true,
                ]),
                'read_at'    => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('ReleaseFunds: impossible de créer la notification admin', [
                'booking_id' => $validation->booking_id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
