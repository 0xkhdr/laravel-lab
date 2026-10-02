<?php

declare(strict_types=1);

namespace Raid\Catalog\Tests;

use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Raid\Catalog\Actions\Permissions\SetCatalogPermissionStateAction;
use Raid\Catalog\Actions\Permissions\SyncUserBrandPermissionsAction;
use Raid\Catalog\Actions\Permissions\SyncUserProductPermissionsAction;
use Raid\Catalog\Actions\Product\CreateProductAction;
use Raid\Catalog\Actions\Product\RetrievePaginateProductAction;
use Raid\Catalog\CatalogServiceProvider;
use Raid\Catalog\Contracts\SkuPolicy;
use Raid\Catalog\Enums\PermissionState;
use Raid\Catalog\Models\Brand;
use Raid\Catalog\Models\Category;
use Raid\Catalog\Models\Product;
use Raid\Catalog\Models\Region;
use Raid\Catalog\Repositories\Contracts\ProductRepository;

final class CatalogTest extends TestCase
{
    public function test_permission_changes_rollback_with_outer_transaction(): void
    {
        $product = CreateProductAction::exec($this->values());
        $user = CatalogUser::create(['name' => 'Client']);
        (new SyncUserProductPermissionsAction)->handle($user, [$product->id]);
        $this->assertSame([$product->id], $user->getEffectiveProductPermissions()->all());
        try {
            $user->getConnection()->transaction(function () use ($user): void {
                (new SyncUserProductPermissionsAction)->handle($user, []);
                $this->assertSame([], $user->getEffectiveProductPermissions()->all());
                throw new \DomainException;
            });
        } catch (\DomainException) {
        }
        $this->assertSame([$product->id], $user->getEffectiveProductPermissions()->all());
    }

    private function values(string $sku = 'test-001'): array
    {
        return ['sku' => $sku, 'name' => ['en' => 'Product', 'ar' => 'منتج'],
            'brand_id' => Brand::create(['name' => ['en' => 'Brand']])->id,
            'category_id' => Category::create(['name' => ['en' => 'Category']])->id,
            'region_id' => Region::create(['name' => ['en' => 'Region']])->id];
    }

    public static function entities(): array
    {
        return [['Brand'], ['Category'], ['Region'], ['Product']];
    }

    #[DataProvider('entities')]
    public function test_translated_crud_and_relationships(string $entity): void
    {
        $user = CatalogUser::create(['name' => 'Administrator']);
        (new SetCatalogPermissionStateAction)->handle($user, PermissionState::Unrestricted);
        $this->actingAs($user);
        $values = $entity === 'Product' ? $this->values() : ['name' => ['en' => 'Original', 'ar' => 'أصلي']];
        $action = 'Raid\\Catalog\\Actions\\'.$entity.'\\Create'.$entity.'Action';
        $model = $action::exec($values);
        $this->assertSame($values['name'], $model->getTranslations('name'));
        $find = 'Raid\\Catalog\\Actions\\'.$entity.'\\Find'.$entity.'Action';
        $this->assertSame($model->id, $find::exec(['id' => $model->id])->id);
        $update = 'Raid\\Catalog\\Actions\\'.$entity.'\\Update'.$entity.'Action';
        $updated = $update::exec($model->id, ['name' => ['en' => 'Changed'], 'sort_order' => 2]);
        $this->assertSame('Changed', $updated->getTranslation('name', 'en'));
        $this->assertSame(2, $updated->sort_order);
        $list = 'Raid\\Catalog\\Actions\\'.$entity.'\\RetrievePaginate'.$entity.'Action';
        $this->assertCount(1, $list::exec(perPage: -1));
        if ($entity === 'Product') {
            $this->assertSame($values['brand_id'], $model->brand->id);
            $this->assertSame($values['category_id'], $model->category->id);
            $this->assertSame($values['region_id'], $model->region->id);
        }
        $delete = 'Raid\\Catalog\\Actions\\'.$entity.'\\Delete'.$entity.'Action';
        $this->assertSame(1, $delete::exec([$model->id]));
        $this->assertDatabaseMissing($model->getTable(), ['id' => $model->id]);
    }

    public function test_two_sku_configurations_and_override_provider_orders(): void
    {
        $this->assertSame('supplied-001', CreateProductAction::exec($this->values('supplied-001'))->sku);
        $this->app->bind(SkuPolicy::class, NormalizedSku::class);
        (new CatalogServiceProvider($this->app))->register();
        $this->assertSame('NORMAL-002', CreateProductAction::exec($this->values('normal-002'))->sku);
        $this->app->bind(SkuPolicy::class, NormalizedSku::class);
        $this->assertSame('NORMAL-003', $this->app->make(CreateProductAction::class)->handle($this->values('normal-003'))->sku);
        $this->expectException(ValidationException::class);
        CreateProductAction::exec($this->values('bad sku'));
    }

    public function test_duplicate_and_missing_sku_are_rejected(): void
    {
        $values = $this->values();
        CreateProductAction::exec($values);
        try {
            CreateProductAction::exec($values);
            $this->fail('Duplicate SKU accepted');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('sku', $error->errors());
        }
        unset($values['sku']);
        $this->expectException(ValidationException::class);
        CreateProductAction::exec($values);
    }

