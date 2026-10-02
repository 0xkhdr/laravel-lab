import json
import shutil
from pathlib import Path

source = Path('/workspace/consumer/app-modules/catalog')
target = Path('/workspace/packages/foundry/resources/catalog')
for f in source.rglob('*'):
    if not f.is_file() or 'database' in f.parts:
        continue
    dest = target / f.relative_to(source)
    dest.parent.mkdir(parents=True, exist_ok=True)
    text = f.read_text().replace('Modules\\Catalog', '{{ namespace }}').replace('CatalogServiceProvider', '{{ module }}ServiceProvider')
    if f.name == 'composer.json':
        text = text.replace('modules/catalog', 'modules/{{ slug }}').replace('Modules\\\\Catalog', '{{ json_namespace }}')
    dest = dest.with_suffix(dest.suffix + '.stub')
    dest.write_text(text)
metadata = {'recipe': 'catalog', 'blueprint': 'catalog.standard', 'recipe_version': '1.1.0', 'blueprint_version': '1.1.0'}
(target / 'blueprint.json').write_text(json.dumps(metadata, indent=4)+'\n')

f = Path('/workspace/packages/foundry/composer.json')
c = json.loads(f.read_text())
c['require-dev']['raid/catalog'] = 'dev-main'
c['suggest'] = {'raid/catalog': 'Install through Composer before using the Catalog recipe.'}
f.write_text(json.dumps(c, indent=4)+'\n')
