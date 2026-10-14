"""Negative packaging/release checks; fixtures never contact GitHub or send mail."""
import importlib.util
import json
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile
import unittest
from unittest.mock import patch
import warnings
import zipfile

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / 'bin'))
from release_common import ARCHIVE, CHECKSUM, MANIFEST, REQUIRED, ReleaseError, validate_archive, validate_tag


def module(name, filename):
    specification = importlib.util.spec_from_file_location(name, ROOT / 'bin' / filename)
    loaded = importlib.util.module_from_spec(specification)
    specification.loader.exec_module(loaded)
    return loaded


builder = module('release_builder', 'build-release.py')
draft = module('release_draft', 'create-draft-release.py')
preparation = module('release_preparation', 'prepare-release.py')


class ReleaseTests(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.base = Path(self.temporary.name)
        self.source = self.base / 'source'
        for name in REQUIRED:
            target = self.source / name
            target.parent.mkdir(parents=True, exist_ok=True)
            shutil.copyfile(ROOT / name, target)
        self.output = self.base / 'dist'
        self.result = builder.build(self.source, self.output)
        self.archive = self.output / ARCHIVE

    def tearDown(self):
        self.temporary.cleanup()

    def rewrite(self, rename=None, omit=None, replace=None, extra=None):
        with zipfile.ZipFile(self.archive) as archive:
            entries = [(entry.filename, archive.read(entry)) for entry in archive.infolist()]
        with zipfile.ZipFile(self.archive, 'w', zipfile.ZIP_DEFLATED) as archive:
            for name, data in entries:
                if name == omit:
                    continue
                archive.writestr(rename(name) if rename else name, replace.get(name, data) if replace else data)
            if extra:
                archive.writestr(*extra)

    def test_complete_capabilities_and_deterministic_runtime(self):
        other = self.base / 'other'
        builder.build(self.source, other)
        self.assertEqual(self.archive.read_bytes(), (other / ARCHIVE).read_bytes())
        result = validate_archive(self.archive, root=self.source, manifest=self.output / MANIFEST, tag='v1.0.0')
        self.assertEqual(len(result['files']), 40)
        self.assertEqual(set(path['path'].split('/', 1)[1] for path in result['files']), REQUIRED)

    def test_development_images_archives_and_temp_files_are_excluded(self):
        for name in ['docs/screenshot.png', 'tests/test.php', '.github/workflows/extra.yml',
                     'vendor/library.php', 'wordpress-org-assets/banner.png', 'previous.zip',
                     'assets/old.zip', 'assets/debug.log', 'includes/.hidden/debug.php',
                     'includes/tmp/debug.php', 'includes/old.php.bak']:
            file = self.source / name
            file.parent.mkdir(parents=True, exist_ok=True)
            file.write_text('must not ship')
        builder.build(self.source, self.output)
        with zipfile.ZipFile(self.archive) as archive:
            self.assertEqual(len(archive.namelist()), 40)

    def test_missing_required_provider_or_asset_fails_before_build(self):
        for name in ['includes/Recipients/Dokan_Vendor.php', 'includes/Recipients/WCVendors_Vendor.php',
                     'includes/Recipients/PublishPress_Authors.php', 'includes/Recipients/WordPress_Author.php',
                     'includes/Inventory/Stock_Monitor.php', 'assets/js/admin.js', 'assets/css/admin.css']:
            with self.subTest(name=name):
                file = self.source / name
                data = file.read_bytes()
                file.unlink()
                with self.assertRaises(ReleaseError):
                    builder.build(self.source, self.output)
                file.write_bytes(data)

    def test_symlinked_runtime_file_is_rejected(self):
        (self.source / 'includes/foreign.php').symlink_to(self.source / 'includes/Plugin.php')
        with self.assertRaisesRegex(ReleaseError, 'symlinks'):
            builder.build(self.source, self.output)

    def test_wrong_root_and_path_traversal_are_rejected(self):
        self.rewrite(rename=lambda name: name.replace('quixdevs-stocknotifier/', 'source/', 1))
        with self.assertRaises(ReleaseError):
            validate_archive(self.archive)
        builder.build(self.source, self.output)
        self.rewrite(extra=('quixdevs-stocknotifier/../outside.php', b'bad'))
        with self.assertRaises(ReleaseError):
            validate_archive(self.archive)

    def test_normalization_alias_and_duplicate_entries_are_rejected(self):
        self.rewrite(extra=('quixdevs-stocknotifier/includes/./alias.php', b'bad'))
        with self.assertRaises(ReleaseError):
            validate_archive(self.archive)
        builder.build(self.source, self.output)
        with warnings.catch_warnings():
            warnings.simplefilter('ignore', UserWarning)
            self.rewrite(extra=('quixdevs-stocknotifier/LICENSE', b'duplicate'))
        with self.assertRaisesRegex(ReleaseError, 'Duplicate'):
            validate_archive(self.archive)

    def test_missing_component_and_added_development_file_are_rejected(self):
        self.rewrite(omit='quixdevs-stocknotifier/includes/Notifications/Email_Sender.php')
        with self.assertRaises(ReleaseError):
            validate_archive(self.archive)
        builder.build(self.source, self.output)
        self.rewrite(extra=('quixdevs-stocknotifier/docs/screenshot.png', b'bad'))
        with self.assertRaises(ReleaseError):
            validate_archive(self.archive)

    def test_modified_runtime_and_manifest_are_rejected(self):
        self.rewrite(replace={'quixdevs-stocknotifier/assets/js/admin.js': b'altered'})
        with self.assertRaisesRegex(ReleaseError, 'differ from source'):
            validate_archive(self.archive, root=self.source)
        with self.assertRaisesRegex(ReleaseError, 'Manifest mismatch'):
            validate_archive(self.archive, manifest=self.output / MANIFEST)

    def test_secret_is_redacted_and_blocks_archive(self):
        sample = ('gh' + 'p_' + 'A' * 36).encode()
        self.rewrite(extra=('quixdevs-stocknotifier/includes/Extra.php', sample))
        with self.assertRaises(ReleaseError) as error:
            validate_archive(self.archive)
        self.assertNotIn(sample.decode(), str(error.exception))

    def test_corrupted_archive_is_rejected(self):
        self.archive.write_bytes(b'not a zip')
        with self.assertRaisesRegex(ReleaseError, 'corrupt'):
            validate_archive(self.archive)

    def test_version_tag_and_readme_mismatch_are_rejected(self):
        for tag in ['v1.0.1', '1.0.0', 'master', 'v1.0.0;echo bad']:
            with self.subTest(tag=tag), self.assertRaises(ReleaseError):
                validate_tag(tag, '1.0.0')
        readme = self.source / 'readme.txt'
        readme.write_text(readme.read_text().replace('Stable tag: 1.0.0', 'Stable tag: 1.0.1'))
        with self.assertRaises(ReleaseError):
            builder.build(self.source, self.output)

    def test_preparation_requires_existing_clean_tag_commit(self):
        commit = 'a' * 40
        with patch.object(preparation, 'git_metadata', return_value={'source_commit': commit, 'worktree_clean': True}):
            with patch.object(preparation.subprocess, 'check_output', side_effect=subprocess.CalledProcessError(1, ['git'])):
                with self.assertRaisesRegex(ReleaseError, 'already exist'):
                    preparation.verify_tag(self.source, 'v1.0.0')
            with patch.object(preparation.subprocess, 'check_output', return_value='b' * 40):
                with self.assertRaisesRegex(ReleaseError, 'tag commit'):
                    preparation.verify_tag(self.source, 'v1.0.0')
        with patch.object(preparation, 'git_metadata', return_value={'source_commit': commit, 'worktree_clean': False}), patch.object(preparation.subprocess, 'check_output', return_value=commit):
            with self.assertRaisesRegex(ReleaseError, 'clean'):
                preparation.verify_tag(self.source, 'v1.0.0')

    def draft_fixture(self):
        manifest = self.output / MANIFEST
        payload = json.loads(manifest.read_text())
        payload.update(source_commit='a' * 40, worktree_clean=True)
        manifest.write_text(json.dumps(payload))
        (self.output / 'release-notes.md').write_text('Synthetic release test')

    def test_draft_creation_never_publishes_or_creates_tag(self):
        self.draft_fixture()
        calls = []
        views = 0
        def fake(arguments, check=True):
            nonlocal views
            calls.append(arguments)
            if arguments[:2] == ['release', 'view']:
                views += 1
                if views == 1:
                    return subprocess.CompletedProcess(arguments, 1, '', '')
                return subprocess.CompletedProcess(arguments, 0, json.dumps({'isDraft': True, 'tagName': 'v1.0.0', 'url': 'https://example.com/draft'}), '')
            return subprocess.CompletedProcess(arguments, 0, '', '')
        with patch.object(draft, 'gh', side_effect=fake), patch.object(draft, 'remote_commit', return_value='a' * 40):
            self.assertEqual(draft.create_draft('owner/repo', 'v1.0.0', self.output, 'a' * 40), 'https://example.com/draft')
        creation = next(call for call in calls if call[:2] == ['release', 'create'])
        self.assertIn('--draft', creation)
        self.assertIn('--verify-tag', creation)
        self.assertTrue(any(call[:2] == ['release', 'upload'] for call in calls))
        self.assertFalse(any('--draft=false' in call or 'edit' in call for call in calls))

    def test_published_release_and_moved_tag_are_never_modified(self):
        self.draft_fixture()
        view = subprocess.CompletedProcess([], 0, json.dumps({'isDraft': False, 'tagName': 'v1.0.0'}), '')
        with patch.object(draft, 'remote_commit', return_value='a' * 40), patch.object(draft, 'gh', return_value=view) as runner:
            with self.assertRaisesRegex(ReleaseError, 'published'):
                draft.create_draft('owner/repo', 'v1.0.0', self.output, 'a' * 40)
            self.assertEqual(runner.call_count, 1)
        with patch.object(draft, 'remote_commit', return_value='b' * 40), patch.object(draft, 'gh') as runner:
            with self.assertRaisesRegex(ReleaseError, 'changed'):
                draft.create_draft('owner/repo', 'v1.0.0', self.output, 'a' * 40)
            runner.assert_not_called()

    def test_draft_rejects_tampered_checksum_before_contacting_github(self):
        self.draft_fixture()
        (self.output / CHECKSUM).write_text('invalid')
        with patch.object(draft, 'gh') as runner:
            with self.assertRaisesRegex(ReleaseError, 'checksum'):
                draft.create_draft('owner/repo', 'v1.0.0', self.output, 'a' * 40)
            runner.assert_not_called()


if __name__ == '__main__':
    unittest.main()
