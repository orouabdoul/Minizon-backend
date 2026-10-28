<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\TripValidation;
use App\Notifications\EscrowReleased;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReleaseEscrowFunds extends Command
{
    protected $signature   = 'minizon:release-funds';
    protected $description = 'Libère automatiquement les fonds en escrow 2h après la fin du trajet, sans litige actif.';

    public function handle(): void
    {
        $validations = TripValidation::with(['trip.user', 'booking.payment', 'booking.dispute'])
            ->where('status', 'waiting')
            ->whereHas('trip', fn ($q) =>
                $q->whereNotNull('completed_at')
                  ->where('completed_at', '<=', now()->subHours(2))
            )
            ->get();

        if ($validations->isEmpty()) {
            $this->info('Aucune libération de fonds à effectuer.');
            return;
        }

        $released = 0;
        $skipped  = 0;

        foreach ($validations as $tv) {
            // Bloquer si litige actif (en attente ou en cours d'investigation)
            $dispute = $tv->booking?->dispute;
            if ($dispute && ($dispute->isPending() || $dispute->isInvestigating())) {
                $skipped++;
                continue;
            }

            $payment = $tv->booking?->payment;
            if (! $payment || ! $payment->isLocked()) {
                $skipped++;
                continue;
            }

            try {
                DB::transaction(function () use ($tv, $payment) {
                    $payment->update(['status' => 'success']);
                    $tv->update(['status' => 'released']);
                    $tv->booking->update(['payment_status' => 'released_to_driver']);

                    $driver = $tv->trip->user;
                    try {
                        $driver?->notify(new EscrowReleased($payment));
                    } catch (\Throwable $e) {
                        Log::warning('ReleaseEscrowFunds: notif driver échouée', [
                            'booking_id' => $tv->booking_id,
                            'err'        => $e->getMessage(),
                        ]);
                    }

                    AuditLog::record(
                        action:      'escrow.released',
                        actionType:  'paiement',
                        severity:    'info',
                        description: 'Fonds libérés auto — booking #' . $tv->booking_id . ' — ' . number_format($payment->net_amount) . ' FCFA -> conducteur',
                        targetType:  'payment',
                        targetName:  $payment->transaction_reference,
                    );
                });

                $released++;
            } catch (\Throwable $e) {
                Log::error('ReleaseEscrowFunds: erreur libération', [
                    'trip_validation_id' => $tv->id,
                    'booking_id'         => $tv->booking_id,
                    'err'                => $e->getMessage(),
                ]);
                $this->warn("Erreur booking #{$tv->booking_id} : {$e->getMessage()}");
            }
        }

        $this->info("{$released} libération(s) effectuée(s). {$skipped} ignorée(s) (litige ou paiement non verrouillé).");
    }
}
