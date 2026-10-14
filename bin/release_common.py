"""Release tooling only. Nothing in this module is shipped to WordPress."""
from pathlib import Path, PurePosixPath
import hashlib
import json
import re
import stat
import subprocess
import zipfile

ROOT = Path(__file__).resolve().parents[1]
SLUG = 'quixdevs-stocknotifier'
ARCHIVE = SLUG + '.zip'
MANIFEST = SLUG + '-manifest.json'
CHECKSUM = ARCHIVE + '.sha256'
VERSION_PATTERN = r'[0-9]+\.[0-9]+\.[0-9]+(?:-[0-9A-Za-z][0-9A-Za-z.-]*)?'
TOP_LEVEL = ('quixdevs-stocknotifier.php', 'uninstall.php', 'readme.txt', 'LICENSE')
RUNTIME_FOLDERS = ('includes', 'assets', 'languages')
FORBIDDEN_PARTS = {'vendor', 'node_modules', 'tests', 'docs', '.github', '.git',
                   'wordpress-org-assets', 'bin', 'dist', '__pycache__', 'tmp', 'temp'}
ALLOWED_SUFFIXES = {
    'includes': {'.php'},
    'assets': {'.css', '.js', '.json', '.svg', '.woff', '.woff2', '.ttf', '.eot'},
    'languages': {'.pot', '.po', '.mo'},
}
CAPABILITIES = {
    'stock_monitoring': ['includes/Inventory/Stock_Monitor.php', 'includes/Inventory/Alert_State.php', 'includes/Inventory/Reconciliation.php'],
    'threshold_configuration_and_validation': ['includes/Support/Settings.php', 'includes/Support/Validator.php', 'includes/Admin/Settings_Page.php', 'assets/js/admin.js'],
    'email_template_editing_and_rendering': ['includes/Admin/Email_Templates.php', 'includes/Notifications/Template_Renderer.php'],
    'email_sending': ['includes/Notifications/Email_Sender.php'],
    'action_scheduler': ['includes/Notifications/Notification_Queue.php', 'includes/Dependencies.php'],
    'notification_deduplication': ['includes/Notifications/Notification_Manager.php', 'includes/Data/Notification_Repository.php', 'includes/Support/Lock_Manager.php'],
    'wordpress_author_provider': ['includes/Recipients/WordPress_Author.php'],
    'dokan_provider': ['includes/Recipients/Dokan_Vendor.php'],
    'wc_vendors_provider': ['includes/Recipients/WCVendors_Vendor.php'],
    'publishpress_provider': ['includes/Recipients/PublishPress_Authors.php'],
    'admin_settings_and_diagnostics': ['includes/Admin/Admin.php', 'includes/Admin/Dashboard.php', 'includes/Admin/Low_Stock_List.php', 'includes/Admin/Activity_Page.php', 'includes/Admin/Tools_Page.php', 'includes/Admin/View.php', 'includes/Support/Logger.php', 'assets/css/admin.css'],
    'bundled_production_loader_and_lifecycle': ['includes/autoload.php', 'includes/Plugin.php', 'includes/Activator.php', 'includes/Deactivator.php', 'includes/Recipients/Provider.php', 'includes/Recipients/Recipient.php', 'includes/Recipients/Recipient_Interface.php', 'includes/Recipients/Recipient_Resolver.php', 'languages/quixdevs-stocknotifier.pot'],
}
REQUIRED = set(TOP_LEVEL).union(*(set(paths) for paths in CAPABILITIES.values()))
SECRET_PATTERNS = {
    'private key': rb'-----BEGIN (?:RSA |EC |OPENSSH |DSA )?PRIVATE KEY-----',
    'GitHub token': rb'\b(?:gh[pousr]_[A-Za-z0-9]{30,}|github_pat_[A-Za-z0-9_]{40,})\b',
    'AWS access key': rb'\b(?:AKIA|ASIA)[0-9A-Z]{16}\b',
    'Stripe secret key': rb'\bsk_live_[A-Za-z0-9]{20,}\b',
    'Slack token': rb'\bxox[baprs]-[0-9A-Za-z-]{20,}\b',
}


class ReleaseError(ValueError):
    """A failed package/release invariant; never include secret values."""


def sha256(data):
    return hashlib.sha256(data).hexdigest()


def read_version(root=ROOT):
    header = (root / TOP_LEVEL[0]).read_text(encoding='utf-8')
    readme = (root / 'readme.txt').read_text(encoding='utf-8')
    plugin = re.search(r'^\s*\*\s*Version:\s*(\S+)\s*$', header, re.MULTILINE)
    stable = re.search(r'^Stable tag:\s*(\S+)\s*$', readme, re.MULTILINE)
    if not plugin or not stable or not re.fullmatch(VERSION_PATTERN, plugin[1]):
        raise ReleaseError('Missing or unsupported plugin/readme version.')
    if plugin[1] != stable[1]:
        raise ReleaseError('Plugin version and readme Stable tag differ.')
    return plugin[1]


def validate_tag(tag, version):
    if not re.fullmatch('v' + VERSION_PATTERN, tag or '') or tag != 'v' + version:
        raise ReleaseError('Release tag must equal v' + version + '.')


