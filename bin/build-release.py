#!/usr/bin/env python3
"""Build the complete runtime-only ZIP, file manifest and SHA-256 sidecar."""
import argparse
import json
from pathlib import Path
import tempfile
import zipfile

from release_common import (ARCHIVE, CHECKSUM, MANIFEST, ROOT, SLUG, ReleaseError,
                            collect_runtime, git_metadata, read_version,
                            validate_archive, validate_tag)


def build(root=ROOT, output_dir=None, tag=None):
    root = Path(root)
    output_dir = Path(output_dir) if output_dir else root / 'dist'
    version = read_version(root)
    if tag:
        validate_tag(tag, version)
    files = collect_runtime(root)
    output_dir.mkdir(parents=True, exist_ok=True)
    output = output_dir / ARCHIVE
    with tempfile.TemporaryDirectory(dir=output_dir, prefix='.build-') as temporary:
        candidate = Path(temporary) / ARCHIVE
        with zipfile.ZipFile(candidate, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
            for name in files:
                entry = zipfile.ZipInfo(SLUG + '/' + name, (2026, 10, 10, 0, 0, 0))
                entry.compress_type = zipfile.ZIP_DEFLATED
                entry.external_attr = 0o100644 << 16
                archive.writestr(entry, (root / name).read_bytes(), compresslevel=9)
        result = validate_archive(candidate, root=root, tag=tag)
        candidate.replace(output)
    result.update(git_metadata(root))
    (output_dir / MANIFEST).write_text(json.dumps(result, indent=2) + '\n', encoding='utf-8')
    (output_dir / CHECKSUM).write_text(result['sha256'] + '  ' + ARCHIVE + '\n', encoding='ascii')
    return result


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output-dir', type=Path, default=ROOT / 'dist')
    parser.add_argument('--tag', help='Require the plugin/readme version to match this v* tag.')
    args = parser.parse_args()
    result = build(output_dir=args.output_dir, tag=args.tag)
    print(str(args.output_dir / ARCHIVE))
    print(str(len(result['files'])) + ' production files; ' + str(result['zip_bytes']) + ' bytes')
    print('SHA256 ' + result['sha256'])


if __name__ == '__main__':
    try:
        main()
    except (ReleaseError, OSError) as error:
        raise SystemExit(str(error))
