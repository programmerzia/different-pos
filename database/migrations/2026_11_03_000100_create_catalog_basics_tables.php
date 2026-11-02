<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Categories, brands, units, tax rates and variant options. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->string('name', 150);
            $table->string('name_bn', 150)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('sync_version')->nullable();
            $table->unsignedInteger('legacy_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'legacy_id']);
            $table->index(['tenant_id', 'sync_version']);
            $table->unique(['tenant_id', 'parent_id', 'name']);
        });

        Schema::create('brands', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('name', 150);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('sync_version')->nullable();
            $table->unsignedInteger('legacy_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'legacy_id']);
            $table->index(['tenant_id', 'sync_version']);
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('name', 50);
            $table->string('short_name', 10);
            $table->boolean('allows_decimal')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('sync_version')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'sync_version']);
            $table->unique(['tenant_id', 'short_name']);
        });

        Schema::create('tax_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('name', 50);
            $table->unsignedInteger('rate_bp');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('sync_version')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'sync_version']);
        });

        Schema::create('variant_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('name', 50);
            $table->string('name_bn', 50)->nullable();
            $table->integer('sort_order')->default(0);
            $table->unsignedBigInteger('sync_version')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'sync_version']);
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('variant_option_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('variant_option_id')->constrained('variant_options')->restrictOnDelete();
            $table->string('value', 50);
            $table->string('value_bn', 50)->nullable();
            $table->string('code', 10);
            $table->integer('sort_order')->default(0);
            $table->unsignedBigInteger('sync_version')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'sync_version']);
            $table->unique(['variant_option_id', 'value']);
            $table->unique(['variant_option_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variant_option_values');
        Schema::dropIfExists('variant_options');
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('units');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
    }
};
