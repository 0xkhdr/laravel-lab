<?php

declare(strict_types=1);

namespace Raid\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot row that grants a user access to a product.
 *
 * Design: presence = allowed, absence = restricted.
 * There is no is_enabled flag — if a row exists the product is accessible.
 * SyncUserProductPermissionsAction atomically replaces all rows for a user:
 * delete everything, then insert one row per allowed product_id.
 *
 * Primary key is the composite (user_id, product_id) — no synthetic UUID id.
 * FK cascades handle cleanup when the user or product is deleted.
 *
 * @property string $user_id
 * @property string $product_id
 */
class UserProductPermission extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    /** @var list<string> */
    protected $primaryKey = ['user_id', 'product_id'];

    /** @var array<int, string> */
    protected $fillable = [
        'user_id',
        'product_id',
    ];

    /**
     * @return BelongsTo<Model, UserProductPermission>
     */
    public function user(): BelongsTo
    {
        /** @var class-string<Model> $userModel */
        $userModel = config('catalog.user_model');

        return $this->belongsTo($userModel);
    }

    /**
     * @return BelongsTo<Product, UserProductPermission>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
