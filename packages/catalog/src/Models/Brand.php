<?php

declare(strict_types=1);

namespace Raid\Catalog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Raid\Catalog\Database\Factories\BrandFactory;
use Raid\Catalog\Support\CatalogAccess;
use Spatie\Translatable\HasTranslations;

class Brand extends Model
{
    use HasFactory;
    use HasTranslations;
    use HasUuids;

    protected static function newFactory(): BrandFactory
    {
        return BrandFactory::new();
    }

    /** @var array<int, string> */
    public array $translatable = ['name'];

    /** @var array<int, string> */
    protected $fillable = [
        'name',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * @return HasMany<Product, Brand>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return HasMany<UserBrandPermission, Brand>
     */
    public function userPermissions(): HasMany
    {
        return $this->hasMany(UserBrandPermission::class);
    }

    public function scopeAccessibleByUser(Builder $builder): Builder
    {
        return CatalogAccess::apply($builder, 'brand');
    }
}
