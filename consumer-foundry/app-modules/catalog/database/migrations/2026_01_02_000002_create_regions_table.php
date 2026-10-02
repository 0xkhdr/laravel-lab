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

        Schema::create('regions', function (Blueprint $blueprint): void {
            $blueprint->uuid('id')->primary();
            $blueprint->json('name');
            $blueprint->boolean('is_active')->default(true);
            $blueprint->integer('sort_order')->default(0);
            $blueprint->timestamps();

            $blueprint->index('is_active', 'idx_regions_is_active');
            $blueprint->index('sort_order', 'idx_regions_sort_order');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("CREATE INDEX IF NOT EXISTS regions_name_en_trgm_idx ON regions USING GIN ((name->>'en') gin_trgm_ops)");
            DB::statement("CREATE INDEX IF NOT EXISTS regions_name_ar_trgm_idx ON regions USING GIN ((name->>'ar') gin_trgm_ops)");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('regions');
    }
};
