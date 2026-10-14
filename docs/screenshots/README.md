# Genuine admin screenshots

Captured from the working isolated local WordPress test site on 2026-10-10 (Asia/Dhaka), using WordPress 6.8.3, WooCommerce 10.0.4 and Chrome 154.0.8037.98. Products, accounts and email destinations are synthetic fixtures. Transport is intercepted; visible acceptance results are not claims of inbox delivery. Optional marketplace plugins are inactive in the final six-screen gallery, demonstrating the normal WordPress author configuration.

| Directory order | Image | What it shows |
| --- | --- | --- |
| 1 | [dashboard.png](dashboard.png) | Inventory counts, notification results and scheduler health |
| 2 | [settings.png](settings.png) | Numeric threshold, explicit recipient provider, copies and retention |
| 3 | [email-templates.png](email-templates.png) | Subject, rich-text editor, variables and preview |
| 4 | [low-stock-products.png](low-stock-products.png) | Paginated stock list, ownership and notification state |
| 5 | [activity.png](activity.png) | Episode and individual recipient outcomes |
| 6 | [tools.png](tools.png) | Private preview and confirmed recovery tools |

[settings-mobile.png](settings-mobile.png) is a browser viewport capture, not a mobile-device test. [installation.png](installation.png) records the native WordPress ZIP upload workflow. No interface elements or successful sends were fabricated. The six directory screenshots in `wordpress-org-assets` are byte-for-byte copies in the order above, matching `readme.txt`.

The [browser harness](../../tests/Browser/admin.cjs) documents capture and input checks. Use a disposable local store, synthetic data and a mail interceptor when recapturing. Keep screenshots out of the runtime plugin ZIP.

After changing originals, regenerate listing copies with `python3 bin/build-directory-assets.py`. The `--check` mode used in CI verifies byte-identical copies and preserves existing README links and readme screenshot order.
