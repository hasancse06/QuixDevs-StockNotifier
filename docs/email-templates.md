# Email templates

Subject and body are editable only by administrators. The body uses `wp_editor()` without media insertion. Stored HTML is sanitized with `wp_kses_post()`; subject control characters and oversized payloads are rejected. Rendering uses literal `strtr()` replacements, escaped values, safe URLs, native paragraphs and an original simple WooCommerce-compatible email layout. No PHP, shortcodes or expression evaluation occurs. Unknown variables stay literal.

| Variable | Meaning |
|---|---|
| PRODUCT_NAME | Canonical inventory product/variation name |
| PRODUCT_ID | Canonical inventory ID |
| PRODUCT_SKU | Inventory SKU or “Not set” |
| PRODUCT_URL | Public product/variation permalink |
| PRODUCT_EDIT_URL | Recipient-accessible editor/dashboard route; product permalink fallback |
| STOCK_QUANTITY | Fresh numeric stock at send time |
| STOCK_THRESHOLD | Validated episode threshold |
| RECIPIENT_NAME | Destination's verified account display name |
| AUTHOR_NAME | Resolved account display name |
| VENDOR_NAME | Resolved account display name |
| VENDOR_STORE_NAME | Verified vendor store name; site-name fallback |
| SITE_NAME | WordPress site name |
| SITE_URL | Site home URL |
| STORE_NAME | Verified vendor store name; site-name fallback |
| ALERT_DATE | Site-local send date using the configured date format |
| ALERT_TIME | Site-local send time using the configured time format |
| PRODUCT_TYPE | WooCommerce inventory product type |
| VARIATION_NAME | Formatted variation attributes; empty for non-variations |

HTML email uses one validated destination, a fixed Content-Type header and a plain alternative through PHPMailer's `AltBody`. Sender identity follows existing WordPress mail configuration. No global content-type filter is left behind.

Preview uses synthetic Wireless Headphones values and sends nothing. Test messages use the current submitted sanitized template, are explicitly labeled TEST EMAIL, are limited to one per minute per administrator, and are queued asynchronously with expiring user-authorized tokens. The worker repeats capability checks. Duplicate job invocation cannot send the same test token again.

`wp_mail()` true means accepted by mail transport. False is a recorded rejection and eligible for bounded retries. Exceptions after invocation or interrupted persistent sending claims are unknown, never automatically resent. The test environment intercepts mail before real transport; real SMTP/inbox behavior is not claimed as verified.
