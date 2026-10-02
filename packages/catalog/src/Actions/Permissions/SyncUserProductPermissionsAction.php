<?php

declare(strict_types=1);

namespace Raid\Catalog\Actions\Permissions;

use Illuminate\Database\Eloquent\Model;

class SyncUserProductPermissionsAction
{
    public function handle(Model $user, array $productIds): void
    {
        SyncPermissions::handle($user, 'product', $productIds);
    }
}
