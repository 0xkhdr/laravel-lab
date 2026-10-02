<?php

declare(strict_types=1);

namespace Raid\Catalog\Repositories\Cache;

use Raid\Catalog\Repositories\Contracts\ProductRepository;
use Raid\Catalog\Repositories\ProductRepositoryEloquent;
use Raid\Pillar\Repositories\BaseRepositoryCache;

class ProductRepositoryCache extends BaseRepositoryCache implements ProductRepository
{
    public function getCachePrefix(): string
    {
        $guard = config('catalog.auth_guard', 'api');
        $userId = auth($guard)->id() ?? 'guest';

        $user = auth($guard)->user();
        $permissions = $user && method_exists($user, 'getCachedPermissions') ? $user->getCachedPermissions() : [];

        return parent::getCachePrefix().':'.$userId.':'.hash('sha256', serialize($permissions));
    }

    /**
     * @param  array<string>  $brandIds
     * @return array<string>
     */
    public function getBrandIdsWithProducts(array $brandIds): array
    {
        return $this->cached(
            'getBrandIdsWithProducts',
            ['brandIds' => $brandIds],
            function () use ($brandIds): array {
                /** @var ProductRepositoryEloquent $inner */
                $inner = $this->repository;

                return $inner->getBrandIdsWithProducts($brandIds);
            }
        );
    }

    /**
     * @param  array<string>  $categoryIds
     * @return array<string>
     */
    public function getCategoryIdsWithProducts(array $categoryIds): array
    {
        return $this->cached(
            'getCategoryIdsWithProducts',
            ['categoryIds' => $categoryIds],
            function () use ($categoryIds): array {
                /** @var ProductRepositoryEloquent $inner */
                $inner = $this->repository;

                return $inner->getCategoryIdsWithProducts($categoryIds);
            }
        );
    }

    /**
     * @param  array<string>  $regionIds
     * @return array<string>
     */
    public function getRegionIdsWithProducts(array $regionIds): array
    {
        return $this->cached(
            'getRegionIdsWithProducts',
            ['regionIds' => $regionIds],
            function () use ($regionIds): array {
                /** @var ProductRepositoryEloquent $inner */
                $inner = $this->repository;

                return $inner->getRegionIdsWithProducts($regionIds);
            }
        );
    }
}
