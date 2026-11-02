<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->nullable()->constrained()->restrictOnDelete(); // NULL only for platform admins
            $table->string('name', 150);
            $table->string('username', 50)->nullable();
            $table->string('email', 150)->nullable()->unique(); // unique across tenants
            $table->string('phone', 20)->nullable();
            $table->string('password');
            $table->char('legacy_password_md5', 32)->nullable();
            $table->boolean('must_change_password')->default(false);
            $table->string('pin_hash')->nullable();
            $table->unsignedTinyInteger('pin_failed_attempts')->default(0);
            $table->timestamp('pin_locked_until')->nullable();
            $table->boolean('is_platform_admin')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('preferences')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->unsignedInteger('legacy_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'username']);
            $table->unique(['tenant_id', 'phone']);
            $table->index(['tenant_id', 'legacy_id']);
        });
        // A platform admin has no tenant; everyone else must have one.
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_tenant_check CHECK (is_platform_admin = 1 OR tenant_id IS NOT NULL)');

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
