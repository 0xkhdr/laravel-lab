<?php

declare(strict_types=1);

namespace Raid\Catalog\Repositories\Cache;

use Raid\Catalog\Repositories\Contracts\BrandRepository;
use Raid\Pillar\Repositories\BaseRepositoryCache;

class BrandRepositoryCache extends BaseRepositoryCache implements BrandRepository
{
    public function getCachePrefix(): string
    {
        $guard = config('catalog.auth_guard', 'api');
        $userId = auth($guard)->id() ?? 'guest';

        $user = auth($guard)->user();
        $permissions = $user && method_exists($user, 'getCachedPermissions') ? $user->getCachedPermissions() : [];

        return parent::getCachePrefix().':'.$userId.':'.hash('sha256', serialize($permissions));
    }
}
