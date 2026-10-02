<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Modules\Catalog\Models\CatalogUser;
use Raid\Catalog\Actions\Permissions\SetCatalogPermissionStateAction;
use Raid\Catalog\Enums\PermissionState;
use Raid\Catalog\Models\Brand;
use Raid\Catalog\Models\Category;

$root = __DIR__.'/consumer';
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();
if (getenv('PROOF_CATALOG_VERSION') && InstalledVersions::getPrettyVersion('raid/catalog') !== getenv('PROOF_CATALOG_VERSION')) {
    throw new RuntimeException('Unexpected copied Catalog version.');
}
if (InstalledVersions::isInstalled('raid/foundry') || class_exists(\Raid\Foundry\FoundryServiceProvider::class)) {
    throw new RuntimeException('Foundry must be absent from production.');
}
foreach (['pillar', 'catalog'] as $package) {
    $path = InstalledVersions::getInstallPath('raid/'.$package);
    if (is_link($path) || !str_starts_with(realpath($path), realpath($root.'/vendor'))) {
        throw new RuntimeException('Runtime package was not independently copied: '.$package);
    }
}
$user = CatalogUser::create(['name' => 'Production proof', 'email' => uniqid().'@example.test', 'password' => bin2hex(random_bytes(32))]);
$user->forceFill(['is_catalog_admin' => true])->save();
(new SetCatalogPermissionStateAction)->handle($user, PermissionState::Unrestricted);
$token = $user->createToken('production-proof')->plainTextToken;
$values = ['brand_id' => Brand::create(['name' => ['en' => 'Brand']])->id,
    'category_id' => Category::create(['name' => ['en' => 'Category']])->id,
    'sku' => 'PRODUCTION-'.bin2hex(random_bytes(4)), 'name' => ['en' => 'Production product', 'ar' => 'منتج']];
$kernel = $app->make(Kernel::class);
$request = Request::create('/api/v1/products', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$token], json_encode($values, JSON_THROW_ON_ERROR));
$response = $kernel->handle($request);
if ($response->getStatusCode() !== 201) {
    throw new RuntimeException('Production HTTP create failed: '.$response->getStatusCode().' '.$response->getContent());
}
$data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR)['data'];
if ($data['sku'] !== (getenv('PROOF_SKU_PREFIX') ?: '').$values['sku'] || $data['name']['ar'] !== 'منتج' || $data['brand']['id'] !== $values['brand_id']) {
    throw new RuntimeException('Production Catalog representation failed.');
}
if (getenv('PROOF_RESOURCE_MARKER') && ($data['application_marker'] ?? null) !== getenv('PROOF_RESOURCE_MARKER')) {
    throw new RuntimeException('Application-owned resource customization was lost.');
}
$kernel->terminate($request, $response);
auth()->forgetGuards();
$read = $kernel->handle(Request::create('/api/v1/products/'.$data['id'], 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$token]));
if ($read->getStatusCode() !== 200) {
    throw new RuntimeException('Production HTTP read failed.');
}
echo json_encode(['foundry_installed' => false, 'catalog_version' => InstalledVersions::getPrettyVersion('raid/catalog'), 'runtime_packages' => 'independent mirrored copies', 'http_create' => 201, 'http_read' => 200, 'sku' => $data['sku'], 'application_marker' => $data['application_marker'] ?? null, 'configuration_cached' => $app->configurationIsCached(), 'routes_cached' => $app->routesAreCached()], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
