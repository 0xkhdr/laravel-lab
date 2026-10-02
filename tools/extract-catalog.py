import json
import shutil
from pathlib import Path

r = Path('/reference/pkgs/raid/laravel-catalog-foundation')
p = Path('/workspace/packages/catalog')
def transform(text):
    return text.replace('Raid\\Foundation\\CatalogFoundation', 'Raid\\Catalog').replace('Frontier\\Actions', 'Raid\\Pillar\\Actions').replace('Frontier\\Repositories', 'Raid\\Pillar\\Repositories').replace('catalog-foundation', 'catalog')
for directory in ['src/Actions', 'src/Models', 'src/Repositories', 'src/Enums', 'src/Traits', 'src/Scopes', 'database']:
    for f in (r / directory).rglob('*.php'):
        dest = p / f.relative_to(r)
        dest.parent.mkdir(parents=True, exist_ok=True)
        dest.write_text(transform(f.read_text()))
for name in ['LICENSE', 'LICENSE.md', 'LICENSE.txt']:
    if (r / name).exists():
        shutil.copy(r / name, p / name)
manifest = json.loads((p / 'composer.json').read_text())
manifest['authors'] = json.loads((r / 'composer.json').read_text())['authors']
manifest['autoload']['psr-4']['Raid\\Catalog\\Database\\Factories\\'] = 'database/factories/'
(p / 'composer.json').write_text(json.dumps(manifest, indent=4) + '\n')
provider = transform((r / 'src/CatalogFoundationServiceProvider.php').read_text())
provider = provider[0:provider.index('    /**\n     * Bootstrap package services.')]
provider = provider.replace('class CatalogFoundationServiceProvider', 'class CatalogServiceProvider')
provider = '\n'.join(line for line in provider.split('\n') if 'use Raid\\Catalog\\Console\\Commands' not in line)
provider = provider.replace("catalog.php", "catalog.php")
provider += '}\n'
(p / 'src/CatalogServiceProvider.php').write_text(provider)

consumer = Path('/workspace/consumer')
f = consumer / 'composer.json'
c = json.loads(f.read_text())
c['require']['raid/catalog'] = 'dev-main'
f.write_text(json.dumps(c, indent=4) + '\n')
