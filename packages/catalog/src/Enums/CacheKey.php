<?php

declare(strict_types=1);

namespace Raid\Catalog\Enums;

/**
 * Redis cache key prefixes used by catalog repository cache decorators.
 *
 * Centralising these as an enum prevents typos and makes it easy to track
 * every cache namespace the package owns.
 */
enum CacheKey: string
{
    case BrandPrefix = 'catalog_brands';
    case CategoryPrefix = 'catalog_categories';
    case ProductPrefix = 'catalog_products';
    case RegionPrefix = 'catalog_regions';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
