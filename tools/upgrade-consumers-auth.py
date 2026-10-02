import json
import shutil
from pathlib import Path

root = Path('/workspace')
source = root / 'consumer'
target = root / 'consumer-example'
for path in ['app-modules/catalog/src/Models/CatalogUser.php', 'app-modules/catalog/src/Providers/CatalogServiceProvider.php', 'app-modules/catalog/routes/catalog-routes.php', 'database/migrations/2026_01_01_000002_create_personal_access_tokens_table.php']:
    shutil.copy(source / path, target / path)
test = (source / 'app-modules/catalog/tests/HttpTest.php').read_text().replace("assertJsonPath('data.sku', 'http-001')", "assertJsonPath('data.sku', 'HTTP-001')")
(target / 'app-modules/catalog/tests/HttpTest.php').write_text(test)
for p in [target, source / 'app-modules/catalog', target / 'app-modules/catalog']:
    f = p / 'composer.json'
    c = json.loads(f.read_text())
    c['require']['laravel/sanctum'] = '^4.0'
    f.write_text(json.dumps(c, indent=4)+'\n')
f = root / 'packages/foundry/composer.json'
c = json.loads(f.read_text())
c['require-dev']['laravel/sanctum'] = '^4.0'
c['suggest']['laravel/sanctum'] = 'Install in the consumer for the supported bearer-token API blueprint.'
f.write_text(json.dumps(c, indent=4)+'\n')
