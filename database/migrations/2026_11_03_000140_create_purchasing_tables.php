<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Purchases and their lines. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->restrictOnDelete();
            $table->string('number', 30);
            $table->string('vendor_ref', 60)->nullable();
            $table->string('status', 32)->default('received');
            $table->timestamp('purchased_at');
            $table->timestamp('received_at')->nullable();
            $table->bigInteger('subtotal');
            $table->bigInteger('discount')->default(0);
            $table->bigInteger('tax_total')->default(0);
            $table->bigInteger('shipping')->default(0);
            $table->bigInteger('total');
            $table->bigInteger('paid')->default(0);
            $table->bigInteger('due')->default(0);
            $table->string('payment_status', 32)->default('unpaid');
            $table->text('note')->nullable();
            $table->unsignedInteger('legacy_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'legacy_id']);
            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'vendor_id', 'purchased_at']);
            $table->index(['branch_id', 'status', 'purchased_at']);
        });
        DB::statement("ALTER TABLE purchases ADD CONSTRAINT purchases_status_check CHECK (status IN ('draft','received','cancelled'))");
        DB::statement("ALTER TABLE purchases ADD CONSTRAINT purchases_payment_status_check CHECK (payment_status IN ('unpaid','partial','paid'))");

        Schema::create('purchase_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('qty', 14, 3);
            $table->bigInteger('unit_cost');
            $table->bigInteger('discount')->default(0);
            $table->bigInteger('tax_amount')->default(0);
            $table->bigInteger('line_total');
            $table->unsignedInteger('legacy_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
    }
};
