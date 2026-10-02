<?php

declare(strict_types=1);

namespace Raid\Catalog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Raid\Catalog\Database\Factories\CategoryFactory;
use Raid\Catalog\Support\CatalogAccess;
use Spatie\Translatable\HasTranslations;

class Category extends Model
{
    use HasFactory;
    use HasTranslations;
    use HasUuids;

    protected static function newFactory(): CategoryFactory
    {
        return CategoryFactory::new();
    }

    /** @var array<int, string> */
    public array $translatable = ['name'];

    /** @var array<int, string> */
    protected $fillable = [
        'parent_id',
        'name',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * @return BelongsTo<Category, Category>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * @return HasMany<Product, Category>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return HasMany<UserCategoryPermission, Category>
     */
    public function userPermissions(): HasMany
    {
        return $this->hasMany(UserCategoryPermission::class);
    }

    public function scopeAccessibleByUser(Builder $builder): Builder
    {
        return CatalogAccess::apply($builder, 'category');
    }
}
