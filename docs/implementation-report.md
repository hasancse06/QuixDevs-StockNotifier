# Implementation report — QuixDevs StockNotifier 1.0.0

Completed implementation and local verification: **2026-10-10, Asia/Dhaka**.

## Delivery

An independent namespaced WooCommerce plugin was created in `quixdevs-stocknotifier/`. No historical metadata, hooks or business rules were imported. Nothing was published, pushed, deployed or submitted externally.

Release ZIP: **`../quixdevs-stocknotifier.zip`** relative to this plugin root. Its top-level directory is `quixdevs-stocknotifier/`. It contains 40 production files: bootstrap, uninstall, bundled loader/classes, lightweight admin assets, translation template, WordPress.org readme and GPL license. Tests, vendor packages, CI, screenshots, documentation, credentials and temporary configuration are excluded. The reproducible builder verifies archive integrity, unique safe paths and an installable bootstrap.

## Architecture and features

The main file contains plugin headers and lifecycle wiring. A PSR-4 loader and `Plugin` composition root assemble focused inventory, recipient, notification, data, support and native admin classes. Production requires no Composer runtime. New identifiers are isolated under `QuixDevs\StockNotifier`, `quixdevs_stocknotifier_`, and Scheduler group `quixdevs-stocknotifier`.

Four indexed InnoDB tables provide canonical inventory state, uniquely identified alert episodes, individual recipient claims/results and private diagnostics. This custom storage is necessary for transactional episode creation and per-recipient concurrency guarantees; it never changes WooCommerce stock tables. Defaults/configuration are small non-autoloaded options. Terminal history has bounded 90-day pruning, with ambiguous/unresolved work preserved for review.

Six top-level menu screens were implemented: Dashboard, Low Stock Products, Email Templates, Settings, Activity and Tools. They offer aggregate inventory/notification counters, paginated searchable/sortable stock, current recipient resolution, dated last alerts, individual outcomes, scheduler health, native settings and confirmed recovery operations. Genuine desktop/mobile screenshots are included in `docs/screenshots/`.

## Threshold validation

The default threshold is integer **5**. Native input attributes are type=number, min=0, step=1, inputmode=numeric and required, with maximum 2147483647. Accessible JavaScript guards typing, beforeinput, paste, drop and input/change events while preserving navigation, shortcuts, valid paste and spinner behavior. Invalid input has an inline live validation message.

PHP validates the original unslashed string with ASCII digits only, rejects non-strings and overflow, then normalizes/casts to an integer. Signs, whitespace, empty values, fractions, decimals, exponents and arbitrary strings are not silently converted. Invalid saves leave the previous option and notification state untouched. The retry-count field follows the same standard and is limited to 0–5.

## Recipient policy and compatibility

Exactly one native radio selection applies globally; WordPress Product Author is the default. Optional providers display accurate installed/active/API/configuration states. Unavailable providers cannot be newly selected. A previously selected unavailable integration remains selected with a warning and paused work; no alternate provider or arbitrary administrator is used.

| Integration | Implementation | Installed version actually exercised | Verification boundary |
|---|---|---|---|
| WordPress author | Actual product author → verified WP_User mailbox | WordPress 6.8.3 | Core/author-only tests; missing author and changed ownership covered |
| WooCommerce inventory | Canonical stock owner and supported before/after stock hooks | 10.0.4 | CRUD, REST, orders, restock, zero/negative/unmanaged, parent/variation tests |
| Dokan Lite | Product-vendor public API, matching actual owner, seller excluding staff, enabled vendor, WP_User email | 5.3.0 | Installed API tests pass for eligible/disabled seller; Pro/dashboard journey unverified |
| WC Vendors Marketplace | Vendor-from-product API, matching owner, vendor eligibility, account email/store name | 2.7.2.2 | Installed API tests pass for vendor/non-vendor; Pro/editor journey unverified |
| PublishPress Authors | Enabled product type, explicit ordered assignments, mapped users only | 4.17.0 | Primary/all registered and guest exclusion tests pass; Pro unverified |
| Dokan Pro | Compatible public API intent | Not installed/tested | No verified premium compatibility claim |
| WC Vendors Pro | Compatible public API intent | Not installed/tested | No verified premium compatibility claim |
| PublishPress Authors Pro | Compatible public API intent | Not installed/tested | No verified premium compatibility claim |

