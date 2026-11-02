<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** branch_user, user_invitations, login_activities tables. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_user', function (Blueprint $table): void {
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_default')->default(false);
            $table->primary(['branch_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('user_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('email', 150)->nullable();
            $table->string('phone', 20)->nullable();
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            $table->json('branch_ids');
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('invited_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('login_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('login', 150);
            $table->string('event', 24);
            $table->string('ip_address', 45);
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tenant_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_activities');
        Schema::dropIfExists('user_invitations');
        Schema::dropIfExists('branch_user');
    }
};
