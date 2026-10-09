# StockNotifier branding

Presentation artwork follows the QuixDevs ProductClock family: navy `#091827`, teal `#35e2cc`, white `#f4fbff` and muted blue `#b3c8d7`. The original StockNotifier mark combines an inventory box, descending stock bars and an envelope. No WordPress, WooCommerce or marketplace logo is used.

The maintainer-supplied [QuixDevs logo](quixdevs-logo.png) is preserved byte-for-byte at its original 164 × 45 pixels. Colors and proportions are unchanged. SVG compositions embed the original PNG, so exports are self-contained and do not depend on a Desktop path or external URL. Larger display sizes retain the supplied raster's original level of detail.

Social preview and banners include **Created by M A Hasan**, matching the ProductClock presentation convention. Official plugin author, publisher and copyright holder remain **QuixDevs**.

| Editable source | Export |
| --- | --- |
| [quixdevs-logo.png](quixdevs-logo.png) | Original supplied wordmark |
| [github-social-preview.svg](github-social-preview.svg) | [1280 × 640 social preview](github-social-preview.png) |
| [wordpress-banner.svg](wordpress-banner.svg) | [772 × 250 banner](../../wordpress-org-assets/banner-772x250.png), [1544 × 500 banner](../../wordpress-org-assets/banner-1544x500.png) |
| [stocknotifier-mark.svg](stocknotifier-mark.svg) | [128 × 128 icon](../../wordpress-org-assets/icon-128x128.png), [256 × 256 icon](../../wordpress-org-assets/icon-256x256.png) |

PNGs are rendered and visually checked. Typography uses Arial, Helvetica or sans-serif. To reproduce with Node.js and Sharp installed, run `node bin/render-branding.mjs`. If Sharp is installed outside the repository, set `QSN_SHARP_MODULE` to its absolute module path. This optional artwork tooling adds no production dependency. Vector editors can also export at the documented dimensions.

Upload `github-social-preview.png` through the repository's Social Preview setting when publishing. Keep presentation artwork in `docs/branding` and `wordpress-org-assets`; the runtime builder excludes both. See [the WordPress.org handoff](../../wordpress-org-assets/README.md) and [publishing checklist](../github-publishing-checklist.md).
