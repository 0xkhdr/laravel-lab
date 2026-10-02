<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_category_permissions', function (Blueprint $blueprint): void {
            // Composite PK: (user_id, category_id) is the natural identity of this row.
            // No synthetic UUID id — nothing references this row by its own key.
            $blueprint->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $blueprint->foreignUuid('category_id')->constrained('categories')->cascadeOnDelete();

            // Presence = allowed; absence = restricted.
            // SyncUserCategoryPermissionsAction deletes all rows then re-inserts allowed IDs.

            $blueprint->primary(['user_id', 'category_id'], 'pk_user_category_permissions');
            $blueprint->index('category_id', 'idx_user_category_permissions_category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_category_permissions');
    }
};
