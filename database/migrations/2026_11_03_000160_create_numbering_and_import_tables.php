<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Document sequences and the legacy import id map. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->string('type', 24);
            $table->string('prefix', 20)->default('');
            $table->unsignedBigInteger('next_number')->default(1);
            $table->unsignedTinyInteger('pad')->default(6);
            $table->boolean('reset_yearly')->default(true);
            $table->unsignedSmallInteger('current_year')->nullable();
            $table->unique(['tenant_id', 'branch_id', 'type']);
        });

        Schema::create('legacy_id_maps', function (Blueprint $table): void {
            $table->id();
            $table->string('entity', 40);
            $table->integer('legacy_store_id');
            $table->integer('legacy_id');
            $table->unsignedBigInteger('new_id');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['entity', 'legacy_store_id', 'legacy_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_id_maps');
        Schema::dropIfExists('document_sequences');
    }
};
