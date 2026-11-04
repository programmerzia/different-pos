<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Delivery couriers a shop books parcels with; the default one prints on the delivery chalan (05_Schema §4). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('couriers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('name', 80);
            $table->string('phone', 30)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            // At most one default per tenant: the tenant id while default, NULL otherwise (UNIQUE ignores NULLs).
            $table->unsignedBigInteger('default_guard')->nullable()->virtualAs('IF(is_default = 1, tenant_id, NULL)')->unique();
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('couriers');
    }
};
