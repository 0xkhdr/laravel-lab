<?php

declare(strict_types=1);

namespace Raid\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot row that grants a user access to a brand.
 *
 * Design: presence = allowed, absence = restricted.
 * There is no is_enabled flag — if a row exists the brand is accessible.
 * SyncUserBrandPermissionsAction atomically replaces all rows for a user:
 * delete everything, then insert one row per allowed brand_id.
 *
 * Primary key is the composite (user_id, brand_id) — no synthetic UUID id.
 * FK cascades handle cleanup when the user or brand is deleted.
 *
 * @property string $user_id
 * @property string $brand_id
 */
class UserBrandPermission extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    /** @var list<string> */
    protected $primaryKey = ['user_id', 'brand_id'];

    /** @var array<int, string> */
    protected $fillable = [
        'user_id',
        'brand_id',
    ];

    /**
     * @return BelongsTo<Model, UserBrandPermission>
     */
    public function user(): BelongsTo
    {
        /** @var class-string<Model> $userModel */
        $userModel = config('catalog.user_model');

        return $this->belongsTo($userModel);
    }

    /**
     * @return BelongsTo<Brand, UserBrandPermission>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
