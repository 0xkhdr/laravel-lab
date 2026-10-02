<?php

declare(strict_types=1);

namespace Raid\Catalog;

use Illuminate\Support\ServiceProvider;
use Raid\Catalog\Contracts\SkuPolicy;
use Raid\Catalog\Models\Brand;
use Raid\Catalog\Models\Category;
use Raid\Catalog\Models\Product;
use Raid\Catalog\Models\Region;
use Raid\Catalog\Repositories\BrandRepositoryEloquent;
use Raid\Catalog\Repositories\Cache\BrandRepositoryCache;
use Raid\Catalog\Repositories\Cache\CategoryRepositoryCache;
use Raid\Catalog\Repositories\Cache\ProductRepositoryCache;
use Raid\Catalog\Repositories\CategoryRepositoryEloquent;
use Raid\Catalog\Repositories\Contracts\BrandRepository;
use Raid\Catalog\Repositories\Contracts\CategoryRepository;
use Raid\Catalog\Repositories\Contracts\ProductRepository;
use Raid\Catalog\Repositories\Contracts\RegionRepository;
use Raid\Catalog\Repositories\ProductRepositoryEloquent;
use Raid\Catalog\Repositories\RegionRepositoryEloquent;
use Raid\Catalog\Support\SuppliedSku;

/** Runtime configuration and default repository/SKU bindings only. */
class CatalogServiceProvider extends ServiceProvider
{
    /**
     * Register package services into the IoC container.
     *
     * Only bindings and config merges live here — no events, routes, or
     * commands are registered at this stage.
     */
    public function register(): void
    {
        $this->app->bindIf(SkuPolicy::class, SuppliedSku::class);
        $this->mergeConfigFrom(
            __DIR__.'/../config/catalog.php',
            'catalog',
        );

        // Cache-decorated repository bindings.
        // bindIf() allows module-level service providers to override before
        // this package provider runs.
        $this->app->bindIf(
            BrandRepository::class,
            fn (): BrandRepositoryCache => new BrandRepositoryCache(
                new BrandRepositoryEloquent(new Brand)
            ),
        );

        $this->app->bindIf(
            CategoryRepository::class,
            fn (): CategoryRepositoryCache => new CategoryRepositoryCache(
                new CategoryRepositoryEloquent(new Category)
            ),
        );

        $this->app->bindIf(
            ProductRepository::class,
            fn (): ProductRepositoryCache => new ProductRepositoryCache(
                new ProductRepositoryEloquent(new Product)
            ),
        );

        // Region changes infrequently — a plain Eloquent repository is sufficient.
        $this->app->bindIf(
            RegionRepository::class,
            fn (): RegionRepositoryEloquent => new RegionRepositoryEloquent(new Region),
        );
    }
}
