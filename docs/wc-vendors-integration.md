# WC Vendors integration

Verified with installed **WC Vendors Marketplace 2.7.2.2**; WC Vendors Pro remains untested.

The provider verifies `WCV_Vendors::get_vendor_from_product()` and `WCV_Vendors::is_vendor()` before availability. It requires the public ownership API's returned positive ID to equal the actual parent product author, then validates vendor eligibility and the WP_User mailbox. Store name uses `get_vendor_shop_name()` when callable. Roles alone do not establish ownership.

The inspected free implementation resolves a product's post author, following ancestors for variations and applying vendor-owner filters. StockNotifier resolves the real parent first and checks actual ownership rather than accepting a missing-product fallback. Unknown accounts, pending/non-vendor roles or conflicting owner results are unresolved; no alternate provider is tried.

The supported `WCV_Product_Controller::get_product_edit_link($product_id)` provides the dashboard editor route where available. A missing or cross-site URL falls back to the product permalink. Full dashboard edit permissions and Pro custom forms require separate staging verification.

Tests exercised the installed free role and product ownership APIs, a valid vendor and rejection after vendor eligibility was removed. Native WC Vendors roles were initialized in the isolated test setup; production StockNotifier never creates or alters vendor roles/settings.

Sources: [WC Vendors plugin](https://wordpress.org/plugins/wc-vendors/), installed release `classes/class-vendors.php` and `classes/front/class-wcv-product-controller.php`. No premium source was downloaded or bundled.
