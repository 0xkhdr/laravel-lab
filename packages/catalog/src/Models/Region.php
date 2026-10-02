<?php

declare(strict_types=1);

namespace Raid\Catalog\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Raid\Catalog\Database\Factories\RegionFactory;
use Spatie\Translatable\HasTranslations;

class Region extends Model
{
    use HasFactory;
    use HasTranslations;
    use HasUuids;

    protected static function newFactory(): RegionFactory
    {
        return RegionFactory::new();
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
     * @return HasMany<Product, Region>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
