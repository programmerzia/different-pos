<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Product families and products (a variant is a product). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_families', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('code', 40);
            $table->string('name', 200);
            $table->string('name_bn', 200)->nullable();
            $table->foreignId('category_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('tax_rate_id')->nullable()->constrained('tax_rates')->restrictOnDelete();
            $table->foreignId('option1_id')->nullable()->constrained('variant_options')->restrictOnDelete();
            $table->foreignId('option2_id')->nullable()->constrained('variant_options')->restrictOnDelete();
            $table->string('image_path', 255)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('sync_version')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'sync_version']);
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('family_id')->nullable()->constrained('product_families')->restrictOnDelete();
            $table->foreignId('option1_value_id')->nullable()->constrained('variant_option_values')->restrictOnDelete();
            $table->foreignId('option2_value_id')->nullable()->constrained('variant_option_values')->restrictOnDelete();
            $table->string('variant_label', 100)->nullable();
            $table->string('variant_key', 40)->storedAs("CONCAT_WS('-', option1_value_id, option2_value_id)");
            $table->string('type', 32)->default('standard');
            $table->string('name', 200);
            $table->string('name_bn', 200)->nullable();
            $table->string('sku', 64);
            $table->string('barcode', 64)->nullable();
            $table->string('hs_code', 12)->nullable();
            $table->foreignId('category_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('tax_rate_id')->nullable()->constrained('tax_rates')->restrictOnDelete();
            $table->foreignId('default_vendor_id')->nullable()->constrained('vendors')->restrictOnDelete();
            $table->bigInteger('sale_price');
            $table->bigInteger('cost_price')->default(0);
            $table->bigInteger('min_sale_price')->nullable();
            $table->decimal('min_sale_qty', 14, 3)->default(1);
            $table->decimal('reorder_level', 14, 3)->default(0);
            $table->boolean('track_stock')->default(true);
            $table->string('image_path', 255)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('sync_version')->nullable();
            $table->unsignedInteger('legacy_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'legacy_id']);
            $table->index(['tenant_id', 'sync_version']);
            $table->unique(['tenant_id', 'sku']);
            $table->unique(['tenant_id', 'barcode']);
            $table->unique(['family_id', 'variant_key']);
            $table->index(['tenant_id', 'category_id']);
            $table->index(['tenant_id', 'brand_id']);
            $table->index(['tenant_id', 'is_active']);
        });
        DB::statement('ALTER TABLE products ADD FULLTEXT products_search_fulltext (name, name_bn, sku, barcode) WITH PARSER ngram');
        DB::statement("ALTER TABLE products ADD CONSTRAINT products_barcode_check CHECK (barcode IS NULL OR barcode NOT LIKE 'MC%')");
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_prices_check CHECK (sale_price >= 0 AND cost_price >= 0)');
        DB::statement("ALTER TABLE products ADD CONSTRAINT products_type_check CHECK (type IN ('standard','service'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_families');
    }
};
