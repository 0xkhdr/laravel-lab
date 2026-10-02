<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_product_permissions', function (Blueprint $blueprint): void {
            // Composite PK: (user_id, product_id) is the natural identity of this row.
            // No synthetic UUID id — nothing references this row by its own key.
            $blueprint->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $blueprint->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();

            // Presence = allowed; absence = restricted.
            // SyncUserProductPermissionsAction deletes all rows then re-inserts allowed IDs.

            $blueprint->primary(['user_id', 'product_id'], 'pk_user_product_permissions');
            $blueprint->index('product_id', 'idx_user_product_permissions_product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_product_permissions');
    }
};
