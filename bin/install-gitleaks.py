#!/usr/bin/env python3
"""Fetch an official, pinned Gitleaks binary for release-only secret scanning."""
import argparse
import hashlib
import io
from pathlib import Path
import platform
import tarfile
import urllib.request

VERSION = '8.30.1'
PLATFORMS = {
    ('Linux', 'x86_64'): ('linux_x64', '551f6fc83ea457d62a0d98237cbad105af8d557003051f41f3e7ca7b3f2470eb'),
    ('Darwin', 'arm64'): ('darwin_arm64', 'b40ab0ae55c505963e365f271a8d3846efbc170aa17f2607f13df610a9aeb6a5'),
}

if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--destination', type=Path, required=True)
    args = parser.parse_args()
    selected = PLATFORMS.get((platform.system(), platform.machine()))
    if not selected:
        raise SystemExit('Use the official Gitleaks distribution for this platform; CI supports Linux x64.')
    name, checksum = selected
    url = f'https://github.com/gitleaks/gitleaks/releases/download/v{VERSION}/gitleaks_{VERSION}_{name}.tar.gz'
    with urllib.request.urlopen(url, timeout=45) as response:
        data = response.read()
    if hashlib.sha256(data).hexdigest() != checksum:
        raise SystemExit('Official Gitleaks archive checksum mismatch; refusing installation.')
    with tarfile.open(fileobj=io.BytesIO(data), mode='r:gz') as archive:
        member = archive.getmember('gitleaks')
        if not member.isfile():
            raise SystemExit('Unexpected Gitleaks archive member.')
        binary = archive.extractfile(member).read()
    args.destination.mkdir(parents=True, exist_ok=True)
    target = args.destination / 'gitleaks'
    target.write_bytes(binary)
    target.chmod(0o700)
    print(f'{target}: official Gitleaks {VERSION}; SHA-256 verified.')
