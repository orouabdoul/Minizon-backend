<?php

namespace App\Console\Commands;

use App\Models\TripValidation;
use Illuminate\Console\Command;

class ReleaseFunds extends Command
{
    protected $signature   = 'minizon:release-funds';
    protected $description = "Libère automatiquement les fonds en escrow dont le délai de 24h est écoulé.";

    public function handle(): void
    {
        $validations = TripValidation::with(['booking.payment'])
            ->where('status', 'waiting')
            ->where('auto_release_at', '<=', now())
            ->get();

        if ($validations->isEmpty()) {
            $this->info('Aucun fonds à libérer pour le moment.');
            return;
        }

        foreach ($validations as $validation) {
            $validation->update(['status' => 'released']);
            $validation->booking?->update(['payment_status' => 'released_to_driver']);
            $validation->booking?->payment?->update(['status' => 'success']);

            $this->line("  ✅ Fonds libérés — Booking #{$validation->booking_id}");
        }

        $this->info("{$validations->count()} paiement(s) libéré(s) automatiquement.");
    }
}
