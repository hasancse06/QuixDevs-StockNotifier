# Stock monitoring and episodes

Use actual `WC_Product::get_stock_quantity()` only when `managing_stock()` is true. Canonical inventory is identified with `get_stock_managed_by_id()`. Shared parent stock gets one item, while independent variation stock gets its own item and resolves ownership from the parent. A variation switching to shared stock retires its independent baseline. Product status labels alone are not inventory.

WooCommerce `woocommerce_product_before_set_stock` / `woocommerce_variation_before_set_stock` establish missing or obsolete baselines from a fresh persisted product. The matching `*_set_stock` hooks observe committed quantity. This covers supported WooCommerce CRUD, stock reduction/restoration, admin editing, REST updates and API-driven imports. Native low-stock actions are not the deduplication mechanism.

| Observation | Action |
|---|---|
| First numeric quantity or new monitoring generation | Baseline only; no automatic episode |
| Healthy quantity becomes <= threshold | Increment item sequence, create unique event, queue expansion |
| Still <= threshold | Keep same low latch and episode |
| Quantity rises above threshold | Cancel pending old work and rearm |
| Management removed, item deleted, or canonical identity changes | Retire/cancel pending old identity |
| Provider policy changes | Invalidate obsolete queued work, preserve history; do not resend completed episodes |

Pre-update baselining avoids suppressing a first real crossing while initial background discovery is incomplete. New products created already low are baselined safely. Configuration generations change for threshold, enabled or out-of-stock-policy changes. Provider-only revisions do not reset the low latch.

Threshold values are ASCII digit strings, `0..2147483647`. PHP rejects non-string inputs, signs, whitespace, fractions, decimal notation, exponents and overflow before casting. Native browser controls include type=number, min=0, step=1, inputmode=numeric and required, with keyboard, paste, drop, beforeinput and input/change enhancements. Invalid saves preserve the entire previous configuration and notification state.

Negative inventory and backorders follow their numeric values. Disabling out-of-stock alerts suppresses automatic crossings at <=0, even if the threshold is larger. A suppressed crossing remains latched until restock, avoiding a later low-level duplicate.

Recovery repairs 100 queued records per minute with a rotating cursor and retries unresolved resolution at most hourly per episode. Inventory recovery visits 100 IDs per batch, with the next full pass starting one day after the previous pass completes. Direct SQL writes are unsupported: recovery can discover a persistent change, but cannot reconstruct unobserved transient crossings.

Existing-stock tools preview in batches, bind confirmation to the initiating administrator, revision, expiry and preview IDs, and queue at most 500 previously unalerted items. They never resend an item that already has an episode. Changing threshold alone does not authorize a campaign.

Sources: [WooCommerce stock functions](https://woocommerce.github.io/code-reference/files/woocommerce-includes-wc-stock-functions.html), [variation stock inheritance](https://woocommerce.github.io/code-reference/files/woocommerce-includes-class-wc-product-variation.html). Actual implementation signatures were also inspected in the installed WooCommerce 10.0.4 source.
