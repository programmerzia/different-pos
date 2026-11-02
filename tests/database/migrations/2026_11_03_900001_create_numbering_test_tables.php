<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Test-only document_sequences (skipped once the real migration exists). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('document_sequences')) {
            return;
        }

        Schema::create('document_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type', 24);
            $table->string('prefix', 20)->default('');
            $table->unsignedBigInteger('next_number')->default(1);
            $table->unsignedTinyInteger('pad')->default(6);
            $table->boolean('reset_yearly')->default(true);
            $table->unsignedSmallInteger('current_year')->nullable();
            $table->unique(['tenant_id', 'branch_id', 'type']);
        });
    }

    public function down(): void
    {
        // Left in place: the real schema may own the table.
    }
};
