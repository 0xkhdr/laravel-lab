import json
import shutil
from pathlib import Path

root = Path('/workspace')
build = root / 'build'
consumer = build / 'consumer'
source = root / 'consumer-foundry'
if build.exists():
    shutil.rmtree(build)  # Disposable acceptance build; never the development consumer.
shutil.copytree(source, consumer, ignore=shutil.ignore_patterns('vendor', '.env', '*.sqlite', '.git', '.foundry', '.phpunit.cache', 'logs', 'cache'))
for directory in ['bootstrap/cache', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs']:
    (consumer / directory).mkdir(parents=True, exist_ok=True)
for name in ['pillar', 'catalog', 'foundry']:
    package = build / 'packages' / name
    shutil.copytree(root / 'packages' / name, package, ignore=shutil.ignore_patterns('vendor', 'tests', 'composer.lock', '.phpunit.cache', '.git'))
    if name != 'pillar':
        f = package / 'composer.json'
        metadata = json.loads(f.read_text())
        metadata['require']['raid/pillar'] = '^0.1'
        f.write_text(json.dumps(metadata, indent=4)+'\n')
f = consumer / 'composer.json'
manifest = json.loads(f.read_text())
manifest['repositories'][0]['options']['symlink'] = False
manifest['repositories'][0]['options']['versions'].update({'raid/pillar': '0.1.0', 'raid/catalog': '0.1.0'})
manifest['require'].update({'raid/pillar': '^0.1', 'raid/catalog': '^0.1'})
f.write_text(json.dumps(manifest, indent=4)+'\n')
(consumer / '.env').write_text('''APP_NAME=PillarProductionProof
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://localhost
DB_CONNECTION=sqlite
DB_DATABASE=/release/consumer/database/database.sqlite
CACHE_STORE=redis
CACHE_PREFIX=pillar_production_proof
REDIS_HOST=redis
REDIS_CLIENT=phpredis
REDIS_PREFIX=pillar_production_proof_
REDIS_CACHE_DB=3
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
MAIL_MAILER=log
''')
(consumer / 'database/database.sqlite').touch()
shutil.copy(root / 'tools/production-smoke.php', build / 'production-smoke.php')
