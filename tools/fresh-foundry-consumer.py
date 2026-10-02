import json
import shutil
from pathlib import Path

root = Path('/workspace')
source = root / 'consumer'
target = root / 'consumer-foundry'
if target.exists():
    shutil.rmtree(target)  # Disposable acceptance consumer created by this script.
shutil.copytree(source, target, ignore=shutil.ignore_patterns('vendor', 'app-modules', '.foundry', '.env', '*.sqlite', '.git', '.phpunit.cache', 'logs', 'cache'), dirs_exist_ok=True)
for directory in ['bootstrap/cache', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs']:
    (target / directory).mkdir(parents=True, exist_ok=True)
(target / '.env').write_text((source / '.env.example').read_text() + '\nDB_DATABASE=/workspace/consumer-foundry/database/database.sqlite\nCACHE_PREFIX=pillar_foundry_fresh\nREDIS_PREFIX=pillar_foundry_fresh_\n')
(target / 'database/database.sqlite').touch()
f = target / 'composer.json'
c = json.loads(f.read_text())
c['autoload']['psr-4'].pop('Modules\\Catalog\\', None)
f.write_text(json.dumps(c, indent=4)+'\n')
(target / 'bootstrap/providers.php').write_text("<?php\n\nuse App\\Providers\\AppServiceProvider;\n\nreturn [\n    AppServiceProvider::class,\n];\n")
