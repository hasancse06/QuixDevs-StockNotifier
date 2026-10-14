#!/usr/bin/env python3
"""Attach verified files to a draft only; never create tags or publish releases."""
import argparse
import json
from pathlib import Path
import re
import subprocess

from release_common import ARCHIVE, CHECKSUM, MANIFEST, ROOT, ReleaseError, validate_archive


def gh(arguments, check=True):
    return subprocess.run(['gh'] + arguments, check=check, capture_output=True, text=True)


def remote_commit(repo, tag):
    reference = json.loads(gh(['api', 'repos/' + repo + '/git/ref/tags/' + tag]).stdout)['object']
    for _ in range(10):
        if reference['type'] == 'commit':
            return reference['sha']
        if reference['type'] != 'tag':
            break
        reference = json.loads(gh(['api', 'repos/' + repo + '/git/tags/' + reference['sha']]).stdout)['object']
    raise ReleaseError('Remote tag does not resolve to a commit.')


def create_draft(repo, tag, directory, expected_commit):
    directory = Path(directory)
    if not re.fullmatch(r'[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+', repo):
        raise ReleaseError('Supply the confirmed OWNER/REPOSITORY.')
    if not re.fullmatch(r'[0-9a-f]{40}', expected_commit):
        raise ReleaseError('Expected commit must be a full Git commit SHA.')
    archive = directory / ARCHIVE
    manifest = directory / MANIFEST
    stored = json.loads(manifest.read_text(encoding='utf-8'))
    result = validate_archive(archive, manifest=manifest, tag=tag)
    if stored.get('source_commit') != expected_commit or stored.get('worktree_clean') is not True:
        raise ReleaseError('The artifact must come from the clean verified tag checkout.')
    if (directory / CHECKSUM).read_text().strip() != result['sha256'] + '  ' + ARCHIVE:
        raise ReleaseError('Artifact checksum sidecar mismatch.')
    if remote_commit(repo, tag) != expected_commit:
        raise ReleaseError('Remote tag changed after verification; refusing release.')
    existing = gh(['release', 'view', tag, '--repo', repo, '--json', 'isDraft,tagName'], check=False)
    if existing.returncode == 0:
        details = json.loads(existing.stdout)
        if details.get('isDraft') is not True or details.get('tagName') != tag:
            raise ReleaseError('A published release already exists; it will not be modified.')
    else:
        # --verify-tag prevents gh from creating an implicit tag. --draft is
        # explicit, and this tool never invokes edit --draft=false/publish.
        gh(['release', 'create', tag, '--repo', repo, '--draft', '--verify-tag',
            '--title', 'QuixDevs StockNotifier ' + tag + ' – Low Stock Alerts for WooCommerce',
            '--notes-file', str(directory / 'release-notes.md')])
    details = json.loads(gh(['release', 'view', tag, '--repo', repo, '--json', 'isDraft,tagName']).stdout)
    if details.get('isDraft') is not True or details.get('tagName') != tag:
        raise ReleaseError('Release is not a draft; no assets will be uploaded.')
    gh(['release', 'upload', tag, '--repo', repo, '--clobber',
        str(archive), str(directory / CHECKSUM), str(manifest)])
    details = json.loads(gh(['release', 'view', tag, '--repo', repo, '--json', 'isDraft,url']).stdout)
    if details.get('isDraft') is not True:
        raise ReleaseError('Release no longer reports draft status; inspect it manually.')
    return details['url']


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--repo', required=True)
    parser.add_argument('--tag', required=True)
    parser.add_argument('--expected-commit', required=True)
    parser.add_argument('--artifact-dir', type=Path, default=ROOT / 'dist')
    args = parser.parse_args()
    try:
        print(create_draft(args.repo, args.tag, args.artifact_dir, args.expected_commit))
    except ReleaseError as error:
        raise SystemExit(str(error))
    except (OSError, subprocess.CalledProcessError) as error:
        # Never emit subprocess stdout/stderr, which can contain credentials.
        raise SystemExit('Draft release preparation failed: ' + type(error).__name__ + '. Review the preceding checks.')
