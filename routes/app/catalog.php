<?php

declare(strict_types=1);

use App\Http\Controllers\Catalog\BrandController;
use App\Http\Controllers\Catalog\CategoryController;
use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\Catalog\ProductFamilyController;
use App\Http\Controllers\Catalog\ProductImageController;
use App\Http\Controllers\Catalog\VariantOptionController;
use App\Support\Export\ExportFormat;
use Illuminate\Support\Facades\Route;

/*
| Catalog: products, families (variant matrix), categories, brands. Loaded inside the
| branch-selected group of routes/app.php. Static segments come before products/{product}.
*/

Route::get('products', [ProductController::class, 'index'])->name('products.index');
Route::get('products/create', [ProductController::class, 'create'])->name('products.create');
Route::get('products/export.{format}', [ProductController::class, 'export'])->where('format', ExportFormat::pattern())->middleware('can:products.view')->name('products.export');
Route::post('products', [ProductController::class, 'store'])->name('products.store');
Route::get('products/images', ProductImageController::class)->name('products.images');

Route::get('products/categories', [CategoryController::class, 'index'])->name('categories.index');
Route::post('products/categories', [CategoryController::class, 'store'])->name('categories.store');
Route::put('products/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
Route::delete('products/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

Route::get('products/brands', [BrandController::class, 'index'])->name('brands.index');
Route::post('products/brands', [BrandController::class, 'store'])->name('brands.store');
Route::put('products/brands/{brand}', [BrandController::class, 'update'])->name('brands.update');
Route::delete('products/brands/{brand}', [BrandController::class, 'destroy'])->name('brands.destroy');

Route::get('products/families/{family}', [ProductFamilyController::class, 'show'])->name('families.show');
Route::post('products/families/{family}/variants', [ProductFamilyController::class, 'variants'])->name('families.variants');
Route::post('products/families/{family}/members', [ProductFamilyController::class, 'attach'])->name('families.attach');

Route::post('variant-options', [VariantOptionController::class, 'store'])->name('variant-options.store');
Route::post('variant-options/{option}/values', [VariantOptionController::class, 'addValue'])->name('variant-options.values');

Route::get('products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
Route::patch('products/{product}/active', [ProductController::class, 'active'])->name('products.active');
Route::delete('products/{product}/family', [ProductFamilyController::class, 'detach'])->name('families.detach');
Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
