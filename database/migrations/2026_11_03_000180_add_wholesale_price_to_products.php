<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The product form's "Wholesale price" (mockup New SKU page): a reference price in paisa, nullable.
 * Informational only: pricing (PriceCalculator / engine/pricing.ts) does not read it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->bigInteger('wholesale_price')->nullable()->after('min_sale_price');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('wholesale_price');
        });
    }
};
