import hashlib
import json
from pathlib import Path

root = Path('/workspace')
consumer = root / 'build/consumer'
before = json.loads((root / 'evidence/upgrade-custom-before.json').read_text())
after = {path: hashlib.sha256((consumer / path).read_bytes()).hexdigest() for path in before}
assert before == after, 'Composer changed application-owned customization'
(root / 'evidence/upgrade-custom-after.json').write_text(json.dumps(after, sort_keys=True, indent=2)+'\n')
print('SKU policy and ProductResource hashes preserved across Composer runtime upgrade.')
