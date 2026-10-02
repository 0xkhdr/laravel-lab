import hashlib
import json
import sys
from pathlib import Path

root = Path(sys.argv[1])
snapshot = {str(f.relative_to(root)): hashlib.sha256(f.read_bytes()).hexdigest()
            for f in sorted(root.rglob('*')) if f.is_file() and not f.is_symlink() and 'vendor' not in f.parts}
print(json.dumps(snapshot, sort_keys=True, indent=2))
