import shutil
from pathlib import Path

root = Path('/workspace')
source = root / 'consumer'
target = root / 'consumer-example'
shutil.copytree(source, target, ignore=shutil.ignore_patterns('vendor', '.env', '*.sqlite', '.git', '.phpunit.cache', 'logs', 'cache'), dirs_exist_ok=True)
for directory in ['bootstrap/cache', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs']:
    (target / directory).mkdir(parents=True, exist_ok=True)
(target / '.env').write_text((source / '.env.example').read_text() + '\nDB_DATABASE=/workspace/consumer-example/database/database.sqlite\nCACHE_PREFIX=pillar_consumer_b\nREDIS_PREFIX=pillar_consumer_b_\n')
(target / 'database/database.sqlite').touch()
f = target / 'app-modules/catalog/src/Policies/ApplicationSku.php'
s = f.read_text().replace('return $sku;', '''$sku = strtoupper(trim($sku));
        if (!preg_match('/^[A-Z0-9-]+$/', $sku)) {
            throw \\Illuminate\\Validation\\ValidationException::withMessages(['sku' => 'Use letters, numbers and hyphens.']);
        }
        return $sku;''')
f.write_text(s)
f = target / 'app-modules/catalog/tests/HttpTest.php'
s = f.read_text().replace("assertJsonPath('data.sku', 'http-001')", "assertJsonPath('data.sku', 'HTTP-001')")
f.write_text(s)
