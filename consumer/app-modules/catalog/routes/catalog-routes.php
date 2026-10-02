<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Catalog\Http\Controllers\BrandController;
use Modules\Catalog\Http\Controllers\CategoryController;
use Modules\Catalog\Http\Controllers\ProductController;
use Modules\Catalog\Http\Controllers\RegionController;

Route::prefix('api/v1')->middleware(['api', 'auth:sanctum'])->group(function (): void {
    Route::get('/brands', [BrandController::class, 'index'])->middleware('can:catalog.read')->name('catalog.brands.index');
    Route::post('/brands', [BrandController::class, 'store'])->middleware('can:catalog.write')->name('catalog.brands.store');
    Route::get('/brands/{id}', [BrandController::class, 'show'])->middleware('can:catalog.read')->name('catalog.brands.show');
    Route::patch('/brands/{id}', [BrandController::class, 'update'])->middleware('can:catalog.write')->name('catalog.brands.update');
    Route::delete('/brands/{id}', [BrandController::class, 'destroy'])->middleware('can:catalog.write')->name('catalog.brands.destroy');
    Route::get('/categories', [CategoryController::class, 'index'])->middleware('can:catalog.read')->name('catalog.categories.index');
    Route::post('/categories', [CategoryController::class, 'store'])->middleware('can:catalog.write')->name('catalog.categories.store');
    Route::get('/categories/{id}', [CategoryController::class, 'show'])->middleware('can:catalog.read')->name('catalog.categories.show');
    Route::patch('/categories/{id}', [CategoryController::class, 'update'])->middleware('can:catalog.write')->name('catalog.categories.update');
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy'])->middleware('can:catalog.write')->name('catalog.categories.destroy');
    Route::get('/products', [ProductController::class, 'index'])->middleware('can:catalog.read')->name('catalog.products.index');
    Route::post('/products', [ProductController::class, 'store'])->middleware('can:catalog.write')->name('catalog.products.store');
    Route::get('/products/{id}', [ProductController::class, 'show'])->middleware('can:catalog.read')->name('catalog.products.show');
    Route::patch('/products/{id}', [ProductController::class, 'update'])->middleware('can:catalog.write')->name('catalog.products.update');
    Route::delete('/products/{id}', [ProductController::class, 'destroy'])->middleware('can:catalog.write')->name('catalog.products.destroy');
    Route::get('/regions', [RegionController::class, 'index'])->middleware('can:catalog.read')->name('catalog.regions.index');
    Route::post('/regions', [RegionController::class, 'store'])->middleware('can:catalog.write')->name('catalog.regions.store');
    Route::get('/regions/{id}', [RegionController::class, 'show'])->middleware('can:catalog.read')->name('catalog.regions.show');
    Route::patch('/regions/{id}', [RegionController::class, 'update'])->middleware('can:catalog.write')->name('catalog.regions.update');
    Route::delete('/regions/{id}', [RegionController::class, 'destroy'])->middleware('can:catalog.write')->name('catalog.regions.destroy');
});
