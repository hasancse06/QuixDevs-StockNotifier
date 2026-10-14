# Final GitHub preparation verification

Prepared 2026-10-10 (Asia/Dhaka). The final source is consolidated in `quixdevs-stocknotifier-github`. No remote push, tag or public release was performed.

## README image fix

The published repository was inspected: its earlier README embeds only one screenshot, has no banner reference, and the repository lacks `docs/branding/`. The final folder contains the branded README with **seven embedded images** (banner plus six admin screens) and all referenced image files. Upload this folder's contents to the repository root to replace the earlier presentation.

## Final checks

- Application code, classes and assets remain unchanged from the recorded verified implementation.
- The plugin bootstrap retains `Author URI: https://quixdevs.com/` already present in the GitHub repository. This metadata addition is the only difference among the 40 production files compared with the original verified package. Its PHP syntax passes.
- The rebuilt installable ZIP passes integrity and content checks for all 40 production files.
- The supplied QuixDevs logo is preserved byte-for-byte; all six directory screenshot copies match the original genuine captures.
- All 94 local Markdown links across 23 documents and all five export dimensions pass validation. The README's seven images previously rendered successfully in real Chrome on desktop and at 390 px with no horizontal page overflow; the README remains unchanged in this consolidation.
- Git's own ignore engine confirms 31 exclusion examples. All intended source, CI, documentation, screenshots and branding files remain includable.
- The upload folder excludes dependencies, caches, local configuration, databases, logs, raw verification artifacts, generated archives and Git metadata.
- The original implementation directory was not edited during final consolidation.

## Installable ZIP

File: `dist/quixdevs-stocknotifier.zip` relative to this repository root.

SHA-256: `d36dfe7c3ebeda617b630e015d70a34af884ec9b15b5660fd27f3de7127b2d76`

The earlier runtime verification used the original package with SHA-256 `59e51cf0e402227283c43c4a9ba1e9118455f4472ad59f40bab7374446c2553a`. The final header metadata changes the checksum. The full integration and installation suites were not repeated for this metadata-only consolidation. See [testing](testing.md) for the actual checks and compatibility limits.

## Source upload

`../quixdevs-stocknotifier-github.zip` mirrors this final folder, including `.github`, `.gitignore` and `.gitattributes`. Extract it, then upload its root folder's **contents** to GitHub. Keep the installable ZIP separate as a release asset. The separate earlier update bundle is unnecessary when using this final source folder.

Previously committed `docs/verification/` files remain in GitHub until removed separately; `.gitignore` does not untrack existing files. That directory is deliberately absent from this cleaned source folder.

[Publishing instructions](github-publishing-checklist.md) · [Branding](branding/README.md)

## Packaging update

The 2026-10-14 runtime distribution build uses `dist/`, maximum ZIP compression, a file manifest and SHA-256 sidecar. The earlier checksum above is historical; [release-distribution.md](release-distribution.md) records the current artifact checksum and draft workflow. Production file bytes and version remain unchanged.
