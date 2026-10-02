import shutil
from pathlib import Path

root = Path('/workspace')
for package in ['pillar', 'catalog']:
    for directory in ['src', 'config', 'database']:
        source = root / 'packages' / package / directory
        if source.exists():
            shutil.copytree(source, root / 'build/packages' / package / directory, dirs_exist_ok=True)
