<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Ajoute le taux de frais de service passager dans la table commissions
        // si la ligne n'existe pas déjà (idempotent)
        if (! DB::table('commissions')->where('type', 'frais_service_passager')->exists()) {
            DB::table('commissions')->insert([
                'uuid'         => (string) Str::uuid(),
                'type'         => 'frais_service_passager',
                'label'        => 'Frais de service passager',
                'rate_percent' => 5.00,
                'status'       => 'active',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('commissions')->where('type', 'frais_service_passager')->delete();
    }
};
