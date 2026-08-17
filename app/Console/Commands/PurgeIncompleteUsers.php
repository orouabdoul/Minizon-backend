<?php

namespace App\Console\Commands;

use App\Models\Profile;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurgeIncompleteUsers extends Command
{
    protected $signature   = 'minizon:purge-incomplete-users';
    protected $description = 'Supprime les utilisateurs n\'ayant pas complété leur profil dans les 20 minutes suivant l\'inscription.';

    public function handle(): void
    {
        $cutoff = now()->subMinutes(20);

        $users = User::where('is_profile_complete', false)
            ->where('created_at', '<=', $cutoff)
            ->get();

        if ($users->isEmpty()) {
            $this->info('Aucun compte incomplet à purger.');
            return;
        }

        $count = 0;

        foreach ($users as $user) {
            DB::beginTransaction();
            try {
                Vehicle::where('user_id', $user->id)->delete();
                Profile::where('user_id', $user->id)->delete();
                $user->tokens()->delete();
                $user->delete();

                DB::commit();

                Log::info('PurgeIncompleteUsers: compte supprimé', [
                    'user_id' => $user->id,
                    'phone'   => $user->phone,
                    'age_min' => $user->created_at->diffInMinutes(now()),
                ]);

                $count++;
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error('PurgeIncompleteUsers: erreur suppression', [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        $this->info("{$count} compte(s) incomplet(s) supprimé(s).");
    }
}
