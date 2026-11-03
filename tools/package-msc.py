#!/usr/bin/env python3
"""Package the plugin and reproducible source without runtime credentials."""
import hashlib
from pathlib import Path
import re
from zipfile import ZipFile, ZIP_DEFLATED

root = Path(__file__).resolve().parent.parent
plugin = root / 'multisite-shared-content'
version = re.search(r'\* Version: ([0-9.]+)', (plugin / 'multisite-shared-content.php').read_text()).group(1)
out = root / 'dist'
out.mkdir(exist_ok=True)
out.chmod(0o755)
names = ['Dockerfile', 'README.md', 'VERIFICATION.md', 'compose.yaml', 'compose.shared-content.yaml', '.dockerignore', '.gitignore', 'bin', 'docker', 'docs', 'wp-content', 'multisite-shared-content', 'tests', 'tools', '.github']
source = []
for name in names:
    path = root / name
    source.extend([path] if path.is_file() else [p for p in path.rglob('*') if p.is_file()])
archives = []
for suffix, paths in [('', list(plugin.rglob('*'))), ('-source', source)]:
    target = out / f'multisite-shared-content{suffix}-{version}.zip'
    with ZipFile(target, 'w', ZIP_DEFLATED) as archive:
        for path in sorted(set(paths)):
            if not path.is_file() or '__pycache__' in path.parts:
                continue
            if path.name.startswith('.env') or path.suffix in {'.env', '.log', '.sql', '.pyc'} or 'local.env' in path.name:
                raise RuntimeError('Refusing runtime artifact: ' + str(path.relative_to(root)))
            archive.write(path, path.relative_to(root))
    target.chmod(0o644)
    with ZipFile(target) as archive:
        assert archive.testzip() is None
    archives.append(target)
    print(target.name)
(out / 'SHA256SUMS').write_text(''.join(hashlib.sha256(p.read_bytes()).hexdigest() + '  ' + p.name + '\n' for p in archives))
