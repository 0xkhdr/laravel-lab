import json
import shutil
from pathlib import Path

r = Path('/reference/pkgs/frontier')
p = Path('/workspace/packages/pillar')
def copy_tree(source, target):
    for f in source.rglob('*'):
        if f.is_file():
            dest = target / f.relative_to(source)
            dest.parent.mkdir(parents=True, exist_ok=True)
            dest.write_text(f.read_text().replace('Frontier\\Actions', 'Raid\\Pillar\\Actions').replace('Frontier\\Repositories', 'Raid\\Pillar\\Repositories'))

for name in ['BaseAction.php', 'Contracts/Action.php']:
    f = r / 'action/src' / name
    dest = p / 'src/Actions' / name
    dest.parent.mkdir(parents=True, exist_ok=True)
    dest.write_text(f.read_text().replace('Frontier\\Actions', 'Raid\\Pillar\\Actions'))
for name in ['Contracts', 'Traits', 'ValueObjects']:
    copy_tree(r / 'repository/src' / name, p / 'src/Repositories' / name)
for name in ['BaseRepository.php', 'BaseRepositoryCache.php']:
    f = r / 'repository/src' / name
    dest = p / 'src/Repositories' / name
    dest.write_text(f.read_text().replace('Frontier\\Repositories', 'Raid\\Pillar\\Repositories'))
(p / 'config').mkdir(exist_ok=True)
shutil.copy(r / 'repository/config/repository-cache.php', p / 'config/repository-cache.php')
shutil.copy(r / 'modular/config/app-modules.php', p / 'config/app-modules.php')
for package in ['action', 'repository', 'modular']:
    for name in ['LICENSE', 'LICENSE.md', 'LICENSE.txt']:
        if (r / package / name).exists():
            shutil.copy(r / package / name, p / (package + '-' + name))
manifest = json.loads((p / 'composer.json').read_text())
manifest['authors'] = json.loads((r / 'repository/composer.json').read_text())['authors']
manifest['suggest'] = {'tucker-eric/eloquentfilter': 'Required only for filters options; model must implement filter()'}
(p / 'composer.json').write_text(json.dumps(manifest, indent=4) + '\n')

test = Path('/workspace/evidence/baseline/CharacterizationTest.php').read_text()
test = test.replace('use Frontier\\', 'use Raid\\Pillar\\')
test = test.replace('use Raid\\Pillar\\Actions', 'namespace Raid\\Pillar\\Tests;\n\nuse DomainException;\nuse Raid\\Pillar\\Actions', 1)
test = test.replace('final class CharacterizationTest', 'class CharacterizationTest')
(p / 'tests/CharacterizationTest.php').write_text(test)
