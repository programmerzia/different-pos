<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Test-only tables for the tenancy and sync traits (loaded only when running tests). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_test_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->unsignedBigInteger('sync_version')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'sync_version']);
        });

        Schema::create('branch_test_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_test_items');
        Schema::dropIfExists('sync_test_items');
    }
};
