<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        }

        Schema::create('products', function (Blueprint $blueprint): void {
            $blueprint->uuid('id')->primary();
            $blueprint->foreignUuid('brand_id')->constrained('brands')->restrictOnDelete();
            $blueprint->foreignUuid('category_id')->constrained('categories')->restrictOnDelete();
            $blueprint->foreignUuid('region_id')->nullable()->constrained('regions')->nullOnDelete();
            $blueprint->string('sku', 100)->unique();
            $blueprint->json('name');
            $blueprint->json('description')->nullable();
            $blueprint->boolean('is_active')->default(true);
            $blueprint->integer('sort_order')->default(0);
            $blueprint->timestamps();

            $blueprint->index(['brand_id', 'category_id', 'is_active'], 'idx_products_brand_category_active');
            $blueprint->index('is_active', 'idx_products_is_active');
            $blueprint->index('region_id', 'idx_products_region_id');
            $blueprint->index('sort_order', 'idx_products_sort_order');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("CREATE INDEX IF NOT EXISTS products_name_en_trgm_idx ON products USING GIN ((name->>'en') gin_trgm_ops)");
            DB::statement("CREATE INDEX IF NOT EXISTS products_name_ar_trgm_idx ON products USING GIN ((name->>'ar') gin_trgm_ops)");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