def git_metadata(root=ROOT):
    try:
        commit = subprocess.check_output(['git', '-C', str(root), 'rev-parse', 'HEAD'], text=True, stderr=subprocess.DEVNULL).strip()
        dirty = subprocess.check_output(['git', '-C', str(root), 'status', '--porcelain'], text=True)
        return {'source_commit': commit, 'worktree_clean': not bool(dirty.strip())}
    except (subprocess.CalledProcessError, FileNotFoundError):
        return {'source_commit': None, 'worktree_clean': False}


def collect_runtime(root=ROOT):
    files = set(TOP_LEVEL)
    for folder in RUNTIME_FOLDERS:
        directory = root / folder
        if not directory.is_dir() or directory.is_symlink():
            raise ReleaseError('Missing or symlinked runtime directory: ' + folder)
        for file in directory.rglob('*'):
            if file.is_symlink():
                raise ReleaseError('Runtime symlinks are not permitted: ' + str(file.relative_to(root)))
            relative = file.relative_to(root)
            if any(part.startswith('.') or part in FORBIDDEN_PARTS for part in relative.parts):
                continue
            if file.is_file() and file.suffix.lower() in ALLOWED_SUFFIXES[folder]:
                files.add(relative.as_posix())
    if not REQUIRED.issubset(files):
        raise ReleaseError('Missing required components: ' + ', '.join(sorted(REQUIRED - files)))
    for name in files:
        file = root / name
        if not file.is_file() or file.is_symlink():
            raise ReleaseError('Missing or symlinked runtime file: ' + name)
    return sorted(files)


def scan_bytes(name, data):
    for label, pattern in SECRET_PATTERNS.items():
        if re.search(pattern, data):
            raise ReleaseError('Possible ' + label + ' in ' + name + '; value redacted.')


def validate_archive(archive, root=None, manifest=None, tag=None):
    """Validate a built/downloaded ZIP, optionally against every source byte."""
    archive = Path(archive)
    if archive.name != ARCHIVE:
        raise ReleaseError('The installable filename must be ' + ARCHIVE + '.')
    try:
        with zipfile.ZipFile(archive) as package:
            entries = package.infolist()
            names = [entry.filename for entry in entries]
            if len(names) != len(set(names)):
                raise ReleaseError('Duplicate ZIP entries.')
            content = {}
            for entry in entries:
                path = PurePosixPath(entry.filename)
                if (not entry.filename.startswith(SLUG + '/') or path.as_posix() != entry.filename or '\\' in entry.filename
                        or path.is_absolute() or '..' in path.parts or ':' in entry.filename
                        or any(part.startswith('.') or part in FORBIDDEN_PARTS for part in path.parts)
                        or entry.is_dir() or stat.S_ISLNK(entry.external_attr >> 16)):
                    raise ReleaseError('Unsafe or non-runtime ZIP entry: ' + entry.filename)
                relative = path.relative_to(SLUG).as_posix()
                if relative not in TOP_LEVEL:
                    parts = PurePosixPath(relative).parts
                    if (parts[0] not in RUNTIME_FOLDERS or PurePosixPath(relative).suffix.lower()
                            not in ALLOWED_SUFFIXES[parts[0]]):
                        raise ReleaseError('Unexpected runtime file: ' + relative)
                data = package.read(entry)
                scan_bytes(relative, data)
                content[relative] = data
            if package.testzip() is not None:
                raise ReleaseError('ZIP integrity check failed.')
    except (zipfile.BadZipFile, RuntimeError) as error:
        raise ReleaseError('Unreadable/corrupt ZIP.') from error
    if not REQUIRED.issubset(content):
        raise ReleaseError('Required files omitted from ZIP: ' + ', '.join(sorted(REQUIRED - content.keys())))
    header = re.search(rb'^\s*\*\s*Version:\s*(\S+)\s*$', content[TOP_LEVEL[0]], re.MULTILINE)
    stable = re.search(rb'^Stable tag:\s*(\S+)\s*$', content['readme.txt'], re.MULTILINE)
    if not header or not stable or header[1] != stable[1]:
        raise ReleaseError('ZIP plugin/readme version mismatch.')
    version = header[1].decode('ascii')
    if not re.fullmatch(VERSION_PATTERN, version):
        raise ReleaseError('Unsupported ZIP version.')
    if tag:
        validate_tag(tag, version)
    if root:
        expected = collect_runtime(Path(root))
        if set(expected) != set(content):
            raise ReleaseError('ZIP does not contain exactly the selected runtime source files.')
        for name, data in content.items():
            if data != (Path(root) / name).read_bytes():
                raise ReleaseError('Runtime bytes differ from source: ' + name)
    result = {
        'plugin': SLUG, 'version': version, 'archive': ARCHIVE,
        'zip_root': SLUG + '/', 'zip_bytes': archive.stat().st_size,
        'sha256': sha256(archive.read_bytes()),
        'files': [{'path': SLUG + '/' + name, 'bytes': len(content[name]), 'sha256': sha256(content[name])}
                  for name in sorted(content)],
        'runtime_capabilities': CAPABILITIES,
        'production_dependencies': {'composer_runtime_required': False, 'bundled_loader': 'includes/autoload.php'},
    }
    if manifest:
        stored = json.loads(Path(manifest).read_text(encoding='utf-8'))
        for field in result:
            if stored.get(field) != result[field]:
                raise ReleaseError('Manifest mismatch: ' + field)
    return result
