<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Models\CatalogUser;
use PHPUnit\Framework\Attributes\DataProvider;
use Raid\Catalog\Actions\Permissions\SetCatalogPermissionStateAction;
use Raid\Catalog\Actions\Permissions\SyncUserProductPermissionsAction;
use Raid\Catalog\Actions\Product\CreateProductAction;
use Raid\Catalog\Enums\PermissionState;
use Raid\Catalog\Models\Brand;
use Raid\Catalog\Models\Category;
use Tests\TestCase;

final class HttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_bearer_token_authentication_and_revocation(): void
    {
        $client = $this->user();
        $token = $client->createToken('catalog-test');
        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)->getJson('/api/v1/products')->assertOk();
        $token->accessToken->delete();
        auth()->forgetGuards();
        $this->getJson('/api/v1/products')->assertUnauthorized();
    }

    private function user(bool $admin = false): CatalogUser
    {
        $user = CatalogUser::create(['name' => 'Test', 'email' => uniqid().'@example.test', 'password' => 'password']);
        $user->forceFill(['is_catalog_admin' => $admin])->save();
        if ($admin) {
            (new SetCatalogPermissionStateAction)->handle($user, PermissionState::Unrestricted);
        }

        return $user;
    }

    private function productValues(): array
    {
        return ['sku' => 'http-001', 'name' => ['en' => 'HTTP Product', 'ar' => 'منتج'],
            'brand_id' => Brand::create(['name' => ['en' => 'Brand']])->id,
            'category_id' => Category::create(['name' => ['en' => 'Category']])->id];
    }

    public static function endpoints(): array
    {
        return [['brands'], ['categories'], ['regions'], ['products']];
    }

    #[DataProvider('endpoints')]
    public function test_all_four_crud_http_adapters(string $endpoint): void
    {
        $this->actingAs($this->user(true), 'web');
        $values = $endpoint === 'products' ? $this->productValues() : ['name' => ['en' => 'Original', 'ar' => 'أصلي']];
        $create = $this->postJson('/api/v1/'.$endpoint, $values)->assertCreated()->assertJsonPath('data.name.en', $values['name']['en']);
        $id = $create->json('data.id');
        $this->getJson('/api/v1/'.$endpoint)->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/'.$endpoint.'/'.$id)->assertOk()->assertJsonPath('data.id', $id);
        $this->patchJson('/api/v1/'.$endpoint.'/'.$id, ['name' => ['en' => 'Changed']])->assertOk()->assertJsonPath('data.name.en', 'Changed');
        $this->deleteJson('/api/v1/'.$endpoint.'/'.$id)->assertOk();
        $this->assertDatabaseMissing($endpoint, ['id' => $id]);
    }

    public function test_authentication_authorization_and_client_visibility(): void
    {
        $values = $this->productValues();
        $product = CreateProductAction::exec($values);
        $this->getJson('/api/v1/products')->assertUnauthorized();
        $client = $this->user();
        $this->actingAs($client)->getJson('/api/v1/products')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/api/v1/products', $values)->assertForbidden();
        $this->patchJson('/api/v1/products/'.$product->id, ['name' => ['en' => 'Denied']])->assertForbidden();
        $this->deleteJson('/api/v1/products/'.$product->id)->assertForbidden();
        (new SyncUserProductPermissionsAction)->handle($client, [$product->id]);
        $this->getJson('/api/v1/products/'.$product->id)->assertOk();
        (new SyncUserProductPermissionsAction)->handle($client, []);
        $this->getJson('/api/v1/products/'.$product->id)->assertNotFound();
    }

    public function test_input_validation_and_supplied_sku_consumer(): void
    {
        $this->actingAs($this->user(true));
        $this->postJson('/api/v1/products', ['sku' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors(['brand_id', 'category_id', 'name']);
        $values = $this->productValues();
        $this->postJson('/api/v1/products', $values)->assertCreated()->assertJsonPath('data.sku', 'http-001');
        $this->postJson('/api/v1/products', $values)->assertUnprocessable()->assertJsonValidationErrors('sku');
    }
}
