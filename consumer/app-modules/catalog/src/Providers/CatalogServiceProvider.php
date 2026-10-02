<?php

declare(strict_types=1);

namespace Modules\Catalog\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Catalog\Models\CatalogUser;
use Modules\Catalog\Policies\ApplicationSku;
use Raid\Catalog\Contracts\SkuPolicy;

class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SkuPolicy::class, ApplicationSku::class);
        config()->set('auth.providers.users.model', CatalogUser::class);
        config()->set('catalog.user_model', CatalogUser::class);
        config()->set('catalog.auth_guard', 'sanctum');
    }

    public function boot(): void
    {
        Gate::define('catalog.write', fn (CatalogUser $user): bool => (bool) $user->is_catalog_admin);
        Gate::define('catalog.read', fn (CatalogUser $user): bool => true);
    }
}
