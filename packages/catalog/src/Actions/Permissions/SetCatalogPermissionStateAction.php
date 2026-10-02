<?php

declare(strict_types=1);

namespace Raid\Catalog\Actions\Permissions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Raid\Catalog\Enums\PermissionState;
use Raid\Pillar\Actions\BaseAction;

class SetCatalogPermissionStateAction extends BaseAction
{
    public function handle(Model $user, PermissionState $state): void
    {
        $connection = $user->getConnection();
        $connection->transaction(function () use ($connection, $user, $state): void {
            $connection->table('catalog_permission_states')->updateOrInsert(['user_id' => $user->getKey()], ['state' => $state->value]);
            $connection->afterCommit(fn () => Cache::forget($user->permissionCacheKey()));
        });
    }
}
