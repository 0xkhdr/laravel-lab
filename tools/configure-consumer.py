import json
from pathlib import Path

p = Path('/workspace/consumer')
f = p / 'composer.json'
c = json.loads(f.read_text())
c['require']['php'] = '~8.4.0'
c['require']['raid/pillar'] = 'dev-main'
c['repositories'] = [{'type': 'path', 'url': '../packages/*', 'options': {'symlink': True, 'versions': {f'raid/{n}': 'dev-main' for n in ['pillar', 'catalog', 'foundry']}}}]
f.write_text(json.dumps(c, indent=4) + '\n')
env = p / '.env'
text = env.read_text()
for key, value in {'APP_NAME': 'PillarConsumer', 'DB_CONNECTION': 'sqlite', 'DB_DATABASE': '/workspace/consumer/database/database.sqlite', 'CACHE_STORE': 'redis', 'CACHE_PREFIX': 'pillar_consumer_a', 'REDIS_HOST': 'redis', 'REDIS_DB': '0', 'REDIS_CACHE_DB': '1', 'REDIS_PREFIX': 'pillar_consumer_a_'}.items():
    lines = text.splitlines()
    lines = [line for line in lines if not line.startswith(key + '=')]
    text = '\n'.join(lines) + f'\n{key}={value}\n'
env.write_text(text)
