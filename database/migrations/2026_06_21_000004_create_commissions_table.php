<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type')->unique();   // identifiant machine
            $table->string('label');             // libellé affiché
            $table->decimal('rate_percent', 5, 2)->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        $now = now();
        DB::table('commissions')->insert([
            ['uuid' => (string) Str::uuid(), 'type' => 'covoiturage_standard', 'label' => 'Covoiturage Standard', 'rate_percent' => 10.00, 'status' => 'active',   'created_at' => $now, 'updated_at' => $now],
            ['uuid' => (string) Str::uuid(), 'type' => 'covoiturage_premium',  'label' => 'Covoiturage Premium',  'rate_percent' => 15.00, 'status' => 'active',   'created_at' => $now, 'updated_at' => $now],
            ['uuid' => (string) Str::uuid(), 'type' => 'retrait_conducteur',   'label' => 'Retrait Conducteur',   'rate_percent' =>  2.50, 'status' => 'active',   'created_at' => $now, 'updated_at' => $now],
            ['uuid' => (string) Str::uuid(), 'type' => 'assurance_trajet',     'label' => 'Assurance Trajet',     'rate_percent' =>  3.00, 'status' => 'inactive', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }
};
