# Release distribution — QuixDevs StockNotifier 1.0.0

Packaging and verification completed **2026-10-15 (Asia/Dhaka)**. This update changes release tooling, CI, packaging tests and documentation only. All 40 runtime files match their pre-task SHA-256 values; version **1.0.0** and all plugin behavior remain unchanged.

## Store-owner download

Open [GitHub Releases](https://github.com/hasancse06/QuixDevs-StockNotifier/releases), select a published release, then choose **Assets → quixdevs-stocknotifier.zip**. Upload that file through **WordPress Admin → Plugins → Add Plugin → Upload Plugin** with WooCommerce active.

Do not install GitHub's automatically generated **Source code (zip)** or a full developer repository archive. Drafts are for maintainer review and are not public installation downloads. No remote release was created during this task.

## Runtime-only package

The builder retains the original production allowlist: bootstrap, uninstall, readme and license at the top level, plus all supported runtime PHP classes, CSS/JavaScript and translation files. Production autoloading is bundled in `includes/autoload.php`; no Composer-installed production package is required by the current plugin. Composer dependencies are development tools only.

The ZIP has exactly one canonical root, `quixdevs-stocknotifier/`. Every included file is compared byte-for-byte with source, and required components are independently enumerated so a missing stock monitor, provider, editor, queue, validator, lifecycle file or asset makes the build fail. Future required runtime assets or production dependencies must be added deliberately to the packaging allowlist and validation requirements before release.

Excluded: tests, docs, CI, bin tooling, WordPress.org artwork, screenshots, branding, vendor/node_modules, caches, hidden/local files, unsupported temporary extensions and previous ZIPs. Runtime symlinks are rejected. The developer repository keeps its complete source, tests, documentation, screenshots and CI.

| Required behavior | Retained components |
| --- | --- |
| WooCommerce inventory and rearming | Stock_Monitor, Alert_State, Reconciliation |
| Threshold configuration and strict numeric validation | Settings, Validator, Settings_Page, admin.js |
| Template editing/rendering and email sending | Email_Templates, Template_Renderer, Email_Sender |
| Action Scheduler and dependency checks | Notification_Queue, Dependencies |
| Durable notification deduplication | Notification_Manager, Notification_Repository, Lock_Manager |
| WordPress author policy | WordPress_Author |
| Dokan vendor policy | Dokan_Vendor |
| WC Vendors policy | WCVendors_Vendor |
| PublishPress author assignments | PublishPress_Authors |
| Admin settings, stock lists and diagnostics | Admin, Dashboard, Low_Stock_List, Activity_Page, Tools_Page, View, Logger, admin.css |
| Runtime loading and lifecycle | Bundled loader, Plugin, Activator, Deactivator, recipient models/resolver and translations |

## Build and validate

Run from the repository root with Python 3.9+; Composer is not required just to build:

```sh
python3 bin/build-release.py
python3 bin/validate-release.py
```

Outputs:

- `dist/quixdevs-stocknotifier.zip` — installable production archive.
- `dist/quixdevs-stocknotifier.zip.sha256` — checksum with canonical filename.
- `dist/quixdevs-stocknotifier-manifest.json` — version, size, whole-archive SHA-256, per-file size/hash, required capability map and source commit/clean-tree metadata.

An explicit alternate directory is supported: `python3 bin/build-release.py --output-dir /path/to/output`. `--tag v1.0.0` additionally checks plugin/readme/tag versions. Maximum DEFLATE compression, fixed entry ordering/timestamps and regular-file permissions produce reproducible ZIP bytes on the tested toolchain. Different zlib/toolchain versions may have different compressed bytes; file hashes still identify unchanged runtime content. Nothing is pushed or published by the builder. Generated `dist/` files remain ignored by Git.

Current measured artifact:

| Field | Value |
| --- | --- |
| Filename | `quixdevs-stocknotifier.zip` |
| Root | `quixdevs-stocknotifier/` |
| Version | `1.0.0` |
| Runtime files | 40 |
| ZIP size | **67,163 bytes (65.6 KiB)** |
| SHA-256 | `3a2f824b7bb021c18c451ab4cd188b24c23e5d3f27a38cf93944b92f887f578d` |

The earlier compression output's hash is historical; use the generated sidecar for each reviewed build. The manifest's `worktree_clean` is false for an uncommitted local preparation. CI release artifacts must come from a clean existing tag checkout.

## File manifest

The generated JSON sidecar records individual file hashes. This is the complete current ZIP file list:

```text
quixdevs-stocknotifier/LICENSE
quixdevs-stocknotifier/assets/css/admin.css
quixdevs-stocknotifier/assets/js/admin.js
quixdevs-stocknotifier/includes/Activator.php
quixdevs-stocknotifier/includes/Admin/Activity_Page.php
quixdevs-stocknotifier/includes/Admin/Admin.php
quixdevs-stocknotifier/includes/Admin/Dashboard.php
quixdevs-stocknotifier/includes/Admin/Email_Templates.php
quixdevs-stocknotifier/includes/Admin/Low_Stock_List.php
quixdevs-stocknotifier/includes/Admin/Settings_Page.php
quixdevs-stocknotifier/includes/Admin/Tools_Page.php
quixdevs-stocknotifier/includes/Admin/View.php
quixdevs-stocknotifier/includes/Data/Notification_Repository.php
quixdevs-stocknotifier/includes/Deactivator.php
quixdevs-stocknotifier/includes/Dependencies.php
quixdevs-stocknotifier/includes/Inventory/Alert_State.php
quixdevs-stocknotifier/includes/Inventory/Reconciliation.php
quixdevs-stocknotifier/includes/Inventory/Stock_Monitor.php
quixdevs-stocknotifier/includes/Notifications/Email_Sender.php
quixdevs-stocknotifier/includes/Notifications/Notification_Manager.php
quixdevs-stocknotifier/includes/Notifications/Notification_Queue.php
quixdevs-stocknotifier/includes/Notifications/Template_Renderer.php
quixdevs-stocknotifier/includes/Plugin.php
quixdevs-stocknotifier/includes/Recipients/Dokan_Vendor.php
quixdevs-stocknotifier/includes/Recipients/Provider.php
quixdevs-stocknotifier/includes/Recipients/PublishPress_Authors.php
quixdevs-stocknotifier/includes/Recipients/Recipient.php
quixdevs-stocknotifier/includes/Recipients/Recipient_Interface.php
quixdevs-stocknotifier/includes/Recipients/Recipient_Resolver.php
quixdevs-stocknotifier/includes/Recipients/WCVendors_Vendor.php
quixdevs-stocknotifier/includes/Recipients/WordPress_Author.php
quixdevs-stocknotifier/includes/Support/Lock_Manager.php
quixdevs-stocknotifier/includes/Support/Logger.php
quixdevs-stocknotifier/includes/Support/Settings.php
quixdevs-stocknotifier/includes/Support/Validator.php
quixdevs-stocknotifier/includes/autoload.php
quixdevs-stocknotifier/languages/quixdevs-stocknotifier.pot
quixdevs-stocknotifier/quixdevs-stocknotifier.php
quixdevs-stocknotifier/readme.txt
quixdevs-stocknotifier/uninstall.php
```

## Verification actually performed

| Check | Result |
| --- | --- |
| Runtime preservation | All 40 pre-task file hashes unchanged; version unchanged |
| Existing unit suite | 35 tests, 51 assertions, pass |
| Packaging safeguards | 15 tests, pass: required files, excluded dev files, reproducibility, symlinks, corrupt/wrong-root/duplicate/traversal archives, tampering, secrets, versions and draft-only controls |
| Existing WordPress/WooCommerce integration suite, exact ZIP installed | 43 tests, 96 assertions; 40 pass and 3 expected skips because free marketplace plugins are absent |
| PHP syntax | 48 production/test PHP files pass |
| PHPCS/WPCS and Composer manifest | Pass under existing ruleset; Composer strict validation passes |
| Actionlint | 1.7.12 validates quality and release workflows |
| Secret scan | Official checksum-pinned Gitleaks 8.30.1 finds no leaks in maintained source and the extracted runtime package; built-in archive checks also reject known key/token patterns |
| Archive checks | Canonical filename/root, CRC integrity, exact runtime manifest/source bytes, required classes/assets, sidecar checksum and repeat build pass |
| Isolated installation | WP-CLI installs and activates the exact ZIP with WooCommerce active; all 40 installed files match the manifest |
| Native admin HTTP smoke test | Six screens return working StockNotifier markup; CSS and JavaScript load; numeric input attributes and absent optional-provider guards pass |
| Missing tag gate | Local preparation rejects absent v1.0.0; no tag is created implicitly |
| Screenshot synchronization | Six byte-identical copies verified, 1,155,374 duplicate bytes excluded from runtime |

Test environment: **PHP 8.4.13, WordPress 6.8.3, WooCommerce 10.0.4, MySQL 8.0.46** on a new dedicated Unix-socket database under a disposable temporary directory. The exact installable ZIP, not a symlink to the full source repository, is activated. External HTTP is blocked and `pre_wp_mail` intercepts all messages. The test mail fixture was corrected to honor the existing suite's captured-mail and simulated-result globals, then the complete integration suite passed. No plugin code was changed to resolve fixture failures.

Six native admin pages were requested through the local WordPress HTTP server: Dashboard, Low Stock Products, Email Templates, Settings, Activity and Tools. The smoke test invokes no stock/mail actions. Two PHP 8.4 deprecation entries in WordPress core `wp-includes/kses.php` were observed; no plugin fatal errors occurred. This task did not repeat the previous visual browser capture or native GUI upload test; its current installation is WP-CLI plus native admin HTTP verification.

PHP 8.1 is configured in remote CI but was not executed locally in this task. Optional/premium provider compatibility, real inbox delivery, latest WordPress/WooCommerce, broader hosting/browser/load tests and actual remote Actions/Release API execution remain unverified. Earlier free-provider compatibility evidence is retained in [testing.md](testing.md). Draft API behavior is covered with mocked GitHub CLI calls, not a claim that a live release has been created.

Reproduce local source checks:

```sh
composer install --no-interaction --prefer-dist
composer test
composer lint
python3 -m unittest discover -s tests/Packaging -v
python3 bin/build-directory-assets.py --check
python3 bin/build-release.py
python3 bin/validate-release.py
```

On an explicitly prepared isolated site with mail interception and optional providers absent:

```sh
QSN_WP_ROOT=/path/to/qsn-tests/wordpress vendor/bin/phpunit -c phpunit-integration.xml.dist
QSN_BASE_URL=http://127.0.0.1:8098 QSN_ADMIN_USER=... QSN_ADMIN_PASSWORD=... python3 tests/Packaging/installation_smoke.py
```

The integration bootstrap refuses databases outside its qsn-tests guards. Set browser install harness `QSN_INSTALL_ZIP` only when intentionally testing an alternate canonical ZIP path; otherwise it uses `dist/quixdevs-stocknotifier.zip`.

## GitHub draft-release procedure

1. Review and upload/commit the packaging changes yourself. The workflow must be present on the default branch for manual dispatch and in the tagged source used for building. This task does not commit or push them.
2. Review the release commit, then create/upload an approved existing **v1.0.0** tag matching both the plugin header and `readme.txt` Stable tag. The workflow does not create tags or bump versions. If that version already has a published release, use an approved future version rather than overwriting it.
3. Run **Actions → Prepare Draft Release → Run workflow**, entering the existing tag, or allow a push of an existing `v*` tag to trigger preparation.
4. The identity job validates the exact tag/clean commit. The reusable **Quality** workflow runs existing unit tests, PHP syntax, WPCS, packaging safeguards and runtime build validation on PHP 8.1 and 8.4.
5. The package job scans maintained source with official pinned Gitleaks, builds the ZIP/checksum/manifest, validates every runtime file, scans the extracted package, and passes only verified artifacts to the draft job.
6. Only the draft job has `contents: write`. It revalidates the downloaded artifact and remote tag commit, creates a release with explicit **--draft --verify-tag**, or refreshes assets on an existing draft. An existing published release is refused. It attaches the ZIP, checksum and manifest, never source/development files.
7. Review the draft notes and downloaded checksum, smoke-test on staging, and **publish manually only when approved**. The workflow contains no automatic publication step.

The source repository remains complete. CI artifacts are retained for 14 days for review; the GitHub Release asset keeps the canonical filename without a version suffix. Action references and the Gitleaks installer are pinned to reviewed commits/checksums. Full source history is not the same as the maintained-tree/ZIP secret scan; review historical exposures separately if needed.

Official references: [GitHub CLI draft/verify-tag options](https://cli.github.com/manual/gh_release_create), [GitHub token permissions](https://docs.github.com/en/actions/reference/workflows-and-actions/workflow-syntax), [Gitleaks commands](https://github.com/gitleaks/gitleaks).

## WordPress.org screenshot copies

All six `wordpress-org-assets/screenshot-N.png` files are byte-identical to their canonical `docs/screenshots` originals. They remain tracked so existing direct GitHub links and the directory asset handoff keep working. Regenerate them from the originals with `python3 bin/build-directory-assets.py`; CI's `--check` mode rejects drift. Captions and numbering remain unchanged. They add no bytes to the installation ZIP.

[README download section](../README.md#download-the-installable-plugin) · [Release workflow](../.github/workflows/release.yml) · [Runtime builder](../bin/build-release.py) · [Testing](testing.md)
