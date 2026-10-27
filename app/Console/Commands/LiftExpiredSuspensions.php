<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Lève automatiquement les suspensions auto-infligées dont la date est passée.
 * Réinitialise warning_count et visibility_score pour repartir à zéro.
 * Lancé chaque matin à 8h01 (juste après CheckDocumentExpiry).
 */
class LiftExpiredSuspensions extends Command
{
    protected $signature   = 'minizon:lift-expired-suspensions';
    protected $description = 'Lève les suspensions auto expirées et réinitialise le compteur d\'avertissements.';

    public function handle(): void
    {
        $lifted = User::where('suspended_until', '<=', now())
            ->whereNotNull('suspended_until')
            ->get();

        if ($lifted->isEmpty()) {
            $this->info('Aucune suspension expirée.');
            return;
        }

        foreach ($lifted as $user) {
            $user->update([
                'suspended_until'  => null,
                'warning_count'    => 0,
                'visibility_score' => 100,
            ]);
        }

        $this->info("{$lifted->count()} suspension(s) levée(s) et avertissements réinitialisés.");
    }
}
