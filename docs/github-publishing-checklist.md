# GitHub publishing checklist

Local preparation is complete. This document is a manual publishing handoff; no push, tag, remote release or WordPress.org submission has occurred.

## Prepared locally

- [x] Dedicated `quixdevs-stocknotifier-github` source folder, without unrelated projects or installed dependencies.
- [x] Branded README with store-owner installation, quick start, recipient matrix, actual screenshot gallery and creator/publisher credit.
- [x] Original supplied QuixDevs logo, StockNotifier mark, social preview, standard/retina banners and icons.
- [x] Six genuine admin screenshots and matching WordPress.org captions/assets.
- [x] About description, topics, release notes, contributing and security-reporting guidance.
- [x] `.gitignore` for dependencies, test/lint output, private configuration, databases, logs, archives and OS/editor files.
- [x] Runtime code preserved from the verified implementation; production-only builder retained.
- [x] Source package, relative links, image dimensions, exclusions and runtime ZIP integrity checked.

## Upload the repository

1. Create the repository under the intended GitHub account, named **`quixdevs-stocknotifier`**.
2. Upload the **contents** of `quixdevs-stocknotifier-github` into the repository root, including hidden `.github`, `.gitignore` and `.gitattributes`. Do not upload the enclosing folder, the workspace, ZIPs or `vendor`.
3. If using the prepared `quixdevs-stocknotifier-github.zip`, extract it first and upload the contents of its root folder. GitHub does not expand an uploaded ZIP into repository source.
4. Set About description/topics from [github-listing.md](github-listing.md). Upload [the social preview](branding/github-social-preview.png) in repository Settings. Keep Website blank until confirmed.
5. Check README image rendering and relative links in GitHub. Run the configured PHP 8.1/8.4 quality workflow; its remote status is unverified until it runs.
6. Confirm a private security-reporting channel, enable GitHub private vulnerability reporting if appropriate, and review the [security policy](../SECURITY.md).

When updating the existing repository, replace its earlier README and include `docs/branding` and `docs/screenshots` from this final folder. The README embeds the banner and six screenshots. Previously committed `docs/verification/` artifacts are not removed by uploading a clean folder or by `.gitignore`; remove that tracked directory separately if you want the public repository to match this cleaned source tree.

For a Git-based upload, run from the prepared source folder after confirming the remote URL:

```sh
git init -b main
git add .
git status --short
git diff --cached --stat
git commit -m "Prepare QuixDevs StockNotifier 1.0.0"
git remote add origin YOUR_CONFIRMED_REPOSITORY_URL
git push -u origin main
```

The URL is a placeholder. Review the staged files and do not force-add ignored local data. These commands are instructions; preparation did not execute publication.

## Create the installable release

The source repository contains tests and presentation assets; the installable plugin ZIP contains only production files. The canonical build location is `dist/quixdevs-stocknotifier.zip`, with its manifest and SHA-256 sidecar. Archives are ignored and belong under GitHub Releases, separately from source control.

```sh
python3 bin/build-release.py
python3 bin/validate-release.py
unzip -l dist/quixdevs-stocknotifier.zip
```

- [ ] Review [the release checklist](release-checklist.md), version declarations and recorded [test limits](testing.md).
- [ ] Review the final commit and tag `v1.0.0` when authorized.
- [ ] With the reviewed release tooling committed and uploaded, create an approved existing tag matching the plugin version and run **Prepare Draft Release** manually for that tag, or let its tag-push trigger run. It runs the reusable quality checks and creates only a draft. See [release-distribution.md](release-distribution.md).
- [ ] Review the draft and [prepared notes](github-release-notes.md); publish manually only when authorized.
- [ ] Attach `quixdevs-stocknotifier.zip`; verify the uploaded/downloaded checksum against the local build output.
- [ ] Update README installation links only after an actual release URL exists.
- [ ] Complete staging SMTP, then-current WordPress/WooCommerce, premium/provider and hosting checks before making broader compatibility claims.

The final upload folder preserves all application code and assets. Its main plugin header retains the `Author URI: https://quixdevs.com/` already present in the published repository; this metadata-only addition changes the archive checksum. The original runtime tests remain documented separately from the final packaging checks. Rebuild and retest when application behavior changes.

## Future WordPress.org submission

- [ ] Confirm the publishing account and contributor identity, naming/trademark availability and current submission requirements.
- [ ] Recheck Plugin Check, readme validation, staging behavior and tested version declarations.
- [ ] Submit the runtime ZIP only when authorized; acceptance remains the directory team's decision.
- [ ] After acceptance, place the listing PNGs in the SVN repository's top-level assets directory as described in [the handoff](../wordpress-org-assets/README.md).

No company URL, endorsement, download statistics, public release URL or successful remote CI result was invented.
