# PublishPress Authors integration

Verified with installed **PublishPress Authors 4.17.0**; Pro remains untested.

PublishPress is an editorial assignment system. Availability requires `get_post_authors()` and `MultipleAuthors\Classes\Utils::is_post_type_enabled('product')`. Product author assignments must already exist. StockNotifier checks the `author` taxonomy relationship before invoking the API, preventing fallback creation of editorial terms/relationships.

The researched documented `get_multiple_authors($post, $filter_the_author, $archive, $ignoreCache)` returns author objects or a WP_User fallback and is now deprecated in favor of `get_post_authors()`. StockNotifier uses `get_post_authors($product_id, true, false)`: bypass author cache and do not update assignments. The inspected API sorts assignments by term order. Only explicit assigned objects' mapped `user_id` accounts, or actual WP_User instances, are eligible.

Primary means the first assignment. If it is a guest without a mapped account, no secondary author is substituted. All Assigned Registered Authors skips guests and invalid accounts and deduplicates validated account emails. Each destination has a separate delivery row and private To header. Guest description, email/contact fields and term IDs are never treated as account IDs or destinations.

Installed tests exercised primary order, multiple registered accounts, guest exclusion and primary-guest skipping. The unconfigured product post-type state was also observed accurately as Unsupported API / Configuration Required. Pro-specific author category behavior and newer/older APIs remain unverified.

Sources: [official API guide](https://publishpress.com/knowledge-base/publishpress-authors-api/), [official source](https://github.com/publishpress/publishpress-authors), [API deprecation changelog](https://github.com/publishpress/publishpress-authors/blob/development/CHANGELOG.md). The implementation uses the actual inspected free release API rather than the deprecated signature.
