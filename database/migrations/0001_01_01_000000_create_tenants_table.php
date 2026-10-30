<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Tenants and branches: 05_Schema §1. Runs before the users table (filename order). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 150);
            $table->string('slug', 80)->unique();
            $table->string('legal_name', 200)->nullable();
            $table->string('vat_bin', 20)->nullable();
            $table->string('vat_registered_address', 500)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('address', 500)->nullable();
            $table->char('currency', 3)->default('BDT');
            $table->string('timezone', 64)->default('Asia/Dhaka');
            $table->string('locale', 5)->default('en');
            $table->string('status', 16)->default('active');
            $table->json('settings');
            $table->json('branding')->nullable();
            $table->string('pin_box_public_key', 64);
            $table->text('pin_box_secret_key');
            $table->unsignedBigInteger('sync_counter')->default(0);
            $table->timestamps();
        });
        DB::statement("ALTER TABLE tenants ADD CONSTRAINT tenants_status_check CHECK (status IN ('active','suspended'))");

        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('code', 10);
            $table->string('name', 150);
            $table->string('phone', 20)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('logo_path', 255)->nullable();
            $table->string('receipt_header', 500)->nullable();
            $table->string('receipt_footer', 500)->nullable();
            $table->json('settings');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('legacy_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'legacy_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
        Schema::dropIfExists('tenants');
    }
};
