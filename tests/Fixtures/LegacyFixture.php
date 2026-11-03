<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A tiny legacy POS database for the importer tests. The `legacy` connection is pointed at the
 * testing database with the table prefix `legacy_`, so `store_product_list` lives in
 * `legacy_store_product_list` next to the app tables. Its rows are committed on that second
 * connection (RefreshDatabase only wraps the default one), so install() drops and recreates them.
 */
final class LegacyFixture
{
    public const string CONNECTION = 'legacy';

    /** @var list<string> */
    private const array TABLES = [
        'store_storelist', 'store_admin', 'store_admin_group', 'store_brand_list', 'store_category_list', 'store_vendor_list',
        'store_customer_group', 'store_customer_list', 'store_product_list', 'store_stock_invoice_list', 'store_stock_invoice_details',
        'store_sales_invoice_list', 'store_sales_invoice_details', 'store_sales_invoice_payment_list', 'store_return_invoice_list',
        'store_return_invoice_details',
    ];

    public static function install(): void
    {
        /** @var array<string, mixed> $mysql */
        $mysql = config('database.connections.mysql');
        config(['database.connections.'.self::CONNECTION => array_merge($mysql, ['prefix' => 'legacy_'])]);
        DB::purge(self::CONNECTION);

        $schema = Schema::connection(self::CONNECTION);
        if ($schema->hasTable('store_return_invoice_details')) {
            // Created earlier in this process (the testing database is only wiped once): empty and reseed.
            foreach (self::TABLES as $table) {
                DB::connection(self::CONNECTION)->table($table)->delete();
            }
            self::seed();

            return;
        }

        foreach (self::TABLES as $table) {
            $schema->dropIfExists($table);
        }

        $schema->create('store_storelist', function (Blueprint $t): void {
            $t->integer('id')->primary();
            $t->string('store_name', 100);
            $t->string('address', 200)->nullable();
            $t->string('contact_no', 15)->nullable();
            $t->tinyInteger('is_use_stock_invoice')->default(1);
            $t->string('status', 10)->default('Active');
            $t->string('logo')->nullable();
            $t->timestamp('created_at')->nullable();
        });
        $schema->create('store_admin_group', function (Blueprint $t): void {
            $t->integer('id')->primary();
            $t->string('title', 50);
            $t->string('comment', 200)->nullable();
            $t->string('status', 10)->default('Active');
        });
        $schema->create('store_admin', function (Blueprint $t): void {
            $t->integer('id')->primary();
            $t->integer('id_admin_group');
            $t->string('username', 20);
            $t->string('passwd', 70);
            $t->integer('store_id');
            $t->string('address')->nullable();
            $t->string('mobile', 20)->nullable();
            $t->string('email', 50)->nullable();
            $t->timestamp('create_date')->nullable();
            $t->timestamp('last_login_time')->nullable();
            $t->string('status', 10)->default('ACTIVE');
        });
        foreach (['store_brand_list' => 'brand_name', 'store_category_list' => 'category_name'] as $table => $column) {
            $schema->create($table, function (Blueprint $t) use ($column): void {
                $t->integer('id')->primary();
                $t->string($column, 150);
                $t->string('brand_id', 150)->nullable();
                $t->integer('store_id');
            });
        }
        $schema->create('store_vendor_list', function (Blueprint $t): void {
            $t->integer('id')->primary();
            $t->integer('store_id');
            $t->string('name', 100);
            $t->string('mobile', 15)->nullable();
            $t->string('email', 100)->nullable();
            $t->string('address', 100)->nullable();
            $t->string('description')->nullable();
        });
        $schema->create('store_customer_group', function (Blueprint $t): void {
            $t->integer('id')->primary();
            $t->string('title', 50);
            $t->string('status', 10)->default('Active');
        });
        $schema->create('store_customer_list', function (Blueprint $t): void {
            $t->integer('id')->primary();
            $t->integer('store_id');
            $t->string('name', 100)->default('');
            $t->string('username')->nullable();
            $t->string('passwd')->nullable();
            $t->string('mobile', 15)->nullable();
            $t->string('email', 100)->nullable();
            $t->string('address', 100)->nullable();
            $t->integer('customer_group_id')->default(0);
            $t->date('dob')->nullable();
            $t->date('marriage_date')->nullable();
            $t->string('shop_name')->nullable();
            $t->timestamp('created_at')->nullable();
        });
        $schema->create('store_product_list', function (Blueprint $t): void {
            $t->integer('id')->primary();
            $t->integer('store_id');
            $t->string('product_name', 200);
            $t->string('product_code')->nullable();
            $t->integer('category_id')->default(0);
            $t->integer('brand_id')->default(0);
            $t->integer('vendor_id')->default(0);
            $t->integer('quantity')->default(0);
            $t->float('mrp');
            $t->double('buy_price');
            $t->integer('min_sale_qty')->default(1);
            $t->integer('stock_out_alert')->default(0);
            $t->string('status', 10)->default('Active');
        });
        $schema->create('store_stock_invoice_list', function (Blueprint $t): void {
            $t->integer('id')->primary();
            $t->integer('store_id');
            $t->string('invoice_no', 20);
            $t->timestamp('created_at')->nullable();
            $t->integer('created_by')->default(0);
        });
        $schema->create('store_stock_invoice_details', function (Blueprint $t): void {
            $t->integer('id')->primary();
            $t->integer('invoice_id');
            $t->integer('product_id');
            $t->float('buy_price');
            $t->integer('quantity');
            $t->string('description')->nullable();
            $t->integer('available_stock');
            $t->timestamp('created_at')->nullable();
        });
        $schema->create('store_sales_invoice_list', function (Blueprint $t): void {
            $t->integer('id')->primary();
            $t->integer('store_id');
            $t->string('invoice_no', 15);
            $t->integer('total_quantity')->default(0);
            $t->float('total_amount');
            $t->float('total_discount')->default(0);
            $t->float('total_due')->default(0);
            $t->integer('customer_id')->default(0);
            $t->string('payment_status', 10);
            $t->dateTime('created_at')->nullable();
            $t->string('courier_service')->nullable();
            $t->integer('created_by')->default(0);
        });
        $schema->create('store_sales_invoice_details', function (Blueprint $t): void {
            $t->integer('id')->primary();
            $t->integer('invoice_id');
            $t->integer('product_id');
            $t->string('description')->nullable();
            $t->integer('quantity');
            $t->float('price');
            $t->float('buy_price');
            $t->float('discount')->default(0);
            $t->integer('stock_invoice_no')->default(0);
        });
        $schema->create('store_sales_invoice_payment_list', function (Blueprint $t): void {
            $t->integer('id')->primary();
            $t->integer('invoice_id');
            $t->float('paid_amount');
            $t->dateTime('created_at')->nullable();
            $t->integer('created_by')->default(0);
        });
        $schema->create('store_return_invoice_list', function (Blueprint $t): void {
            $t->integer('id')->primary();
            $t->integer('store_id');
            $t->string('invoice_no', 15);
            $t->float('total_amount');
            $t->float('total_discount')->default(0);
            $t->dateTime('created_at')->nullable();
            $t->integer('created_by')->default(0);
        });
        $schema->create('store_return_invoice_details', function (Blueprint $t): void {
            $t->integer('id')->primary();
            $t->integer('invoice_id');
            $t->integer('sales_invoice_id');
            $t->integer('stock_invoice_id')->default(0);
            $t->integer('product_id');
            $t->integer('quantity');
            $t->float('price');
            $t->float('discount')->default(0);
        });

        self::seed();
    }