PublishPress primary means the first assigned author. A primary guest is skipped without selecting another author. All mode validates/deduplicates registered accounts and sends individually. Preview reports the selected provider, product, reason and validated destinations without mail/state changes. Queued snapshot ownership/email identities are rechecked before transport.

## Inventory and state machine

Canonical identity uses `get_stock_managed_by_id()`. Shared parent inventory produces one episode, while independently managed variations track their own numeric stock and use parent ownership. Real variation details are included in template data. Disabled management is not zero inventory; backorders and negative values use actual numeric quantities.

Missing baselines are captured from persisted old stock before an update, protecting first crossings while background initialization is incomplete. Healthy→low creates one durable episode and sets a low latch; later low observations do not create duplicates. Above-threshold restock rearms and invalidates pending old work. Configurations have a recipient-policy revision and a separate monitoring generation; provider changes preserve history and invalidate obsolete jobs without resending completed alerts. Threshold/enabled-policy changes baseline safely rather than creating a campaign.

The manual existing-stock tool first runs a bounded preview with affected, resolved, unresolved and estimated counts. Confirmation is bound to the user, revision, expiry and preview IDs. At most 500 previously unalerted items are queued per confirmed run. Other bulk tools require confirmation and never expose unauthenticated URL triggers.

## Async delivery, templates and protection

`quixdevs_stocknotifier_send_email` on WooCommerce Action Scheduler performs expansion and recipient-specific work. No inventory-hook or checkout path invokes mail synchronously. Stable job arguments, per-job connection locks and database unique identities suppress duplicate pending work. The Scheduler database store's hook/group-wide uniqueness flag is deliberately avoided for recipient jobs; independent argument identities are checked under plugin locks.

The sender rechecks product existence, canonical management, numeric low stock, enabled state, episode sequence, configuration revision, provider availability and recipient snapshot eligibility. It holds configuration and item locks while committing a persistent `sending` claim and invoking transport. True means accepted; false has bounded exponential retries (three by default); exceptions or stale interrupted claims become delivery unknown and never automatically retry. Completed claims cannot regress through duplicate jobs. Exactly-once delivery or inbox delivery is not claimed.

Templates use a native rich-text editor, sanitized HTML, an original WooCommerce-compatible wrapper and 18 literal escaped variables. Subject/header controls are rejected. Unknown placeholders remain literal; PHP and shortcodes never execute. WordPress editing links check the intended recipient's capability, Dokan uses its products dashboard to avoid unusable worker-session nonces, WC Vendors uses its public editor route, and public product URLs are the fallback. A plain alternative is supplied. Admin copies are explicit, validated, deduplicated and individually addressed. Synthetic preview/test email is administrator-only, rate-limited, labeled, asynchronous and separate from alert eligibility.

## Concurrency, recovery, security and performance

Connection-owned MySQL/MariaDB advisory locks include database/site identity and do not expire mid-send. Episode/item and recipient-snapshot writes are transactional. Indexed identities enforce one item/episode and event/mailbox combination. Current stock and policy are refreshed after relevant locks. The implementation treats transport/database interruption as uncertain, without unsafe automatic re-sends.

Recovery rotates through 100 queued rows per batch; unresolved episodes back off hourly. Catalog recovery visits 100 IDs per minute, with a new full pass at most one day after completion. No periodic full-catalog in-memory load or frontend inventory scan occurs. All admin requests require manage_options; mutations require task-specific nonces and POST; manual mail campaigns require confirmation. All dynamic output is escaped, HTML is sanitized, mailbox/header validation is strict, SQL values/identifiers are prepared or fixed allowlisted, and include files guard direct execution. No telemetry or external HTTP is used by plugin code.

Default uninstall preserves owned data. Explicit deletion drops only owned tables/options and stops owned scheduling. Deactivation preserves history and pending records so reactivation can restore valid work. Network-wide activation is unsupported; site-local operation is documented.

## Actual verification

