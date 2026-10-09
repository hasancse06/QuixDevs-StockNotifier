=== QuixDevs StockNotifier ===
Contributors: hasanonline
Tags: woocommerce, low stock, inventory alerts, vendor notifications, stock management
Requires at least: 6.8
Tested up to: 6.8
Requires PHP: 8.1
Requires Plugins: woocommerce
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically email WooCommerce product authors or selected vendors when managed inventory crosses your low-stock threshold.

== Description ==

QuixDevs StockNotifier — Low Stock Alerts for WooCommerce.

Monitor Stock. Alert Vendors. Stay Informed.

Choose exactly one recipient source: WordPress Product Author (default), Dokan Vendor, WC Vendors, or PublishPress Authors / Pro. Optional providers are never required for standard author mode. Unavailable providers pause affected work without selecting another recipient.

* Global threshold defaults to 5. Use ASCII digits only; no signs, spaces, fractions, decimals or exponents. Supported range: 0–2147483647.
* One alert episode per crossing. Restocking above the threshold rearms future alerts.
* Supports managed simple products, shared variable-product inventory, and independently managed variations.
* Asynchronous Action Scheduler processing, individual recipient results, bounded retries, and recovery tools.
* Native rich-text templates, safe variables, previews and clearly labeled synthetic test email.
* Private administrator dashboard, stock list, activity and confirmed bulk tools.
* No telemetry. No stock, price, order, status or vendor ownership changes.

Activation and threshold changes do not trigger a catalog-wide notification campaign. Tools includes an explicit preview and confirmation workflow for at most 500 previously unalerted inventory items per run.

PublishPress product assignments must be enabled. Primary means first in assignment order; guests without a verified linked WordPress user are skipped. All Assigned Registered Authors sends separate messages and deduplicates email addresses.

Dokan Lite 5.3.0, WC Vendors Marketplace 2.7.2.2, and PublishPress Authors 4.17.0 were exercised through their installed free APIs. Premium versions are not verified. Tested core environment: WordPress 6.8.3, WooCommerce 10.0.4, PHP 8.4.13, MySQL 8.0.46.

Requires WooCommerce 10.0 or newer and MySQL/MariaDB InnoDB with GET_LOCK support. Activate per site; network activation is unsupported. SMTP/inbox delivery and premium integrations require separate staging verification. A true wp_mail result indicates transport acceptance only.

== Installation ==

1. Install and activate WooCommerce.
2. Upload quixdevs-stocknotifier.zip in Plugins → Add Plugin → Upload Plugin.
3. Activate QuixDevs StockNotifier.
4. Configure StockNotifier → Settings.
5. Preview recipients in Tools and preview/test templates in Email Templates.

No Composer or premium marketplace plugin is needed on your store.

== Frequently Asked Questions ==

= What stock values are monitored? =

Only actual numeric quantities for stock-managed inventory. Parent-shared variations use one parent episode. Independent variation quantities have their own episode. Backorders and negative stock follow the numeric value. Unmanaged stock is never treated as zero.

= Can threshold 0 send out-of-stock-only alerts? =

Yes. Keep Enable Out-of-Stock Alerts checked. Disabling it suppresses crossings at zero or negative inventory.

= Does installation email existing low-stock products? =

No. Baselines initialize safely in bounded batches. Use the explicit existing-stock preview and confirmation tool if needed.

= What happens if the selected marketplace disappears? =

Its selection is preserved and work pauses. There is no automatic fallback. Obsolete configuration jobs are invalidated when the selected policy changes.

= What happens after a failed or interrupted send? =

Rejected sends have bounded retries. Ambiguous interrupted sends show Delivery unknown and are never retried automatically. Exactly-once or inbox delivery is not promised.

= Why are notifications delayed? =

Action Scheduler requires a working runner. WP-Cron depends on traffic unless a server cron is configured. Inspect scheduler health and failed actions in Tools.

= What data is retained? =

Plugin-owned inventory baselines, episodes, individual recipient outcomes and private diagnostics. Terminal history is pruned after 90 days in bounded batches; unresolved or ambiguous records remain for review. Data is preserved on uninstall unless deletion was explicitly enabled.

== Screenshots ==

1. Dashboard with inventory and notification counts and scheduling health.
2. Global numeric threshold and explicit recipient provider settings.
3. Editable email templates, variables and synthetic preview.
4. Paginated stock list with recipient previews and notification states.
5. Activity with individual recipient outcomes and authorized retry controls.
6. Recovery tools and explicit existing-stock preview/confirmation.

== Changelog ==

= 1.0.0 =
* Initial independent QuixDevs StockNotifier implementation.
* Strict global numeric threshold, recipient providers, asynchronous episodes, templates, private admin screens and recovery.

== Upgrade Notice ==

= 1.0.0 =
Initial release. Activation establishes monitoring baselines without sending existing-stock campaigns.
