# Recipient resolution

The sole active policy is `settings.provider`. WordPress is the default; there is no automatic provider detection mode. The resolver validates product existence, resolves variation ownership from the parent, checks availability, then delegates to exactly one provider. Missing accounts, invalid emails and unresolved ownership skip or pause work with a diagnostic reason. There is no fallback administrator or other-provider recipient.

`Recipient` contains user ID, name, validated account email, provider ID, parent product ID, store name and recipient type. Mailboxes are deduplicated by lowercase SHA-256 identity within an episode. Email is read from a verified WP_User, never arbitrary product metadata or a guest contact field. Administrator copies are separately enabled, validated, limited to ten and individually addressed; they require an eligible primary recipient policy too.

Availability states are Available, Not Installed, Inactive, and Unsupported API / Configuration Required. A newly unavailable choice is disabled in the form and rejected on the server. An already-selected unavailable choice stays selected and produces a warning. Workers revalidate before each message. Configuration revision mismatches cancel pending work, while completed history remains unchanged.

A delivery snapshot includes both account and mailbox identities. A changed owner, account email or assignment cancels that queued destination; it is not redirected to the new owner or expanded into an unexpected campaign. Unresolved episodes without snapshots can resolve later when their original selected integration becomes usable, provided the episode is still low and its configuration remains valid.

WordPress editing links use the recipient's `edit_post` capability, not the anonymous worker session. Marketplace destinations use safe same-site supported routes, with a public product fallback. Recipient preview is administrator-only, nonce-protected, read-only and reports the selected provider's resolution reason.
