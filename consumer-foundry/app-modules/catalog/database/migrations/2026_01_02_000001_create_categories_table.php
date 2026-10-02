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

        Schema::create('categories', function (Blueprint $blueprint): void {
            $blueprint->uuid('id')->primary();
            $blueprint->uuid('parent_id')->nullable();
            $blueprint->json('name');
            $blueprint->boolean('is_active')->default(true);
            $blueprint->integer('sort_order')->default(0);
            $blueprint->timestamps();
        });

        Schema::table('categories', function (Blueprint $table): void {
            $table->foreign('parent_id', 'fk_categories_parent_id')
                ->references('id')
                ->on('categories')
                ->cascadeOnDelete();
            $table->index('parent_id', 'idx_categories_parent_id');
            $table->index('is_active', 'idx_categories_is_active');
            $table->index('sort_order', 'idx_categories_sort_order');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("CREATE INDEX IF NOT EXISTS categories_name_en_trgm_idx ON categories USING GIN ((name->>'en') gin_trgm_ops)");
            DB::statement("CREATE INDEX IF NOT EXISTS categories_name_ar_trgm_idx ON categories USING GIN ((name->>'ar') gin_trgm_ops)");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
