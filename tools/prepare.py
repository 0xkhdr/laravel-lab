import json
from pathlib import Path

root = Path('/workspace')
dev = {'orchestra/testbench': '^10.0', 'pestphp/pest': '^4.0', 'pestphp/pest-plugin-laravel': '^4.0', 'laravel/pint': '^1.0'}
for name in ['pillar', 'catalog', 'foundry']:
    p = root / 'packages' / name
    (p / 'src').mkdir(parents=True, exist_ok=True)
    (p / 'tests').mkdir(exist_ok=True)
    ns = 'Raid\\' + name.title() + '\\'
    require = {'php': '~8.4.0', 'laravel/framework': '^12.0'}
    if name == 'pillar':
        require['internachi/modular'] = '^2.3'
    else:
        require['raid/pillar'] = 'dev-main'
    if name == 'catalog':
        require['spatie/laravel-translatable'] = '^6.0'
    if name == 'foundry':
        require['composer-runtime-api'] = '^2.2'
    manifest = {
        'name': 'raid/' + name, 'description': 'Raid ' + name.title() + ' for Laravel 12',
        'type': 'library', 'license': 'MIT', 'require': require, 'require-dev': dev,
        'autoload': {'psr-4': {ns: 'src/'}},
        'autoload-dev': {'psr-4': {ns + 'Tests\\': 'tests/'}},
        'extra': {'laravel': {'providers': [ns + name.title() + 'ServiceProvider']}},
        'repositories': [{'type': 'path', 'url': '../*', 'options': {'versions': {'raid/pillar': 'dev-main', 'raid/catalog': 'dev-main', 'raid/foundry': 'dev-main'}}}],
        'config': {'allow-plugins': {'pestphp/pest-plugin': True}},
        'scripts': {'test': 'vendor/bin/pest', 'lint': 'vendor/bin/pint --test'},
    }
    (p / 'composer.json').write_text(json.dumps(manifest, indent=4) + '\n')
    (p / 'phpunit.xml').write_text('<phpunit bootstrap="vendor/autoload.php" colors="true"><testsuites><testsuite name="Package"><directory>tests</directory></testsuite></testsuites></phpunit>\n')

# Disposable source characterization harness, never a consumer dependency.
p = root / 'evidence' / 'baseline'
p.mkdir(exist_ok=True)
manifest = {'name': 'raid/source-characterization', 'description': 'Read-only reference characterization', 'license': 'MIT',
            'require': {'php': '~8.4.0', 'laravel/framework': '^12.0'}, 'require-dev': dev,
            'autoload': {'psr-4': {'Frontier\\Actions\\': '/reference/pkgs/frontier/action/src/', 'Frontier\\Repositories\\': '/reference/pkgs/frontier/repository/src/'}},
            'config': {'allow-plugins': {'pestphp/pest-plugin': True}}}
(p / 'composer.json').write_text(json.dumps(manifest, indent=4) + '\n')
