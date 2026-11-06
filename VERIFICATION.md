# Shared Content 1.7.0 verification

This is an installable release candidate for local/staging testing, with actual WordPress, ACF and browser coverage. It is not a certification of every production-readiness requirement in the original project brief.

## Episode administration

Version 1.7 adds the default Show-first Episodes menu and season-batch navigation. Real WordPress tests cover parent and season aggregation, drafts, missing seasons versus zero, local overrides, Show search, 25-Show pagination and editorial permissions. Chromium checks the top-level menu, Show counts, season counts, the selected season's native list, return navigation and access to All Episodes.

Version 1.6 adds Show-scoped native and global Episode lists, filters, numeric ordering, contextual row titles and parent-prefilled creation. `tests/shared-content/episode-admin.php` exercises actual WordPress admin main queries, draft visibility, empty/missing parents, season zero, missing numbers, sorting, combined inheritance filters, native/global title preservation, pagination, local parent overrides and role restrictions. `episode-admin-browser.py` exercises the real admin lists, sorting controls, scoped creation, Show row actions, ACF navigation and cross-site overrides. These workflows run in the Shared Content GitHub Actions job.

## Commercial-source and Episode import coverage

Version 1.5 introduces source-identified Movie/Person/TV imports, Wikipedia text with separate provenance, explicit Commons image-licence allowlists, source attribution in the theme, and Show/season-based Episode imports with resumable progress. Provider fixtures run against real WordPress storage, media, transactions and ACF. They check exact Episode parents/numbering/timezones, missing values, specials, idempotency, selective refresh/local overrides, concurrent edits, namesake People, image errors and cleanup, licences, permissions, caching and shared backoff. Chromium exercises the complete sequential Episode workflow and Movie/Person enrichment with licensed media and responsive attribution. The current GitHub Actions run records results. Direct live source requests are restricted in this cloud; live searches must be verified on the Mac. See [the guide](multisite-shared-content/docs/import-content.md).

## OMDb integration coverage

Version 1.4 adds title search, preview and selective global imports with reviewed Person links, IMDb deduplication and optional owner-aware poster downloads. Tests exercise real WordPress storage, HTTP/media APIs and ACF against deterministic provider fixtures. Coverage includes filtering/pagination, cache/key separation, malformed/missing/quota errors, secure configuration, per-user request limits, selective updates, retained local overrides and IDs/metadata, Episode parent resolution, credit ordering, stale previews and nested rollback/poster cleanup. Chromium verifies the actual search/import/ACF workflow and HTTP 409. The cloud blocks outbound OMDb calls; the supplied live key was not verified. See [the OMDb guide](multisite-shared-content/docs/omdb.md).

## Presentation, Person, category and image coverage

Version 1.3 adds the optional MSC Screening Room theme with three visual identities and native Movie/TV Show/Episode/Person templates. **34 additional integration checks passed** for global Person links, per-site ACF ID conversion, local empty-list overrides/reset, native category materialization, custom vocabulary permissions, legacy credit preservation, a safe 1.2 schema upgrade, disabling all types, and owner-aware image previews/clear/reset.

The real Chromium workflow passed for **12 detail pages at desktop and mobile sizes** across all three sites, distinct palettes and admin badges, working taxonomy archives, published Person filmographies, image previews in both editors, the actual media-library selector, and empty local image overrides/reset, with no JavaScript page errors. PHP syntax, shell/JavaScript checks and ZIP integrity checks passed. Plugin and theme ZIP installation are covered in the release workflow. Original demo artwork is generated locally with PHP GD.

## TV/Movie field coverage

Version 1.2 adds separate Movie fields and generated ACF groups, plus a versioned additive upgrade. `tests/shared-content/catalog.php` checks type isolation, custom-schema/native-property preservation, stored records and local overrides, enrollment policy, Movie updates/reset/indexed dates and noindex HTTP output. The browser workflow also checks the distinct TV/Movie controls and saves a Movie director from one site for another to inherit. The Shared Content GitHub Actions workflow records current results.

## Core environment and baseline results

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

Optional browser testing uses Python Playwright 1.62.0 and Chromium, or `MSC_BROWSER_EXECUTABLE=/path/to/chromium`. After creating a fresh demo, run `MSC_DISPOSABLE_TESTS=1 python3 tests/shared-content/browser.py`. It changes the demo date and resets Site B's override. Also run `MSC_DISPOSABLE_TESTS=1 python3 tests/shared-content/presentation-browser.py` for the three identities, responsive detail templates and media controls. CI provisions a fresh network and runs both browser workflows, packaging, and the WordPress plugin/theme ZIP installers.

## Limits and remaining work

ACF support covers generated, schema-owned scalar/category controls, single/multiple Post Object relationships and owner-aware media previews through a custom ACF field. It does not import arbitrary ACF schemas, turn a native Gutenberg save into a global edit, support ACF Pro repeaters/flexible content, or promise `get_fields()` enumeration. Unsupported data remains in the full MSC editor where its type is supported. See [the adapter guide](multisite-shared-content/docs/acf.md).

The network schema editor is an advanced JSON form. Capabilities are generic rather than per-CPT configurations. Queries use calendar dates, without a UTC/DST airing-time index. Embedded local attachment/block IDs in arbitrary rich content are preserved, not rewritten. Global revisions lack retention controls; admin lists are bounded; no destructive global-purge UI exists.

The dedicated ACF browser workflow is covered. A full accessibility review, broad Gutenberg browser testing, ACF Pro/third-party fields, actual Yoast/Rank Math combinations, persistent/external caches, sharded databases, multiple-network administration and other PHP/WordPress versions remain unverified. The 205-record exercise demonstrates batching, not tens-of-thousands/high-traffic capacity. This run used Linux containers/Chromium rather than physical Mac hardware.

Use staging review, backups, production queue supervision and workload-specific testing before rollout. See [the roadmap](multisite-shared-content/docs/changelog.md) and [Mac quickstart](docs/shared-content-quickstart.md).
