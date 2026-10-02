<?php

declare(strict_types=1);

namespace Raid\Catalog\Actions\Permissions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Raid\Catalog\Enums\PermissionState;

final class SyncPermissions
{
    public static function handle(Model $user, string $type, array $ids): void
    {
        Validator::make(['ids' => $ids], ['ids' => 'array', 'ids.*' => 'required|uuid|distinct'])->validate();
        $connection = $user->getConnection();
        $connection->transaction(function () use ($connection, $user, $type, $ids): void {
            $relation = $user->{$type.'Permissions'}();
            $relation->delete();
            if ($ids !== []) {
                $relation->insert(array_map(fn (string $id): array => ['user_id' => $user->getKey(), $type.'_id' => $id], $ids));
            }
            $connection->table('catalog_permission_states')->updateOrInsert(['user_id' => $user->getKey()], ['state' => PermissionState::Restricted->value]);
            $connection->afterCommit(fn () => Cache::forget($user->permissionCacheKey()));
        });
    }
}
