<?php

declare(strict_types=1);

namespace Raid\Catalog\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Raid\Catalog\Enums\PermissionState;
use Raid\Catalog\Models\UserBrandPermission;
use Raid\Catalog\Models\UserCategoryPermission;
use Raid\Catalog\Models\UserProductPermission;

trait HasCatalogPermissions
{
    public function brandPermissions(): HasMany
    {
        return $this->hasMany(UserBrandPermission::class, 'user_id');
    }

    public function categoryPermissions(): HasMany
    {
        return $this->hasMany(UserCategoryPermission::class, 'user_id');
    }

    public function productPermissions(): HasMany
    {
        return $this->hasMany(UserProductPermission::class, 'user_id');
    }

    /** Applications override this hook for explicit inheritance. */
    public function catalogPermissionParent(): ?Model
    {
        return null;
    }

    public function getEffectiveBrandPermissions(): ?Collection
    {
        $ids = $this->getCachedPermissions()['brand_ids'];

        return $ids === null ? null : collect($ids);
    }

    public function getEffectiveCategoryPermissions(): ?Collection
    {
        $ids = $this->getCachedPermissions()['category_ids'];

        return $ids === null ? null : collect($ids);
    }

    public function getEffectiveProductPermissions(): ?Collection
    {
        $ids = $this->getCachedPermissions()['product_ids'];

        return $ids === null ? null : collect($ids);
    }

    public function permissionCacheKey(): string
    {
        return 'catalog:permissions:'.$this->getConnection()->getName().':'.$this->getTable().':'.$this->getKey();
    }

    /** null sets mean explicitly unrestricted; empty sets deny that axis. */
    public function getCachedPermissions(array $visited = []): array
    {
        $key = $this->permissionCacheKey();
        $denied = ['brand_ids' => [], 'category_ids' => [], 'product_ids' => []];
        if (in_array($key, $visited, true)) {
            return $denied;
        }
        $visited[] = $key;
        $load = function (): array {
            return [
                'state' => $this->getConnection()->table('catalog_permission_states')->where('user_id', $this->getKey())->value('state') ?? PermissionState::Unconfigured->value,
                'brand_ids' => $this->brandPermissions()->pluck('brand_id')->all(),
                'category_ids' => $this->categoryPermissions()->pluck('category_id')->all(),
                'product_ids' => $this->productPermissions()->pluck('product_id')->all(),
            ];
        };
        $raw = $this->getConnection()->transactionLevel() > 0
            ? $load()
            : Cache::remember($key, (int) config('catalog.permission_cache_ttl', 900), $load);
        $state = PermissionState::from($raw['state']);
        if ($state === PermissionState::Inherited) {
            $parent = $this->catalogPermissionParent();

            return $parent && method_exists($parent, 'getCachedPermissions')
                ? $parent->getCachedPermissions($visited) : $denied;
        }

        return match ($state) {
            PermissionState::Unrestricted => array_fill_keys(array_keys($denied), null),
            PermissionState::Restricted => array_intersect_key($raw, $denied),
            default => $denied,
        };
    }
}
