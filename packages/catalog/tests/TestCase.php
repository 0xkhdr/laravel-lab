<?php

declare(strict_types=1);

namespace Raid\Catalog\Tests;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Orchestra\Testbench\TestCase as Orchestra;
use Raid\Catalog\CatalogServiceProvider;
use Raid\Catalog\Contracts\SkuPolicy;
use Raid\Catalog\Traits\HasCatalogPermissions;
use Raid\Pillar\PillarServiceProvider;

class CatalogUser extends User
{
    use HasCatalogPermissions;
    use HasUuids;

    protected $table = 'users';

    protected $guarded = [];

    public function catalogPermissionParent(): ?Model
    {
        return $this->parent_id ? static::find($this->parent_id) : null;
    }
}

class MissingAdapterUser extends User
{
    use HasUuids;

    protected $table = 'users';

    protected $guarded = [];
}

class NormalizedSku implements SkuPolicy
{
    public function apply(string $sku): string
    {
        $sku = strtoupper(trim($sku));
        if (! preg_match('/^[A-Z0-9-]+$/', $sku)) {
            throw ValidationException::withMessages(['sku' => 'Use letters, numbers and hyphens.']);
        }

        return $sku;
    }
}

class MalformedAdapterUser extends MissingAdapterUser
{
    public function getCachedPermissions(): array
    {
        return [];
    }
}

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [PillarServiceProvider::class, CatalogServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]);
        $app['config']->set('catalog.user_model', CatalogUser::class);
        $app['config']->set('catalog.auth_guard', 'web');
        $app['config']->set('auth.providers.users.model', CatalogUser::class);
        $app['config']->set('cache.default', 'array');
    }

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('parent_id')->nullable();
            $table->string('name');
            $table->timestamps();
        });
        foreach (['brands', 'categories', 'regions', 'products', 'user_brand_permissions', 'user_category_permissions', 'user_product_permissions', 'catalog_permission_states'] as $table) {
            (require __DIR__.'/../database/migrations/create_'.$table.'_table.php')->up();
        }
    }
}
