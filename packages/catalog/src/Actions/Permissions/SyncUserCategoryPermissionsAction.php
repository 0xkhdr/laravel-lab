<?php

declare(strict_types=1);

namespace Raid\Catalog\Actions\Permissions;

use Illuminate\Database\Eloquent\Model;

class SyncUserCategoryPermissionsAction
{
    public function handle(Model $user, array $categoryIds): void
    {
        SyncPermissions::handle($user, 'category', $categoryIds);
    }
}
