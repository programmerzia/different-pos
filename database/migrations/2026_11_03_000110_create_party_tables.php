<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Customer groups, customers, membership cards and vendors. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->unsignedInteger('discount_bp')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('legacy_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'legacy_id']);
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_group_id')->nullable()->constrained('customer_groups')->restrictOnDelete();
            $table->unsignedInteger('customer_no')->nullable();
            $table->string('name', 150);
            $table->string('name_bn', 150)->nullable();
            $table->string('shop_name', 150)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('alt_phone', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('gender', 32)->nullable();
            $table->date('dob')->nullable();
            $table->date('anniversary_date')->nullable();
            $table->string('vat_bin', 20)->nullable();
            $table->unsignedSmallInteger('geo_division_id')->nullable();
            $table->unsignedSmallInteger('geo_district_id')->nullable();
            $table->unsignedSmallInteger('geo_upazila_id')->nullable();
            $table->unsignedInteger('geo_union_id')->nullable();
            $table->string('area', 150)->nullable();
            $table->char('post_code', 4)->nullable();
            $table->string('address_line', 500)->nullable();
            $table->boolean('location_verified')->default(true);
            $table->string('pin_hash', 255)->nullable();
            $table->timestamp('pin_set_at')->nullable();
            $table->unsignedTinyInteger('pin_failed_attempts')->default(0);
            $table->timestamp('pin_locked_until')->nullable();
            $table->date('member_since')->nullable();
            $table->bigInteger('credit_limit')->nullable();
            $table->bigInteger('balance')->default(0);
            $table->timestamp('last_purchase_at')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_walk_in')->default(false);
            $table->boolean('is_active')->default(true);
            $table->char('origin_client_uuid', 36)->nullable();
            $table->foreignId('merged_into_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->unsignedBigInteger('sync_version')->nullable();
            $table->unsignedInteger('legacy_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'legacy_id']);
            $table->index(['tenant_id', 'sync_version']);
            $table->unique(['tenant_id', 'customer_no']);
            $table->unique(['tenant_id', 'phone']);
            $table->unique(['tenant_id', 'origin_client_uuid']);
            $table->index(['tenant_id', 'name']);
            $table->index(['tenant_id', 'last_purchase_at']);
            $table->index(['tenant_id', 'balance']);
            $table->index(['tenant_id', 'geo_district_id']);
            $table->index(['tenant_id', 'geo_upazila_id']);
        });
        DB::statement("ALTER TABLE customers ADD CONSTRAINT customers_gender_check CHECK (gender IN ('male','female','other'))");

        Schema::create('membership_cards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->unsignedTinyInteger('issue_no');
            $table->string('card_code', 16);
            $table->string('status', 32)->default('active');
            $table->timestamp('issued_at');
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->unsignedSmallInteger('print_count')->default(0);
            $table->timestamp('last_printed_at')->nullable();
            $table->string('last_print_layout', 32)->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->foreignId('blocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('block_reason', 255)->nullable();
            $table->string('active_guard', 40)->nullable()->virtualAs("IF(status = 'active', customer_id, NULL)")->unique();
            $table->unsignedBigInteger('sync_version')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'sync_version']);
        });
        DB::statement("ALTER TABLE membership_cards ADD CONSTRAINT membership_cards_status_check CHECK (status IN ('active','lost','replaced','revoked'))");
        DB::statement("ALTER TABLE membership_cards ADD CONSTRAINT membership_cards_last_print_layout_check CHECK (last_print_layout IN ('cr80','a4_10','pdf'))");

        Schema::create('vendors', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('name', 150);
            $table->string('company', 200)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('address', 500)->nullable();
            $table->text('notes')->nullable();
            $table->bigInteger('balance')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('legacy_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'legacy_id']);
            $table->unique(['tenant_id', 'phone']);
            $table->index(['tenant_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors');
        Schema::dropIfExists('membership_cards');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('customer_groups');
    }
};
