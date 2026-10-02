<?php

declare(strict_types=1);

namespace Raid\Catalog\Actions\Permissions;

use Illuminate\Database\Eloquent\Model;

class SyncUserBrandPermissionsAction
{
    public function handle(Model $user, array $brandIds): void
    {
        SyncPermissions::handle($user, 'brand', $brandIds);
    }
}
