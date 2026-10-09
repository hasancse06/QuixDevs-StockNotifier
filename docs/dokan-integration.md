# Dokan integration

Verified with installed **Dokan Lite 5.3.0**; Dokan Pro remains untested.

The provider requires callable `dokan_get_vendor()`, `dokan_get_vendor_by_product()` and `dokan_is_user_seller()`. It resolves `dokan_get_vendor_by_product($product_id, true)`, requires a positive ID matching the actual parent product `post_author`, and calls `dokan_is_user_seller($vendor_id, true)` to exclude staff. It loads the vendor with an explicit ID, verifies `get_id()` and `is_enabled()`, then obtains the corresponding WP_User email. Store name comes from `get_shop_name()` when supported.

`dokan_get_vendor($vendor_id = null)` otherwise defaults to the current user, so StockNotifier always supplies the verified owner. `dokan_is_user_seller($user_id, $exclude_staff = false)` supports explicit staff exclusion in the tested version. No role-only ownership assumption or vendor database query is used.

`dokan_edit_product_url()` was inspected. Its legacy route can contain a session-bound nonce generated for the worker, which would not authorize the emailed vendor. StockNotifier links to `dokan_get_navigation_url('products')` instead, where the authenticated vendor can choose their product. A missing or cross-site route falls back to the public product page. The full vendor dashboard editing journey remains a staging check.

Installed tests verified an eligible owner and rejected disabled selling. Product ownership changes are covered by worker snapshot validation. Premium staff/editor variations are not claimed as verified.

Sources: [official Dokan repository](https://github.com/getdokan/dokan), [public functions](https://github.com/getdokan/dokan/blob/develop/includes/functions.php), [product functions](https://github.com/getdokan/dokan/blob/develop/includes/Product/functions.php). Version-specific behavior was checked against the downloaded free release source.
