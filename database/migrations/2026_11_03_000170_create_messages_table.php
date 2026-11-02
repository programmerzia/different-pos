<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** The SMS log. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->string('phone', 20);
            $table->text('body');
            $table->string('driver', 32);
            $table->string('status', 32)->default('queued');
            $table->string('provider_ref', 100)->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('event', 40);
            $table->string('related_type', 64)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'customer_id', 'created_at']);
            $table->index(['related_type', 'related_id']);
        });
        DB::statement("ALTER TABLE messages ADD CONSTRAINT messages_status_check CHECK (status IN ('queued','sent','failed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
