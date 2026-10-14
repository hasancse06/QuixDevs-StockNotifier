#!/usr/bin/env python3
"""Check canonical root, required components, integrity, version and source bytes."""
import argparse
from pathlib import Path
from release_common import ARCHIVE, MANIFEST, ROOT, ReleaseError, validate_archive

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('archive', nargs='?', type=Path, default=ROOT / 'dist' / ARCHIVE)
parser.add_argument('--tag')
parser.add_argument('--artifact-only', action='store_true', help='Validate a downloaded artifact without a source checkout.')
args = parser.parse_args()
try:
    manifest = args.archive.parent / MANIFEST
    if not manifest.is_file():
        raise ReleaseError('The generated file manifest is required.')
    result = validate_archive(args.archive, root=None if args.artifact_only else ROOT,
                              manifest=manifest, tag=args.tag)
    checksum = args.archive.parent / (ARCHIVE + '.sha256')
    if checksum.read_text().strip() != result['sha256'] + '  ' + ARCHIVE:
        raise ReleaseError('SHA-256 sidecar mismatch.')
    print('PASS: canonical ZIP, ' + str(len(result['files'])) + ' runtime files, integrity, secrets and checksum.')
except (ReleaseError, OSError) as error:
    raise SystemExit(str(error))