| Check | Result |
|---|---|
| PHPUnit unit | 35 tests / 51 assertions, pass |
| WordPress/WooCommerce, all free providers configured | 43 cases / 104 assertions; 42 pass, 1 expected unavailable-provider skip |
| ZIP-installed author mode, optional providers inactive | 43 cases / 96 assertions; 40 pass, 3 integration-plugin skips |
| Installed free-provider API suite | 3 tests / 11 assertions, pass |
| Independent-process concurrency | Four concurrent stock decrements + four observers create one episode; four send workers invoke intercepted transport once |
| Real Chrome | Valid/invalid numeric entry, paste/drop/input/spinners, direct server tampering, nonce rejection, six native screens, preview and mobile captures pass |
| Native upload/activation | Exact ZIP installs through WordPress Admin and activates with WooCommerce; optional plugins inactive |
| Action Scheduler CLI | Actual expansion and recipient jobs processed; stored mail result accepted |
| Lifecycle/dependency | Default data retention, isolated opted-in cleanup and missing-WooCommerce bootstrap pass |
| PHP syntax / JavaScript syntax | Pass |
| PHPCS/WPCS | Zero errors/warnings with documented narrow PSR-4/custom-table/API exceptions |
| PHPCompatibilityWP | Zero findings for 8.1+ static analysis; minimum runtime not separately executed |
| WordPress Plugin Check 2.1.0 | Clean extracted ZIP reports no errors or warnings |
| Composer | Locked development dependencies installed; no known vulnerability advisories reported at resolution |

Runtime actually tested: **PHP 8.4.13, MySQL 8.0.46, WordPress 6.8.3, WooCommerce 10.0.4, Action Scheduler 3.9.2, Chrome 154.0.8037.98**. WordPress.org Tested up to 6.8 reflects this actual core series. PHP 8.1, WordPress 6.8 and WooCommerce 10.0 minimum declarations are support requirements, not separate runtime test claims.

Mail was intercepted with `pre_wp_mail` and external HTTP blocked in an isolated temporary site. Real SMTP/inbox delivery was not tested. A PHP 8.4 str_getcsv deprecation came from WooCommerce's bundled Scheduler CLI and is recorded in its runner log; no plugin failure resulted. Initial fixture issues and the Scheduler readiness/uniqueness bugs found during development were corrected and rerun. Recorded results and commands are in `docs/testing.md`. Raw machine-specific `docs/verification/` artifacts are retained locally and excluded from this public source folder.

## Remaining limits and release readiness

Premium plugins, end-to-end authenticated vendor editor flows, minimum-version PHP runtimes, MariaDB hosting, latest WordPress/WooCommerce versions, non-Chromium/mobile hardware input, large-store load tests and real SMTP/inbox behavior remain unverified. Direct SQL stock mutations and transient unobserved crossing/restock cycles cannot be reconstructed reliably. A manual existing-stock run excludes items with a previous episode. The system preserves ambiguous/unresolved history for administrative review.

GitHub presentation is prepared with README, screenshots, topic/About suggestions, developer credit, license, changelog, lockfile and local/CI test commands. WordPress.org preparation includes dependency/header/text-domain compliance, five tags, readme, safe native UI, translation template, actual screenshots and icon/banner design specifications. GitHub preparation now includes finished social preview, original icon, standard/retina banners, supplied QuixDevs logo and matching WordPress.org screenshot assets. Latest-version staging tests, premium testing and public submissions remain separate checklist items. No download/review/endorsement claims or company URL were invented.

Install the ZIP using **Plugins → Add Plugin → Upload Plugin**, activate WooCommerce first, then configure **StockNotifier → Settings**, preview recipients in **Tools**, and preview/test mail in **Email Templates**. Review the release checklist before public release. The deliverable is implemented and locally verified, with its remaining production-matrix limitations disclosed rather than certified away.

## GitHub presentation preparation

The dedicated `quixdevs-stocknotifier-github` folder follows the ProductClock presentation conventions. It includes the branded README, six-screen real gallery, creator/publisher credit, editable artwork, listing/release/publishing guides, contributing/security guidance, robust `.gitignore` and clean source package. All application code and assets remain identical to the verified implementation. The final main plugin header preserves the Author URI already present in the published GitHub repository; the metadata-only addition changes the installable ZIP checksum. Final packaging checks are recorded in `docs/github-preparation-report.md`. Presentation assets are excluded from the runtime ZIP; vendor packages, caches, local logs and credentials are omitted from the upload folder. No repository was created remotely, pushed or published.
