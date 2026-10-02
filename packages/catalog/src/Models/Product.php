<?php

declare(strict_types=1);

namespace Raid\Catalog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Raid\Catalog\Database\Factories\ProductFactory;
use Raid\Catalog\Support\CatalogAccess;
use Spatie\Translatable\HasTranslations;

/**
 * Minimal core product. Contains only domain-agnostic fields.
 * Card-specific concerns (stock_mode, face_value, denomination, sales_channels,
 * instructions, cost_price, etc.) are intentionally absent — extend this model
 * in the consuming application.
 *
 * Columns: id (UUID), sku, name (translatable), description (translatable),
 *          brand_id, category_id, region_id (nullable), is_active, sort_order.
 */
class Product extends Model
{
    use HasFactory;
    use HasTranslations;
    use HasUuids;

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }

    /** @var array<int, string> */
    public array $translatable = ['name', 'description'];

    /** @var array<int, string> */
    protected $fillable = [
        'brand_id',
        'category_id',
        'region_id',
        'sku',
        'name',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * @return BelongsTo<Brand, Product>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return BelongsTo<Category, Product>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Region, Product>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * @return HasMany<UserProductPermission, Product>
     */
    public function userPermissions(): HasMany
    {
        return $this->hasMany(UserProductPermission::class);
    }

    public function scopeAccessibleByUser(Builder $builder): Builder
    {
        return CatalogAccess::apply($builder, 'product');
    }
}
