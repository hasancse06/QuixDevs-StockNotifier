#!/usr/bin/env python3
"""Require an existing, version-matching tag at the checked-out clean commit."""
import argparse
from pathlib import Path
import subprocess
from release_common import ROOT, ReleaseError, git_metadata, read_version, validate_tag


def verify_tag(root, tag):
    version = read_version(root)
    validate_tag(tag, version)
    metadata = git_metadata(root)
    try:
        tagged = subprocess.check_output(['git', '-C', str(root), 'rev-parse', '--verify',
                                          'refs/tags/' + tag + '^{commit}'], text=True, stderr=subprocess.DEVNULL).strip()
    except subprocess.CalledProcessError as error:
        raise ReleaseError('The release tag must already exist; this tool never creates tags.') from error
    if tagged != metadata['source_commit'] or not metadata['worktree_clean']:
        raise ReleaseError('Checkout must be the existing tag commit with a clean source tree.')
    return {'tag': tag, 'version': version, 'commit': tagged}


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--tag', required=True)
    parser.add_argument('--github-output', type=Path)
    args = parser.parse_args()
    try:
        result = verify_tag(ROOT, args.tag)
        if args.github_output:
            with args.github_output.open('a', encoding='utf-8') as output:
                for key, value in result.items():
                    output.write(key + '=' + value + '\n')
        print('PASS: ' + result['tag'] + ' at ' + result['commit'])
    except ReleaseError as error:
        raise SystemExit(str(error))
