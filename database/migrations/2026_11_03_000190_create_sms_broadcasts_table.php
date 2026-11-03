<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per ad-hoc SMS sent from the messaging hub's composer (SendSmsBroadcast). The per-recipient
 * `messages` rows are created later by the FanOutSmsBroadcast job (related = this broadcast). The
 * composer's idempotency key is unique per tenant, so a double submit or a retry after a timeout
 * returns the same broadcast instead of sending twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_broadcasts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('audience');
            $table->string('template', 40)->nullable();
            $table->text('body');
            $table->unsignedInteger('recipient_count');
            $table->bigInteger('cost_paisa');
            $table->string('idempotency_key', 64);
            $table->string('status', 32)->default('queued');
            $table->unsignedInteger('queued_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_broadcasts');
    }
};
