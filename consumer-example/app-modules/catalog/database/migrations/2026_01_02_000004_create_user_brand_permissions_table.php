<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_brand_permissions', function (Blueprint $blueprint): void {
            // Composite PK: (user_id, brand_id) is the natural identity of this row.
            // No synthetic UUID id — nothing references this row by its own key.
            $blueprint->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $blueprint->foreignUuid('brand_id')->constrained('brands')->cascadeOnDelete();

            // Presence = allowed; absence = restricted.
            // SyncUserBrandPermissionsAction deletes all rows then re-inserts allowed IDs.
            // An is_enabled boolean column would always be true — omitted intentionally.

            $blueprint->primary(['user_id', 'brand_id'], 'pk_user_brand_permissions');
            $blueprint->index('brand_id', 'idx_user_brand_permissions_brand_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_brand_permissions');
    }
};
