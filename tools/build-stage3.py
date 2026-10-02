import json
from pathlib import Path

r = Path('/reference')
c = Path('/workspace/consumer')
m = c / 'app-modules/catalog'
for kind in ['Controllers', 'Requests', 'Resources']:
    for f in (r / 'app-modules/catalog/src/Http' / kind).rglob('*.php'):
        if 'Permissions' in str(f) or 'CatalogPermissionsController' in f.name:
            continue
        dest = m / 'src/Http' / kind / f.relative_to(r / 'app-modules/catalog/src/Http' / kind)
        dest.parent.mkdir(parents=True, exist_ok=True)
        s = f.read_text().replace('Modules\\Catalog\\Actions', 'Raid\\Catalog\\Actions').replace('Raid\\Foundation\\CatalogFoundation', 'Raid\\Catalog')
        dest.write_text(s)

routes = "<?php\n\ndeclare(strict_types=1);\n\nuse Illuminate\\Support\\Facades\\Route;\n"
for entity in ['Brand', 'Category', 'Product', 'Region']:
    routes += f'use Modules\\Catalog\\Http\\Controllers\\{entity}Controller;\n'
routes += "\nRoute::prefix('api/v1')->middleware(['api', 'auth:web'])->group(function (): void {\n"
for entity in ['Brand', 'Category', 'Product', 'Region']:
    plural = {'Category': 'categories'}.get(entity, entity.lower()+'s')
    for method, suffix, handler, ability in [('get', '', 'index', 'read'), ('post', '', 'store', 'write'), ('get', '/{id}', 'show', 'read'), ('patch', '/{id}', 'update', 'write'), ('delete', '/{id}', 'destroy', 'write')]:
        routes += f"    Route::{method}('/{plural}{suffix}', [{entity}Controller::class, '{handler}'])->middleware('can:catalog.{ability}')->name('catalog.{plural}.{handler}');\n"
routes += '});\n'
(m / 'routes').mkdir(exist_ok=True)
(m / 'routes/catalog-routes.php').write_text(routes)
manifest = {'name': 'modules/catalog', 'description': 'Application Catalog integration', 'type': 'library', 'license': 'proprietary', 'require': {'raid/catalog': 'dev-main', 'raid/pillar': 'dev-main'}, 'autoload': {'psr-4': {'Modules\\Catalog\\': 'src/'}}}
(m / 'composer.json').write_text(json.dumps(manifest, indent=4)+'\n')

tables = ['brands', 'categories', 'regions', 'products', 'user_brand_permissions', 'user_category_permissions', 'user_product_permissions', 'catalog_permission_states']
(m / 'database/migrations').mkdir(parents=True, exist_ok=True)
for i, table in enumerate(tables):
    src = Path('/workspace/packages/catalog/database/migrations') / f'create_{table}_table.php'
    (m / 'database/migrations' / f'2026_01_02_00000{i}_create_{table}_table.php').write_text(src.read_text())

f = c / 'composer.json'
config = json.loads(f.read_text())
config['autoload']['psr-4']['Modules\\Catalog\\'] = 'app-modules/catalog/src/'
f.write_text(json.dumps(config, indent=4)+'\n')
f = c / 'bootstrap/providers.php'
s = f.read_text()
assert '    AppServiceProvider::class,' in s, 'Missing provider registration anchor'
s = s.replace('    AppServiceProvider::class,', '    AppServiceProvider::class,\n    Modules\\Catalog\\Providers\\CatalogServiceProvider::class,')
f.write_text(s)
f = c / 'database/migrations/0001_01_01_000000_create_users_table.php'
s = f.read_text().replace("$table->id();", "$table->uuid('id')->primary();", 1)
f.write_text(s)

# Discover application-owned module tests in the consumer harness.
f = c / 'phpunit.xml'
s = f.read_text().replace('<directory>tests/Feature</directory>', '<directory>tests/Feature</directory>\n            <directory>app-modules/catalog/tests</directory>')
f.write_text(s)
