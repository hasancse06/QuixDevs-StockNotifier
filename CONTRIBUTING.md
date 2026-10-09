# Contributing

Once the repository is public, use Issues for reproducible bugs and focused feature requests. Include StockNotifier, WordPress, WooCommerce, PHP and optional-provider versions, product/stock-management type, selected recipient policy, expected behavior and reproduction steps. Remove private emails, store data, credentials and access tokens from reports.

For code changes, start with [architecture](docs/architecture.md) and [testing](docs/testing.md). Run unit tests and PHPCS. Inventory, recipient and delivery changes need a relevant regression test and isolated WooCommerce integration checks. Never run destructive fixture suites on a production database or allow test mail to reach real recipients.

Keep inventory writes and marketplace ownership changes out of this plugin. Preserve explicit provider selection, strict numeric validation, private admin actions, individual delivery outcomes and durable duplicate protection. Explain schema, hook and compatibility changes in the pull request.

Runtime files go in the production allowlist; presentation files belong in `docs` or `wordpress-org-assets`. Run `python3 bin/build-release.py` and verify the archive after changes to release contents. Do not commit vendor packages, local evidence, databases, credentials or generated ZIPs. See `.gitignore`.

Report suspected vulnerabilities using [SECURITY.md](SECURITY.md), rather than a public issue containing exploit details.
