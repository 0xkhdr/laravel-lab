<?php

declare(strict_types=1);

namespace Raid\Catalog\Repositories\Cache;

use Raid\Catalog\Repositories\CategoryRepositoryEloquent;
use Raid\Catalog\Repositories\Contracts\CategoryRepository;
use Raid\Pillar\Repositories\BaseRepositoryCache;

class CategoryRepositoryCache extends BaseRepositoryCache implements CategoryRepository
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
     * @param  array<string>  $parentIds
     * @return array<string>
     */
    public function getParentIdsWithSubCategories(array $parentIds): array
    {
        return $this->cached(
            'getParentIdsWithSubCategories',
            ['parentIds' => $parentIds],
            function () use ($parentIds): array {
                /** @var CategoryRepositoryEloquent $inner */
                $inner = $this->repository;

                return $inner->getParentIdsWithSubCategories($parentIds);
            }
        );
    }
}
