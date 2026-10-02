<?php

declare(strict_types=1);

namespace Raid\Catalog\Support;

use Illuminate\Database\Eloquent\Builder;

final class CatalogAccess
{
    public static function apply(Builder $builder, string $entity = 'product'): Builder
    {
        $user = auth(config('catalog.auth_guard', 'web'))->user();
        if (! $user || ! method_exists($user, 'getCachedPermissions')) {
            return $builder->whereRaw('1 = 0');
        }
        $sets = $user->getCachedPermissions();
        if (! is_array($sets)) {
            return $builder->whereRaw('1 = 0');
        }
        foreach (['brand_ids', 'category_ids', 'product_ids'] as $key) {
            if (! array_key_exists($key, $sets) || ($sets[$key] !== null && ! is_array($sets[$key]))) {
                return $builder->whereRaw('1 = 0');
            }
        }
        if ($sets['brand_ids'] === null && $sets['category_ids'] === null && $sets['product_ids'] === null) {
            return $builder;
        }
        if ($sets['brand_ids'] === null || $sets['category_ids'] === null || $sets['product_ids'] === null) {
            return $builder->whereRaw('1 = 0');
        }
        if ($entity !== 'product') {
            return $builder->whereIn($builder->getModel()->qualifyColumn('id'), $sets[$entity.'_ids']);
        }

        return $builder->where(function (Builder $query) use ($sets): void {
            $query->whereIn('products.brand_id', $sets['brand_ids'])
                ->orWhereIn('products.category_id', $sets['category_ids'])
                ->orWhereIn('products.id', $sets['product_ids']);
        });
    }
}
