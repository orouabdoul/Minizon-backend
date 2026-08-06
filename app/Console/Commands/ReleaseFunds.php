<?php

namespace App\Console\Commands;

use App\Models\Review;
use App\Models\TripValidation;
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
            ->get();

        if ($validations->isEmpty()) {
            $this->info('Aucun fonds à libérer pour le moment.');
            return;
        }

        $released = 0;
        $disputed = 0;
        $blocked  = 0;

        foreach ($validations as $validation) {
            $booking = $validation->booking;

            // ── Condition 1 : litige actif → admin gère via module Litiges ───
            if ($booking?->dispute && ! $booking->dispute->isResolved()) {
                $this->line("  ⏸  Litige actif — Booking #{$validation->booking_id}");
                $blocked++;
                continue;
            }

            // ── Condition 2 : avis critique (≤ 2 étoiles) du passager ─────────
            $criticalReview = Review::where('trip_id', $booking?->trip_id)
                ->where('reviewer_id', $booking?->passenger_id)
                ->where('rating', '<=', 2)
                ->first();

            if ($criticalReview) {
                // Passer en "disputed" pour que l'admin décide manuellement
                $validation->update(['status' => 'disputed']);

                // Créer une notification admin
                $this->notifyAdmin($validation, $criticalReview);

                $this->line("  🔴 Avis critique ({$criticalReview->rating}★) — Booking #{$validation->booking_id} → en attente décision admin");
                Log::info('ReleaseFunds: avis critique signalé à l\'admin', [
                    'booking_id' => $validation->booking_id,
                    'rating'     => $criticalReview->rating,
                ]);
                $disputed++;
                continue;
            }

            // ── Libération automatique ────────────────────────────────────────
            $validation->update(['status' => 'released']);
            $booking?->update(['payment_status' => 'released_to_driver']);
            $booking?->payment?->update(['status' => 'success']);

            $this->line("  ✅ Fonds libérés — Booking #{$validation->booking_id}");
            Log::info('ReleaseFunds: fonds libérés', [
                'booking_id' => $validation->booking_id,
                'amount'     => $booking?->payment?->net_amount,
            ]);
            $released++;
        }

        $this->info("{$released} libéré(s). {$disputed} en attente décision admin. {$blocked} bloqué(s) par litige.");
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
                    'body'         => "Un passager a donné {$review->rating}★ pour le trajet {$from} → {$to}. Les fonds sont bloqués en attente de votre décision.",
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
