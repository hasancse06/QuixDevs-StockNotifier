# QuixDevs StockNotifier v1.0.0 – Low Stock Alerts for WooCommerce

Initial independent release of QuixDevs StockNotifier by QuixDevs.

Monitor Stock. Alert Vendors. Stay Informed.

## Included

- Global low-stock threshold, default 5, with strict whole-number browser and server validation.
- One explicit recipient provider: WordPress Product Author, Dokan, WC Vendors or PublishPress Authors.
- Simple products, shared parent inventory and independently managed variations.
- One durable episode per threshold crossing, restock rearming and per-recipient results.
- Background Action Scheduler delivery, bounded retries, duplicate protection and recovery tools.
- Native rich-text email templates, 18 variables, safe previews and labeled test mail.
- Private dashboard, inventory list, settings, activity and confirmed bulk tools.
- Safe activation baselines and default data retention on uninstall.

## Installation

Download **quixdevs-stocknotifier.zip** from this release's Assets. With WooCommerce active, upload it through **Plugins → Add Plugin → Upload Plugin**, install and activate. Open **StockNotifier → Settings**, select your threshold/provider, preview recipients in Tools and test the template before relying on alerts. GitHub's generated source archives are development packages.

Requirements: WordPress 6.8+, WooCommerce 10.0+, PHP 8.1+, and MySQL/MariaDB with InnoDB and connection-owned GET_LOCK support. No Composer or premium integration is required on the store.

## Verification boundary

Locally exercised on PHP 8.4.13, MySQL 8.0.46, WordPress 6.8.3 and WooCommerce 10.0.4. Installed free API tests used Dokan Lite 5.3.0, WC Vendors Marketplace 2.7.2.2 and PublishPress Authors 4.17.0. Recorded checks include unit/integration, concurrency, Chrome input validation, PHPCS, Plugin Check and native ZIP installation.

Premium editions, full vendor editor flows, real SMTP/inbox delivery, minimum-version runtimes and broader hosting/browser/load coverage remain unverified. Mail transport acceptance does not prove delivery. WP-Cron can delay background processing. Network activation is unsupported. See the repository's testing guide and implementation report for complete limits and expected skips.

Copyright © 2026 QuixDevs. GPL-2.0-or-later. Created by Md Abu Hasan (M A Hasan).

Maintainer: copy this body into the authorized GitHub release and attach the verified runtime ZIP. Confirm the release asset checksum before publication. These notes do not create a release.
