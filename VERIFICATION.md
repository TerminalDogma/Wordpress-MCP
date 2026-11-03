# Shared Content 1.1.0 verification

This is an installable release candidate for local/staging testing, with actual WordPress, ACF and browser coverage. It is not a certification of every production-readiness requirement in the original project brief.

## Actual environment and results

WordPress 7.1.4, PHP 8.3.36, MariaDB 11.4 and ACF Free 6.8.10, using pinned images, a main site and three independent participating subsites.

- **100 core integration checks passed**, including a 205-record batching exercise, global edits from another subsite, falsey overrides, reset, stale edits, native editing, relationships, media, noindex/sitemaps, capabilities, publication, lifecycle, queue failures and retries.
- **Nine HTTP checks passed** through Apache, including published/draft access, nonce authorization, editorial forms and two simultaneous native saves yielding one success and one conflict.
- The non-image attachment URL check passed.
- **27 real ACF adapter assertions passed**: field registration, key/name resolution, raw/formatted dates, local writes, locks, empty overrides, reset, conflicts, permissions, relationship conversion, unrelated ACF behavior, upgrade role policy and authenticated form saves with 403/409 rejection.
- **Chromium/Playwright workflow passed**: sign-in, actual ACF calendar controls, global date save, preservation of Site B's override, resetting that override, newest-value inheritance and no JavaScript page errors during the workflow.
- PHP syntax checks passed for all plugin/example files. The original draft was rerun in its separate environment before integration; its old report was not treated as proof of the new adapter.
- A local background worker processed queued work without errors. Global saves remain distinct from confirmed materialization on participating sites.

## Reproduction

Use a disposable project/port. These tests create records, users and media and deliberately inject failures:

```sh
sh bin/shared-content.sh setup
MSC_DISPOSABLE_TESTS=1 sh bin/shared-content.sh test
sh bin/shared-content.sh demo
python3 tools/package-msc.py
```

Optional browser testing uses Python Playwright 1.62.0 and Chromium, or `MSC_BROWSER_EXECUTABLE=/path/to/chromium`. After creating a fresh demo, run `MSC_DISPOSABLE_TESTS=1 python3 tests/shared-content/browser.py`. It changes the demo date and resets Site B's override. CI provisions a fresh network and runs these checks, packaging, and the WordPress ZIP installer.

## Limits and remaining work

ACF support covers generated, schema-owned custom scalar fields and single Post Object relationships. It does not import arbitrary ACF schemas, turn a native Gutenberg save into a global edit, support ACF Pro repeaters/flexible content, bridge owner-scoped media into ACF image controls, or promise `get_fields()` enumeration. Unsupported data remains in the full MSC editor where its type is supported. See [the adapter guide](multisite-shared-content/docs/acf.md).

The network schema editor is an advanced JSON form. Capabilities are generic rather than per-CPT configurations. Queries use calendar dates, without a UTC/DST airing-time index. Embedded local attachment/block IDs in arbitrary rich content are preserved, not rewritten. Global revisions lack retention controls; admin lists are bounded; no destructive global-purge UI exists.

The dedicated ACF browser workflow is covered. A full accessibility review, broad Gutenberg browser testing, ACF Pro/third-party fields, actual Yoast/Rank Math combinations, persistent/external caches, sharded databases, multiple-network administration and other PHP/WordPress versions remain unverified. The 205-record exercise demonstrates batching, not tens-of-thousands/high-traffic capacity. This run used Linux containers/Chromium rather than physical Mac hardware.

Use staging review, backups, production queue supervision and workload-specific testing before rollout. See [the roadmap](multisite-shared-content/docs/changelog.md) and [Mac quickstart](docs/shared-content-quickstart.md).