    private static function seed(): void
    {
        $db = DB::connection(self::CONNECTION);
        $md5 = md5('secret');

        $db->table('store_storelist')->insert([
            ['id' => 7, 'store_name' => 'DIFFERENT FASHION', 'address' => 'Dhaka', 'contact_no' => '01675111376', 'is_use_stock_invoice' => 1, 'status' => 'Active', 'logo' => 'logo7.png'],
            ['id' => 10, 'store_name' => 'DIFFERENT FASHION-2', 'address' => 'Dhaka', 'contact_no' => '', 'is_use_stock_invoice' => 0, 'status' => 'Active', 'logo' => ''],
            ['id' => 99, 'store_name' => 'Somebody else', 'address' => '', 'contact_no' => '', 'is_use_stock_invoice' => 1, 'status' => 'Active', 'logo' => ''],
        ]);
        $db->table('store_admin_group')->insert([['id' => 1, 'title' => 'superadmin'], ['id' => 2, 'title' => 'admin'], ['id' => 5, 'title' => 'Odd group']]);
        $db->table('store_admin')->insert([
            ['id' => 28, 'id_admin_group' => 2, 'username' => 'Different -1', 'passwd' => $md5, 'store_id' => 7, 'mobile' => '01675111376', 'email' => 'null@gmail.com', 'status' => 'ACTIVE'],
            ['id' => 31, 'id_admin_group' => 2, 'username' => 'Different -2', 'passwd' => $md5, 'store_id' => 10, 'mobile' => '01717', 'email' => '', 'status' => 'INACTIVE'],
            ['id' => 40, 'id_admin_group' => 1, 'username' => 'other', 'passwd' => $md5, 'store_id' => 99, 'mobile' => '', 'email' => '', 'status' => 'ACTIVE'],
        ]);
        $db->table('store_brand_list')->insert([
            ['id' => 257, 'brand_name' => 'Different Fashion', 'store_id' => 7],
            ['id' => 259, 'brand_name' => ' different fashion ', 'store_id' => 10],
        ]);
        $db->table('store_category_list')->insert([
            ['id' => 272, 'category_name' => 'Shirt', 'brand_id' => '257', 'store_id' => 7],
            ['id' => 273, 'category_name' => 'Panjabi', 'brand_id' => '257', 'store_id' => 7],
            ['id' => 277, 'category_name' => 'SHIRT', 'brand_id' => '259', 'store_id' => 10],
        ]);
        $db->table('store_vendor_list')->insert([
            ['id' => 3, 'store_id' => 7, 'name' => 'Indian Fabrics', 'mobile' => '012227222', 'email' => '', 'address' => 'No Description', 'description' => ''],
            ['id' => 4, 'store_id' => 10, 'name' => 'Indian Fabrics', 'mobile' => '01754954954', 'email' => 'fabrics@example.com', 'address' => 'Kolkata', 'description' => 'Main supplier'],
        ]);
        $db->table('store_customer_group')->insert([['id' => 1, 'title' => 'Whole Sale']]);
        $db->table('store_customer_list')->insert([
            ['id' => 815, 'store_id' => 7, 'name' => 'Rahim', 'username' => 'rahim', 'passwd' => 'plain', 'mobile' => '01711000001', 'email' => '', 'address' => 'Mirpur, Dhaka', 'customer_group_id' => 1, 'shop_name' => 'Rahim Store', 'created_at' => '2024-05-01 10:00:00'],
            ['id' => 816, 'store_id' => 7, 'name' => '', 'username' => '', 'passwd' => '', 'mobile' => '0171100', 'email' => 'karim@example.com', 'address' => '', 'customer_group_id' => 1, 'shop_name' => 'Karim Traders', 'created_at' => '2024-05-02 10:00:00'],
            ['id' => 830, 'store_id' => 10, 'name' => 'Rahim again', 'username' => '', 'passwd' => '', 'mobile' => '+8801711000001', 'email' => '', 'address' => '', 'customer_group_id' => 1, 'shop_name' => '', 'created_at' => '2025-01-01 10:00:00'],
            ['id' => 900, 'store_id' => 99, 'name' => 'Not ours', 'username' => '', 'passwd' => '', 'mobile' => '01711000009', 'email' => '', 'address' => '', 'customer_group_id' => 1, 'shop_name' => '', 'created_at' => '2025-01-01 10:00:00'],
        ]);
        $db->table('store_product_list')->insert([
            ['id' => 2418, 'store_id' => 7, 'product_name' => 'Shirt Chaina', 'product_code' => '410500', 'category_id' => 272, 'brand_id' => 257, 'vendor_id' => 3, 'quantity' => 10, 'mrp' => 1050, 'buy_price' => 0, 'min_sale_qty' => 3, 'stock_out_alert' => 3, 'status' => 'Active'],
            ['id' => 2419, 'store_id' => 7, 'product_name' => 'Panjabi Solid', 'product_code' => '309000', 'category_id' => 273, 'brand_id' => 257, 'vendor_id' => 0, 'quantity' => 5, 'mrp' => 900, 'buy_price' => 700.5, 'min_sale_qty' => 1, 'stock_out_alert' => 1, 'status' => 'Inactive'],
            ['id' => 2451, 'store_id' => 10, 'product_name' => 'Shirt Chaina', 'product_code' => '410500', 'category_id' => 277, 'brand_id' => 259, 'vendor_id' => 4, 'quantity' => 3, 'mrp' => 1050, 'buy_price' => 600, 'min_sale_qty' => 1, 'stock_out_alert' => 0, 'status' => 'Active'],
            // Same code twice inside store 7: keeps the suffix.
            ['id' => 2460, 'store_id' => 7, 'product_name' => 'Panjabi Solid 2', 'product_code' => '309000', 'category_id' => 273, 'brand_id' => 257, 'vendor_id' => 0, 'quantity' => 0, 'mrp' => 950, 'buy_price' => 700, 'min_sale_qty' => 1, 'stock_out_alert' => 0, 'status' => 'Active'],
        ]);
        $db->table('store_stock_invoice_list')->insert([
            ['id' => 1053, 'store_id' => 7, 'invoice_no' => 'ST1', 'created_at' => '2024-05-06 04:57:42', 'created_by' => 28],
            ['id' => 1054, 'store_id' => 7, 'invoice_no' => 'ST2', 'created_at' => '2024-05-08 04:00:00', 'created_by' => 28],
            ['id' => 1060, 'store_id' => 10, 'invoice_no' => 'ST1', 'created_at' => '2025-07-22 06:58:54', 'created_by' => 31],
        ]);
        $db->table('store_stock_invoice_details')->insert([
            ['id' => 5001, 'invoice_id' => 1053, 'product_id' => 2418, 'buy_price' => 600, 'quantity' => 20, 'available_stock' => 8],
            ['id' => 5002, 'invoice_id' => 1053, 'product_id' => 2419, 'buy_price' => 700.5, 'quantity' => 10, 'available_stock' => 5],
            // Sold out: a batch row for linking only, no movement.
            ['id' => 5004, 'invoice_id' => 1054, 'product_id' => 2419, 'buy_price' => 710, 'quantity' => 4, 'available_stock' => 0],
            ['id' => 5003, 'invoice_id' => 1060, 'product_id' => 2451, 'buy_price' => 600, 'quantity' => 5, 'available_stock' => 3],
        ]);
        $db->table('store_sales_invoice_list')->insert([
            // Paid, no payment row -> synthesised payment of total - due.
            ['id' => 1269, 'store_id' => 7, 'invoice_no' => 'S-1', 'total_amount' => 2100, 'total_discount' => 100, 'total_due' => 0, 'customer_id' => 815, 'payment_status' => 'Paid', 'created_at' => '2024-05-06 11:00:45', 'courier_service' => 'NA', 'created_by' => 28],
            // Due, one real payment, one zero payment row; customer 830 is merged into 815.
            ['id' => 1270, 'store_id' => 7, 'invoice_no' => 'S-2', 'total_amount' => 2700, 'total_discount' => 0, 'total_due' => 1700, 'customer_id' => 830, 'payment_status' => 'Due', 'created_at' => '2024-05-11 03:45:06', 'courier_service' => 'Sundarban', 'created_by' => 28],
            // Future-dated, walk-in (customer 0), number collides with store 7.
            ['id' => 1271, 'store_id' => 10, 'invoice_no' => 'S-1', 'total_amount' => 1050, 'total_discount' => 0, 'total_due' => 0, 'customer_id' => 0, 'payment_status' => 'paid', 'created_at' => '2027-03-02 00:00:00', 'courier_service' => '', 'created_by' => 31],
        ]);
        $db->table('store_sales_invoice_details')->insert([
            ['id' => 9001, 'invoice_id' => 1269, 'product_id' => 2418, 'quantity' => 2, 'price' => 1050, 'buy_price' => 600, 'discount' => 100, 'stock_invoice_no' => 1053],
            ['id' => 9002, 'invoice_id' => 1270, 'product_id' => 2419, 'quantity' => 3, 'price' => 900, 'buy_price' => 700.5, 'discount' => 0, 'stock_invoice_no' => 0],
            ['id' => 9003, 'invoice_id' => 1271, 'product_id' => 2451, 'quantity' => 1, 'price' => 1050, 'buy_price' => 600, 'discount' => 0, 'stock_invoice_no' => 1060],
        ]);
        $db->table('store_sales_invoice_payment_list')->insert([
            ['id' => 7001, 'invoice_id' => 1270, 'paid_amount' => 1000, 'created_at' => '2024-05-11 03:45:06', 'created_by' => 28],
            ['id' => 7002, 'invoice_id' => 1270, 'paid_amount' => 0, 'created_at' => '2024-05-11 03:45:06', 'created_by' => 28],
        ]);
        $db->table('store_return_invoice_list')->insert([
            ['id' => 401, 'store_id' => 7, 'invoice_no' => 'R-1', 'total_amount' => 1050, 'total_discount' => 50, 'created_at' => '2024-05-07 10:00:00', 'created_by' => 28],
        ]);
        $db->table('store_return_invoice_details')->insert([
            ['id' => 402, 'invoice_id' => 401, 'sales_invoice_id' => 1269, 'stock_invoice_id' => 1053, 'product_id' => 2418, 'quantity' => 1, 'price' => 1050, 'discount' => 50],
        ]);
    }
}
