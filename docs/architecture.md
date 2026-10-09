# Architecture

`QuixDevs\StockNotifier` is the sole application namespace. New hooks, options and tables use `quixdevs_stocknotifier_`; no legacy metadata or external plugin settings are imported. The main bootstrap declares identity/dependencies and delegates to a composition root. A bundled PSR-4 loader supports production without Composer.

## Components

- `Inventory/Stock_Monitor`: canonical WooCommerce stock-owner identity, persisted pre-update baselines, crossing/rearm transitions, durable episodes.
- `Inventory/Reconciliation`: bounded daily inventory pass, queue repair, expired terminal retention, confirmed manual batch tools.
- `Recipients`: isolated explicit providers and immutable validated recipient snapshots.
- `Notifications`: argument-specific Scheduler jobs, eligibility checks, persistent send claims, safe literal templates, private messages and transport outcomes.
- `Data/Notification_Repository`: parameterized values, fixed table names, indexed reads, transactions and recipient aggregation.
- `Support`: validated non-autoloaded configuration, strict original-string validation, private logging, connection-owned advisory locks.
- `Admin`: six focused screen classes and one nonce/capability-protected request controller. No public recipient endpoint.

## Owned schema

All tables use `$wpdb->prefix`, InnoDB and the site's charset/collation. `dbDelta()` installs the initial schema. There are no speculative migration adapters.

| Table suffix | Durable purpose | Principal keys |
|---|---|---|
| `items` | Canonical quantity, threshold generation, low latch, episode sequence and state | primary item ID; state; low/quantity; owner product ID |
| `events` | Stable item/episode, configuration revision, provider, crossing stock, status and retry time | unique item/episode; status/retry time/ID; creation time |
| `deliveries` | Recipient snapshot, normalized-email hash, attempts, current outcome and retry time | unique event/recipient hash; status/retry time; status/ID; event/status |
| `activity` | Private code, item/event IDs, allowlisted diagnostic JSON and UTC timestamp | creation time; item ID |

Custom tables are justified by transactional episode creation, database-enforced identities and per-recipient durable claims. Product metadata cannot enforce these multi-row guarantees. StockNotifier never writes WooCommerce inventory tables.

All durable UTC dates are converted to site time for display. Terminal history counters represent retained records, not lifetime totals. Delivery snapshots contain personal email addresses for operational purposes and are accessible only to administrators. Diagnostic logs omit addresses and message bodies.

## Concurrency and delivery limits

A MySQL/MariaDB `GET_LOCK()` name includes a hash of database, site table prefix and operation identity. Ownership lasts for the connection; a time-based lease cannot expire while a worker still sends. The final sender takes configuration then item locks. Configuration updates take the same configuration lock and refresh the non-autoloaded option cache. Stock transitions take only their item lock; current stock is reloaded inside it.

Episode creation and item updates commit together. Recipient expansion commits snapshots together. Unique indexes protect item/episode and event/mailbox identities. Jobs are independently guarded by argument identity. Action Scheduler's built-in database unique flag is deliberately false for recipient jobs because the tested store enforces hook/group-wide uniqueness, ignoring args. Recurring reconciliation uses the built-in uniqueness flag because only one recurring hook is desired.

Immediately before transport, a delivery becomes `sending` and its attempt increments. Exceptions or stale interrupted claims become `unknown`; they are never automatically retried. Returning true from `wp_mail()` records `accepted`, not inbox delivery. There is an unavoidable transport/database interruption window; exactly-once delivery is not claimed.

## Standard exceptions

`phpcs.xml.dist` applies WordPress Coding Standards. PSR-4 filenames retain class casing as requested. Machine identifier `wordpress` is not auto-capitalized. Exception messages are escaped at their admin output boundary. Current durable reads and plugin-owned allowlisted table identifiers have explicit, narrow database-check exceptions. PHPMailer’s public `AltBody` property retains its API casing. None of these exceptions authorize unsanitized form input or public recipient output.
