<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Raw materials (fabric, trims) are products that are bought and stocked but never sold:
 * `products.item_type` finished|raw_material (existing rows stay finished), and the stock adjustment
 * reason `production_use` so that consumption can be recorded as an adjustment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('item_type', 20)->default('finished')->after('type');
            $table->index(['tenant_id', 'item_type']);
        });
        DB::statement("ALTER TABLE products ADD CONSTRAINT products_item_type_check CHECK (item_type IN ('finished','raw_material'))");

        DB::statement('ALTER TABLE stock_adjustments DROP CHECK stock_adjustments_reason_check');
        DB::statement("ALTER TABLE stock_adjustments ADD CONSTRAINT stock_adjustments_reason_check CHECK (reason IN ('damage','loss','found','opening','correction','stocktake','production_use','other'))");
    }

    public function down(): void
    {
        DB::statement('DELETE FROM stock_adjustments WHERE reason = \'production_use\'');
        DB::statement('ALTER TABLE stock_adjustments DROP CHECK stock_adjustments_reason_check');
        DB::statement("ALTER TABLE stock_adjustments ADD CONSTRAINT stock_adjustments_reason_check CHECK (reason IN ('damage','loss','found','opening','correction','stocktake','other'))");

        DB::statement('ALTER TABLE products DROP CHECK products_item_type_check');
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'item_type']);
            $table->dropColumn('item_type');
        });
    }
};
