# Release checklist

## Implemented preparation

- [x] Independent namespace, identifiers, bundled PSR-4 loader and GPL-2.0-or-later license.
- [x] Required WooCommerce plugin header and graceful runtime checks.
- [x] Strict numeric server/browser validation, explicit provider selection and private previews.
- [x] Stock-owner identity, durable unique episodes, restock rearming and recipient results.
- [x] Action Scheduler delivery/recovery, bounded retries and unknown-send safeguards.
- [x] Capability and nonce gates, safe templates, private single-address mail.
- [x] GitHub README, five-tag WordPress readme, changelog, documentation and artwork specs.
- [x] Actual local admin screenshots; no fabricated marketplace/inbox claims.
- [x] Production-only release builder and archive-path validation.

## Verification evidence

Consult [implementation-report.md](implementation-report.md) and [testing.md](testing.md) for recorded results. Raw local `verification/` artifacts are excluded from the public source package. Do not infer unexecuted tests from checked preparation items.

Before public distribution:

- [ ] Review the final report and residual Plugin Check findings, if any.
- [ ] Independently smoke-test PHP 8.1 and each supported hosting/database combination.
- [ ] Test the latest stable WordPress/WooCommerce versions before broad compatibility claims.
- [ ] Test real SMTP and deliverability on a staging mailbox; transport acceptance does not prove inbox delivery.
- [ ] Verify full authenticated vendor dashboard editor routes on the store's configured marketplace.
- [ ] Verify premium editions before advertising tested premium compatibility.
- [ ] Run non-Chromium accessibility/input checks and large-store load testing.
- [x] Export the branded social preview, directory banners and icons; preserve editable sources and the supplied logo.
- [ ] Confirm the WordPress.org contributor account and review directory guidelines at submission time.
- [ ] Create/publish the GitHub repository only after separate authorization.
- [ ] Submit to WordPress.org only after separate authorization.

Minimum-version declarations express support intent; the report lists exact installed versions actually exercised. The build is prepared for review and install testing, not an unqualified certification of all production hosting and vendor configurations.
