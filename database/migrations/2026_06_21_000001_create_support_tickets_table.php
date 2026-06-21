<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('subject', 255);
            $table->text('description');
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->enum('channel',  ['app', 'phone', 'email', 'chat', 'other'])->default('app');
            $table->enum('status',   ['new', 'in_progress', 'resolved', 'closed'])->default('new');
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
