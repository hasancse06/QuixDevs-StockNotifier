#!/usr/bin/env python3
"""Build a deterministic, production-only WordPress upload archive."""
from pathlib import Path
import hashlib
import zipfile

root = Path(__file__).resolve().parents[1]
output = root.parent / 'quixdevs-stocknotifier.zip'
files = [root / name for name in ('quixdevs-stocknotifier.php', 'uninstall.php', 'readme.txt', 'LICENSE')]
for folder in ('includes', 'assets', 'languages'):
    files.extend(p for p in (root / folder).rglob('*') if p.is_file() and not p.name.startswith('.'))
with zipfile.ZipFile(output, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
    for file in sorted(files):
        relative = Path('quixdevs-stocknotifier') / file.relative_to(root)
        info = zipfile.ZipInfo(str(relative), date_time=(2026, 10, 10, 0, 0, 0))
        info.compress_type = zipfile.ZIP_DEFLATED
        info.external_attr = 0o100644 << 16
        archive.writestr(info, file.read_bytes())
with zipfile.ZipFile(output) as archive:
    assert archive.testzip() is None
    names = archive.namelist()
    assert 'quixdevs-stocknotifier/quixdevs-stocknotifier.php' in names
    assert len(names) == len(set(names))
    for name in names:
        assert name.startswith('quixdevs-stocknotifier/')
        assert '..' not in Path(name).parts
        assert not any(part.startswith('.') for part in Path(name).parts)
        assert not any(part in ('vendor', 'node_modules', 'tests', 'docs', '.git', '.github') for part in Path(name).parts)
print(f'{output}\n{len(files)} production files\nSHA256 {hashlib.sha256(output.read_bytes()).hexdigest()}')