    public function test_union_grant_clear_and_explicit_states(): void
    {
        $first = CreateProductAction::exec($this->values('one'));
        CreateProductAction::exec($this->values('two'));
        $user = CatalogUser::create(['name' => 'Client']);
        $this->actingAs($user);
        $this->assertSame(0, Product::accessibleByUser()->count());
        foreach ([['Brand', $first->brand_id], ['Category', $first->category_id], ['Product', $first->id]] as [$type, $id]) {
            $action = 'Raid\\Catalog\\Actions\\Permissions\\SyncUser'.$type.'PermissionsAction';
            (new $action)->handle($user, [$id]);
            $this->assertSame([$first->id], Product::accessibleByUser()->pluck('id')->all());
            (new $action)->handle($user, []);
            $this->assertSame(0, Product::accessibleByUser()->count());
        }
        (new SyncUserBrandPermissionsAction)->handle($user, [$first->brand_id]);
        (new SyncUserProductPermissionsAction)->handle($user, []);
        $this->assertSame(1, Product::accessibleByUser()->count());
        (new SetCatalogPermissionStateAction)->handle($user, PermissionState::Denied);
        $this->assertSame(0, Product::accessibleByUser()->count());
        (new SetCatalogPermissionStateAction)->handle($user, PermissionState::Unrestricted);
        $this->assertSame(2, Product::accessibleByUser()->count());
        (new SetCatalogPermissionStateAction)->handle($user, PermissionState::Unconfigured);
        $this->assertSame(0, Product::accessibleByUser()->count());
    }

    public function test_guests_and_missing_adapter_fail_closed(): void
    {
        CreateProductAction::exec($this->values());
        $this->assertSame(0, Product::accessibleByUser()->count());
        $this->actingAs(MissingAdapterUser::create(['name' => 'Missing']));
        $this->assertSame(0, Product::accessibleByUser()->count());
        $this->assertSame(0, Brand::accessibleByUser()->count());
        $this->assertSame(0, Category::accessibleByUser()->count());
        $this->actingAs(MalformedAdapterUser::create(['name' => 'Malformed']));
        $this->assertSame(0, Product::accessibleByUser()->count());
        $this->assertSame(0, Brand::accessibleByUser()->count());
        $this->assertSame(0, Category::accessibleByUser()->count());
    }

    public function test_explicit_inheritance_revocation_and_cycles(): void
    {
        $product = CreateProductAction::exec($this->values());
        $parent = CatalogUser::create(['name' => 'Parent']);
        $child = CatalogUser::create(['name' => 'Child', 'parent_id' => $parent->id]);
        (new SetCatalogPermissionStateAction)->handle($child, PermissionState::Inherited);
        (new SyncUserProductPermissionsAction)->handle($parent, [$product->id]);
        $this->actingAs($child);
        $this->assertSame(1, Product::accessibleByUser()->count());
        (new SyncUserProductPermissionsAction)->handle($parent, []);
        $this->assertSame(0, Product::accessibleByUser()->count());
        $parent->update(['parent_id' => $child->id]);
        (new SetCatalogPermissionStateAction)->handle($parent, PermissionState::Inherited);
        $this->assertSame(0, Product::accessibleByUser()->count());
    }

    public function test_real_cache_user_isolation_mutation_and_permission_revocation(): void
    {
        $this->app->instance('env', 'cache-proof');
        config()->set('database.redis.client', 'phpredis');
        config()->set('database.redis.default', ['host' => 'redis', 'port' => 6379, 'database' => 2]);
        config()->set('cache.stores.redis', ['driver' => 'redis', 'connection' => 'default']);
        config()->set('cache.default', 'redis');
        config()->set('cache.prefix', 'catalog-test-'.bin2hex(random_bytes(8)));
        config()->set('repository-cache.driver', 'redis');
        $first = CreateProductAction::exec($this->values('one'));
        $second = CreateProductAction::exec($this->values('two'));
        $a = CatalogUser::create(['name' => 'A']);
        $b = CatalogUser::create(['name' => 'B']);
        (new SyncUserProductPermissionsAction)->handle($a, [$first->id]);
        (new SyncUserProductPermissionsAction)->handle($b, [$second->id]);
        $repo = $this->app->make(ProductRepository::class);
        $action = new RetrievePaginateProductAction($repo);
        $this->actingAs($a);
        $this->assertSame([$first->id], $action->handle(perPage: -1)->pluck('id')->all());
        $this->actingAs($b);
        $this->assertSame([$second->id], $action->handle(perPage: -1)->pluck('id')->all());
        $repo->updateByIdOrFail($first->id, ['sku' => 'changed']);
        $this->actingAs($a);
        $this->assertSame('changed', $action->handle(perPage: -1)->first()->sku);
        (new SyncUserProductPermissionsAction)->handle($a, []);
        $this->assertCount(0, $action->handle(perPage: -1));
        $repo->clearCache();
    }
}
