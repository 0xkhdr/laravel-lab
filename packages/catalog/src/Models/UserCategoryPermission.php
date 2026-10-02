<?php

declare(strict_types=1);

namespace Raid\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot row that grants a user access to a category.
 *
 * Design: presence = allowed, absence = restricted.
 * There is no is_enabled flag — if a row exists the category is accessible.
 * SyncUserCategoryPermissionsAction atomically replaces all rows for a user:
 * delete everything, then insert one row per allowed category_id.
 *
 * Primary key is the composite (user_id, category_id) — no synthetic UUID id.
 * FK cascades handle cleanup when the user or category is deleted.
 *
 * @property string $user_id
 * @property string $category_id
 */
class UserCategoryPermission extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    /** @var list<string> */
    protected $primaryKey = ['user_id', 'category_id'];

    /** @var array<int, string> */
    protected $fillable = [
        'user_id',
        'category_id',
    ];

    /**
     * @return BelongsTo<Model, UserCategoryPermission>
     */
    public function user(): BelongsTo
    {
        /** @var class-string<Model> $userModel */
        $userModel = config('catalog.user_model');

        return $this->belongsTo($userModel);
    }

    /**
     * @return BelongsTo<Category, UserCategoryPermission>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
