<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Branch stock cache, FIFO batches, the movement ledger and stock adjustments. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_stocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('qty_on_hand', 14, 3)->default(0);
            $table->decimal('qty_in_transit', 14, 3)->default(0);
            $table->timestamp('updated_at')->nullable();
            $table->unique(['branch_id', 'product_id']);
            $table->index(['tenant_id', 'product_id']);
        });

        Schema::create('stock_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('source_type', 32);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('batch_no', 50)->nullable();
            $table->bigInteger('unit_cost');
            $table->decimal('qty_received', 14, 3);
            $table->decimal('qty_remaining', 14, 3);
            $table->timestamp('received_at');
            $table->date('expires_at')->nullable();
            $table->unsignedInteger('legacy_id')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'legacy_id']);
            $table->index(['branch_id', 'product_id', 'qty_remaining', 'received_at', 'id'], 'stock_batches_fifo_index');
        });
        DB::statement('ALTER TABLE stock_batches ADD CONSTRAINT stock_batches_qty_check CHECK (qty_remaining >= 0 AND qty_remaining <= qty_received)');
        DB::statement("ALTER TABLE stock_batches ADD CONSTRAINT stock_batches_source_type_check CHECK (source_type IN ('purchase','opening','transfer_in','return','adjustment'))");

        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('stock_batch_id')->nullable()->constrained('stock_batches')->restrictOnDelete();
            $table->string('type', 32);
            $table->decimal('qty', 14, 3);
            $table->bigInteger('unit_cost');
            $table->string('reference_type', 64);
            $table->unsignedBigInteger('reference_id');
            $table->boolean('is_backorder')->default(false);
            $table->string('note', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tenant_id', 'product_id', 'created_at']);
            $table->index(['branch_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });
        DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_type_check CHECK (type IN ('opening','purchase','sale','sale_void','sale_return','adjustment_in','adjustment_out','transfer_out','transfer_in','stocktake'))");

        Schema::create('stock_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('number', 30);
            $table->string('reason', 32);
            $table->unsignedBigInteger('stocktake_id')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('adjusted_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'number']);
        });
        DB::statement("ALTER TABLE stock_adjustments ADD CONSTRAINT stock_adjustments_reason_check CHECK (reason IN ('damage','loss','found','opening','correction','stocktake','other'))");

        Schema::create('stock_adjustment_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_adjustment_id')->constrained('stock_adjustments')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('qty', 14, 3);
            $table->bigInteger('unit_cost');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_items');
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_batches');
        Schema::dropIfExists('branch_stocks');
    }
};
