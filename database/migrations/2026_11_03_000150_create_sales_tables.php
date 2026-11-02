<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Payment methods, registers, sales, payments and returns. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('name', 60);
            $table->string('type', 32)->default('cash');
            $table->unsignedBigInteger('account_id')->nullable();
            $table->boolean('requires_reference')->default(false);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('sync_version')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'sync_version']);
        });
        DB::statement("ALTER TABLE payment_methods ADD CONSTRAINT payment_methods_type_check CHECK (type IN ('cash','card','mobile_wallet','bank','customer_credit','exchange_credit'))");

        Schema::create('registers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name', 60);
            $table->string('code', 6);
            $table->unsignedBigInteger('last_number')->default(0);
            $table->unsignedSmallInteger('number_year');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['branch_id', 'name']);
            $table->unique(['branch_id', 'code']);
        });

        Schema::create('sales', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->char('client_uuid', 36);
            $table->string('number', 30)->nullable();
            $table->foreignId('register_id')->nullable()->constrained('registers')->restrictOnDelete();
            $table->unsignedBigInteger('exchange_return_id')->nullable();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->string('status', 32)->default('completed');
            $table->timestamp('sold_at');
            $table->bigInteger('subtotal');
            $table->bigInteger('item_discount_total')->default(0);
            $table->bigInteger('order_discount')->default(0);
            $table->bigInteger('tax_total')->default(0);
            $table->bigInteger('shipping')->default(0);
            $table->bigInteger('rounding')->default(0);
            $table->bigInteger('total');
            $table->bigInteger('paid')->default(0);
            $table->bigInteger('change_given')->default(0);
            $table->bigInteger('due')->default(0);
            $table->bigInteger('returned_total')->default(0);
            $table->bigInteger('cogs_total')->default(0);
            $table->string('payment_status', 32)->default('unpaid');
            $table->string('courier_name', 100)->nullable();
            $table->string('courier_tracking', 100)->nullable();
            $table->char('public_token', 32)->nullable();
            $table->string('buyer_vat_bin', 20)->nullable();
            $table->string('origin', 32)->default('device');
            $table->unsignedBigInteger('device_id')->nullable();
            $table->timestamp('device_created_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->string('member_auth', 32)->default('none');
            $table->foreignId('member_auth_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('member_pin_box')->nullable();
            $table->foreignId('membership_card_id')->nullable()->constrained('membership_cards')->restrictOnDelete();
            $table->json('sync_flags')->nullable();
            $table->foreignId('sync_flags_ack_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sync_flags_ack_at')->nullable();
            $table->json('client_totals')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('sold_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 255)->nullable();
            $table->unsignedInteger('legacy_id')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'legacy_id']);
            $table->unique('public_token');
            $table->unique(['tenant_id', 'client_uuid']);
            $table->unique(['tenant_id', 'number']);
            $table->index(['branch_id', 'status', 'sold_at']);
            $table->index(['tenant_id', 'customer_id', 'sold_at']);
            $table->index(['tenant_id', 'payment_status', 'due']);
            $table->index(['register_id', 'sold_at']);
            $table->index(['sold_by', 'sold_at']);
            $table->index(['tenant_id', 'synced_at']);
        });
        DB::statement('ALTER TABLE sales ADD CONSTRAINT sales_amounts_check CHECK (total >= 0 AND paid >= 0 AND due >= 0)');
        DB::statement("ALTER TABLE sales ADD CONSTRAINT sales_status_check CHECK (status IN ('held','completed','voided'))");
        DB::statement("ALTER TABLE sales ADD CONSTRAINT sales_payment_status_check CHECK (payment_status IN ('unpaid','partial','paid'))");
        DB::statement("ALTER TABLE sales ADD CONSTRAINT sales_origin_check CHECK (origin IN ('device','web','import'))");
        DB::statement("ALTER TABLE sales ADD CONSTRAINT sales_member_auth_check CHECK (member_auth IN ('none','pin_online','pin_offline_ok','pin_offline_failed','override','no_pin_policy'))");

        Schema::create('sale_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('product_name', 200);
            $table->string('sku', 64);
            $table->decimal('qty', 14, 3);
            $table->bigInteger('unit_price');
            $table->bigInteger('discount')->default(0);
            $table->unsignedInteger('tax_rate_bp')->default(0);
            $table->bigInteger('tax_amount')->default(0);
            $table->bigInteger('line_total');
            $table->bigInteger('cogs')->default(0);
            $table->decimal('qty_returned', 14, 3)->default(0);
            $table->unsignedInteger('legacy_id')->nullable();
        });
        DB::statement('ALTER TABLE sale_items ADD CONSTRAINT sale_items_qty_check CHECK (qty > 0 AND qty_returned <= qty)');

        Schema::create('sale_item_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_item_id')->constrained('sale_items')->cascadeOnDelete();
            $table->foreignId('stock_batch_id')->constrained('stock_batches')->restrictOnDelete();
            $table->decimal('qty', 14, 3);
            $table->bigInteger('unit_cost');
            $table->decimal('qty_returned', 14, 3)->default(0);
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('number', 30);
            $table->string('direction', 32)->default('in');
            $table->string('party_type', 32)->default('customer');
            $table->unsignedBigInteger('party_id');
            $table->foreignId('payment_method_id')->constrained('payment_methods')->restrictOnDelete();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->bigInteger('amount');
            $table->string('reference', 100)->nullable();
            $table->foreignId('register_id')->nullable()->constrained('registers')->restrictOnDelete();
            $table->timestamp('paid_at');
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->string('note', 255)->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->unsignedInteger('legacy_id')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'legacy_id']);
            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'party_type', 'party_id', 'paid_at']);
            $table->index(['register_id', 'paid_at']);
            $table->index(['received_by', 'paid_at']);
        });
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_check CHECK (amount > 0)');
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_direction_check CHECK (direction IN ('in','out'))");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_party_type_check CHECK (party_type IN ('customer','vendor'))");

        Schema::create('payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->string('allocatable_type', 32);
            $table->unsignedBigInteger('allocatable_id');
            $table->bigInteger('amount');
            $table->index(['allocatable_type', 'allocatable_id']);
        });
        DB::statement("ALTER TABLE payment_allocations ADD CONSTRAINT payment_allocations_allocatable_type_check CHECK (allocatable_type IN ('sale','purchase','sale_return'))");

        Schema::create('sale_returns', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('number', 30);
            $table->foreignId('sale_id')->constrained('sales')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->timestamp('returned_at');
            $table->bigInteger('subtotal');
            $table->bigInteger('tax_total')->default(0);
            $table->bigInteger('total');
            $table->string('refund_mode', 32)->default('refund');
            $table->foreignId('exchange_sale_id')->nullable()->constrained('sales')->restrictOnDelete();
            $table->bigInteger('refunded')->default(0);
            $table->bigInteger('credited')->default(0);
            $table->string('reason', 32);
            $table->text('note')->nullable();
            $table->unsignedInteger('legacy_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'legacy_id']);
            $table->unique(['tenant_id', 'number']);
            $table->index(['branch_id', 'returned_at']);
        });
        DB::statement("ALTER TABLE sale_returns ADD CONSTRAINT sale_returns_refund_mode_check CHECK (refund_mode IN ('refund','credit','exchange'))");

        Schema::create('sale_return_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_return_id')->constrained('sale_returns')->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained('sale_items')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('qty', 14, 3);
            $table->bigInteger('unit_price');
            $table->bigInteger('tax_amount')->default(0);
            $table->bigInteger('line_total');
            $table->boolean('restock')->default(true);
            $table->bigInteger('cogs_reversed')->default(0);
            $table->unsignedInteger('legacy_id')->nullable();
        });
        DB::statement('ALTER TABLE sale_return_items ADD CONSTRAINT sale_return_items_qty_check CHECK (qty > 0)');

        Schema::table('sales', function (Blueprint $table): void {
            $table->foreign('exchange_return_id')->references('id')->on('sale_returns')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropForeign(['exchange_return_id']);
        });

        Schema::dropIfExists('sale_return_items');
        Schema::dropIfExists('sale_returns');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('sale_item_batches');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('registers');
        Schema::dropIfExists('payment_methods');
    }
};
