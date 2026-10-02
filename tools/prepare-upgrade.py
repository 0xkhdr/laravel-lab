import hashlib
import json
import shutil
from pathlib import Path

root = Path('/workspace')
build = root / 'build'
consumer = build / 'consumer'
policy = consumer / 'app-modules/catalog/src/Policies/ApplicationSku.php'
policy.write_text(policy.read_text().replace('return $sku;', "return 'SITE-'.$sku;"))
resource = consumer / 'app-modules/catalog/src/Http/Resources/ProductResource.php'
text = resource.read_text()
assert "return [" in text
resource.write_text(text.replace('return [', "return [\n            'application_marker' => 'preserved',", 1))
hashes = {str(f.relative_to(consumer)): hashlib.sha256(f.read_bytes()).hexdigest() for f in [policy, resource]}
(root / 'evidence/upgrade-custom-before.json').write_text(json.dumps(hashes, sort_keys=True, indent=2)+'\n')

# Local versioned package mirrors exercise actual Composer replacement, not publication.
f = consumer / 'composer.json'
c = json.loads(f.read_text())
c['repositories'][0]['options']['versions']['raid/catalog'] = '0.1.1'
f.write_text(json.dumps(c, indent=4)+'\n')
package = build / 'packages/foundry'
shutil.copytree(root / 'packages/foundry', package, ignore=shutil.ignore_patterns('vendor', 'tests', 'composer.lock', '.phpunit.cache', '.git'))
f = package / 'composer.json'
c = json.loads(f.read_text())
c['require']['raid/pillar'] = '^0.1'
f.write_text(json.dumps(c, indent=4)+'\n')
