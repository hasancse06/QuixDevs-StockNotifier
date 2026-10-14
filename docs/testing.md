# Testing

## Recorded environment

Verification completed 2026-10-10 (Asia/Dhaka) using PHP 8.4.13, MySQL 8.0.46, WordPress 6.8.3, WooCommerce 10.0.4, Action Scheduler 3.9.2, Chrome 154.0.8037.98, PHPUnit 9.6.38, PHP_CodeSniffer 3.13.6, WordPress Coding Standards 3.4.1 and Plugin Check 2.1.0. Free providers: Dokan Lite 5.3.0, WC Vendors Marketplace 2.7.2.2 and PublishPress Authors 4.17.0.

The test site is disposable under `/private/tmp/qsn-tests/wordpress`; its database is `qsn_test` on the dedicated socket `/private/tmp/qsn-tests/mysql.sock`. External HTTP is blocked by a test-only MU plugin. `pre_wp_mail` intercepts all messages; no real vendor email or SMTP transport is used. No live production database was accessed. No unrelated application code is required for these tests.

## Results

The evidence filenames below identify the original local run artifacts. Raw `docs/verification/` files are deliberately omitted from the public source folder and ignored by Git; summarized results and test sources are retained here. Fresh local runs can regenerate evidence without committing machine-specific paths or logs.

| Check | Actual result | Evidence |
|---|---|---|
| Unit suite | 35 tests, 51 assertions, pass | `verification/unit.txt` |
| All free providers active/configured | 43 tests, 104 assertions, 42 pass / 1 intentional skip | `verification/integration-all.txt`, `.xml` |
| Optional providers inactive, ZIP installed | 43 tests, 96 assertions, 40 pass / 3 provider skips | `verification/integration-author-only.txt`, `.xml` |
| Installed free-provider API tests | 3 tests, 11 assertions, pass | `verification/integration-providers.xml` |
| Independent-process concurrency | Four concurrent stock decrements + four observers → one episode; four send workers → one intercepted message | `verification/concurrency.txt` |
| Browser | Numeric controls, typing/paste/drop/input, spinner, strict server rejection, nonce failure, six screens, preview and mobile capture pass | `verification/browser.txt` |
| WordPress Admin ZIP installation | Native upload, extraction and activation with WooCommerce; optional integrations inactive | `verification/zip-installation.txt` |
| Real Action Scheduler runner | Expansion and individual delivery actions completed; stored outcome accepted | `verification/action-scheduler.txt` |
| Lifecycle | Default preservation and opted-in owned-data deletion pass; product/stock/unrelated options unchanged | `verification/lifecycle.txt` |
| Missing WooCommerce | Graceful bootstrap, no fatal error | `verification/dependency-failure.txt` |
| PHPCS / WPCS | Zero errors, zero warnings under documented ruleset | `verification/phpcs.json` |
| PHP compatibility analysis | No findings for PHP 8.1+; static analysis only | `verification/php-compatibility.txt` |
| PHP syntax | All production PHP files pass; JavaScript also passed `node --check` | `verification/php-syntax.txt` |
| Plugin Check, clean ZIP contents | No reported errors or warnings | `verification/plugin-check.txt` |

The all-provider suite skips the “unavailable provider cannot be newly selected” case because every provider is available. That case runs in the author-only suite. The author-only suite skips the three installed-provider behavior tests because those plugins are inactive. Skips are not counted as passed compatibility checks.

## Reproduce unit and static checks

```sh
composer install --no-interaction --prefer-dist
vendor/bin/phpunit -c phpunit.xml.dist
vendor/bin/phpcs --standard=phpcs.xml.dist
vendor/bin/phpcs --standard=PHPCompatibilityWP --runtime-set testVersion 8.1- includes quixdevs-stocknotifier.php uninstall.php
find includes -name '*.php' -print0 | xargs -0 -n1 php -l
node --check assets/js/admin.js
python3 -m unittest discover -s tests/Packaging -v
python3 bin/build-release.py
python3 bin/validate-release.py
```

The lockfile is constrained to PHP 8.1 so CI dependency resolution does not accidentally require the developer's PHP 8.4 runtime. The reusable GitHub quality workflow runs unit, lint, syntax and package checks on PHP 8.1 and 8.4. The draft-release workflow reuses it before producing verified runtime-only release assets. See [release distribution](release-distribution.md) for the current packaging checks and exact artifact paths. That remote workflow has not been executed or claimed as passing.

## Reproduce integration checks safely

Create a fresh local WordPress site with database name `qsn_test` and a dedicated socket path containing `/qsn-tests/`. Bootstrap refuses unrelated storage. Install WooCommerce and the plugin. Before running, block external HTTP and intercept `pre_wp_mail` in a test-only MU plugin. The tests truncate only StockNotifier's owned test tables and create synthetic products/accounts, so never relax the storage guards for a live site.

```sh
QSN_WP_ROOT=/private/tmp/qsn-tests/wordpress vendor/bin/phpunit -c phpunit-integration.xml.dist
QSN_WP_ROOT=/private/tmp/qsn-tests/wordpress php tests/concurrency.php
QSN_WP_ROOT=/private/tmp/qsn-tests/wordpress php tests/lifecycle.php
```

`tests/concurrency.php` additionally expects the isolated MU mail interceptor to append a JSON line to `QSN_MAIL_CAPTURE` when that environment variable is set. The provided verification used a locked file append under `/private/tmp/qsn-tests/` and wrote only the synthetic subject, never a real address. The concurrency test spawns fresh PHP processes, avoiding a forked shared database connection.

For installed provider tests, activate each free release and complete its native installation. WC Vendors roles must exist; PublishPress must enable the product post type. Test setup may initialize native provider fixtures in the disposable site; production StockNotifier never changes provider settings or roles.

## Browser checks

Use Playwright with Chrome and a local-only PHP server. Install Playwright in an external development directory or an ignored local `node_modules` directory. `admin.cjs` accepts `QSN_CHROME` for the Chrome executable; the recorded run used macOS Chrome. Pass test administrator credentials through environment variables; no private credentials are bundled in production files.

```sh
QSN_BASE_URL=http://127.0.0.1:8097 QSN_ADMIN_USER=... QSN_ADMIN_PASSWORD=... node tests/Browser/admin.cjs
```

`install.cjs` verifies the actual upload workflow and expects the plugin to be absent from the disposable site while WooCommerce is active. It uses `dist/quixdevs-stocknotifier.zip`, or the explicit `QSN_INSTALL_ZIP` path when set. The browser harness aborts non-local network requests and stores genuine screenshots in `docs/screenshots/`.

## Test limitations

No premium source or paid plugin was bundled. No real SMTP/inbox delivery, minimum-PHP runtime execution, MariaDB runtime, full vendor-dashboard edit journey, non-Chromium/browser-mobile keyboard, multisite network activation, large-catalog benchmark, hosting mail-failure semantics or latest WordPress/WooCommerce release matrix was executed. Local findings do not certify those configurations.

WooCommerce/Action Scheduler emitted a PHP 8.4 `str_getcsv` deprecation in its own CLI runner. Its initial migration needed to complete before group-limited runs. Both are visible in verification history and are external to StockNotifier's code. StockNotifier's final package static and integration checks have no reproducible plugin failures outstanding.
