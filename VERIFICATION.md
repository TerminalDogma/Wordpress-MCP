# Shared Content 2.6.0 verification

## Training catalog, bulk publication and reviewed release posts

The actual 500-title import completed in the disposable network: 499 new drafts and one existing title reused. The frozen Wikidata CC0 dataset has 250 distinct Movie QIDs and 250 distinct TV Show QIDs, with sourced metadata and an explicit Wikipedia-coverage methodology. Source facts are bundled; installation does not call external APIs.

All 19 focused checks passed, including 500 imported memberships, duplicate prevention, preserved editorial fields, draft defaults, local/global publication, changed-record rejection, cross-user and role permissions, local removals, migration of generated Pages into pending Posts, scheduled native publication and a complete 250-Movie global publication in ten-record batches. A separate cross-site check verified that first-time imports immediately create draft projections even when the schema's normal default is publish.

Chromium verified the 250-title native filters, global bulk actions for shared titles, local publication for regular Posts, hidden pending guides, publication from the normal Posts list, post-owned titles, and resumable training imports. Published release Posts also appear in the homepage release-guide section. The updated guide browser workflow passed with regular Post templates and heading levels.

Regression checks passed: 71 source/import, 42 Episode, 35 entity/calendar, 18 ACF publication, 34 review and 57 comic checks, plus 38 release-guide checks. PHP/JavaScript syntax, workflow YAML and packaged ZIP integrity were checked. A stylesheet overwrite found by the mobile browser regression was restored before packaging.

## Upcoming release guides and streaming services

All 37 focused WordPress checks passed. Coverage includes idempotent hubs, eight local service logos, published-date filtering, private parent protection, episode inheritance, explicit empty overrides, distinct movie streaming dates, page-generation permissions and bounds, duplicate prevention, preserved editorial introductions, automatic date updates, calendar service badges/filters, past-date controls and pagination beyond sixty results.

Chromium passed the public card/list workflow, provider logos and filters, AJAX month/view/history navigation, resetting initially selected/generated defaults with and without JavaScript, service retention in calendar month/week views, mobile overflow, saved monthly guides, service profile links and the admin generator. The existing 239 movie/import, episode, entity/calendar, review and comic checks also passed. All PHP files passed syntax checks; JavaScript syntax and release ZIP integrity were verified. Screenshots of the implemented desktop/mobile layout were inspected. The reference's own screenshot host was blocked; layout patterns were extracted from its HTML/text and branding metadata.

## Comic series, issue arcs and GCD imports

All 57 WordPress/ACF comic checks passed. Coverage includes SuperHeroHype-only enrollment, an upgrade from the saved 2.4.0 schema, direct book arc assignments, independent issue numbering and arc positions, source identity/parent validation, cover sideloads and provenance, corrupt-image retention, permissions for native authors, selected-scope parent context, draft/private/protected visibility, calendar context, empty-arc filtering and pagination beyond 50 issues. Provider responses use deterministic HTTP fixtures. GCD's live API was separately verified for its direct cover URL on issue 4513.

Chromium verifies the series/arc/issue templates, downloaded artwork/footer credits, AJAX arc filtering including an empty assigned arc and reset, pagination/history, mobile overflow, Site A exclusion, ACF controls and selectable issue-batch progress.

The four-site brand, content-type navigation, IGDB import and dynamic-calendar browser workflow also passed.

Existing regressions passed: 71 source/import checks, 42 Episode administration checks, 35 entity/calendar checks and 34 review/aggregate checks. PHP syntax, JavaScript syntax and package validation also passed. See [the focused audit and suggestions](docs/comic-audit.md).


Episode calendar checks additionally cover parent titles in month/week cards, local parent-title overrides and exclusion of private Show names.

## Wikipedia game aggregates and editorial-only badges

All 33 focused Wikipedia checks, 34 editorial/OMDb checks and the review browser workflow passed. A reduced real Wikipedia reception table verifies per-platform Metacritic scores/citations and OpenCritic percent-recommended data. Focused checks cover alternate tables/prose, missing and ambiguous values, article URL normalization, revision provenance, caches, permissions, Sony filtering, no paid provider calls, source attribution and editorial-only image/card badges. The existing Movie/TV OMDb regressions remain in place. Chromium covers article/platform selection, persisted recommendation percentages, unchanged Movie forms and the absence of aggregate badges on unreviewed posters/archive cards. Provider network replies use deterministic fixtures; extraction is also exercised against the captured Wikipedia table.

## Editorial reviews and aggregate scores

All 42 focused WordPress checks and the review Chromium workflow passed. The checks cover half-point bounds, medal tiers, schema upgrades, UUID/ACF associations, site isolation, editorial precedence, protected/draft reviews, unpublishing, review toggles, explicit empty overrides, legacy OMDb values and provider preview/cache/credential/quota behavior. Chromium covers the four site identities, review layouts and verdicts, score ordering/medals on item images and archive cards, mobile overflow, native ACF fields, both shared score forms and global saving. API replies are deterministic fixtures; live commercial provider access is not claimed without configured accounts. Full CI runs after publication.

The trailer browser workflow additionally checks three compact embeds sharing one desktop row, two on tablets and one on phones, controls hidden when all trailers fit, partial final pages, stable row height and unloading players as they become hidden.

## Trailers and sidebar layout

All 37 focused trailer/sidebar checks, 24 existing image-search checks and 11 draft-credit checks passed. Chromium workflows passed. The focused WordPress checks exercise trailer schema upgrades, ACF Free fields and writes, normalization and unsafe input rejection, limits, provider result filtering, independent image/video caches, paging, permissions and cooldowns. Publication fixtures cover Movies, TV Shows and Video Games, empty sections, first-player loading, inherited/local values and a five-actor sidebar retaining the full cast. Chromium checks carousel navigation/player unloading, stable height, keyboard controls, mobile overflow, details spacing, no-JavaScript watch links, embedded search/preview/add/retry/duplicate prevention in both editors and global ACF saving. Provider responses and YouTube thumbnails are fixtures; live Serper account access and third-party video playback are not asserted. Full CI runs separately after publication.

## Credit names and profile publication

Eleven focused WordPress checks passed for draft Person/company names, duplicate company references, multiple roles, hidden private descriptions, automatic linking after publication and unlinking after unpublishing. Checks also cover inherited People credits on a site without Person pages, empty local overrides and unchanged global relationship data. The full GitHub Actions suite runs separately after publication.

## Multilingual Movie titles

The live Wikidata label response for Avengers: Doomsday (`Q113244839`) has no English-only label but returns its title with multilingual fallback. The focused fixture reproduces the blank first result before the fix. Regression checks cover title rendering, preview of multilingual-only cached data and automatic refresh of an older unnamed cache entry. Missing-title errors are separate from unsupported-type errors; film validation remains enforced.

## Movie search request pacing

The previous implementation reproduces “Please wait a few seconds before requesting more source data” on a cold twelve-result fixture: fifteen requests are needed and the local twelve-request allowance rejects the thirteenth. Version 2.1.2 completes the same search with four requests. Eighteen WordPress checks cover batched subclass levels, negative/full/claims cache handling, permission checks, short automatic waits, bounded nested waits, invalid future-window recovery and preserved upstream Retry-After behavior. Browser checks exercise search, preview, future-window recovery and cached results during provider cooldowns. Provider tests use deterministic fixtures; this does not assert live searches on the user's Mac or remove real upstream quotas.

## Bespoke catalog archives

Version 2.1.1 gives each archive family its own layout and metadata without rendering excerpts. Integration checks cover scoped search, combined filters, stable sorting/undated values, local title/date/category overrides, taxonomy scope, published parent Shows, retained source text and exclusion of protected records from results/facets. Sony checks include a stale native PC projection after a policy change and the correct projected release year. Browser coverage exercises all twelve built-in type archives, custom taxonomy listings, all four identities, mobile layouts, pagination/filter resets, history, failed requests/retry, overlapping requests and no-JavaScript forms/links. Models read records/overrides in batches, keep only card fields and do not claim high-traffic or large-catalog performance certification.

## Linked entities, empty Episodes and weekly calendars

Version 2.1 adds seven entity schemas and ACF Post Object relationships without changing stored legacy field types. Integration tests cover additive upgrades with saved data/custom labels, explicit and matched-name links, deduplication, local empty overrides, private/password-protected destinations, reverse links, Sony console membership, platform launch dates, disabled-type preservation and draft-only Episode sections. Calendar checks cover adjacent-month week data, site start-of-week settings, DST, daily overflow/all entries, malformed input and anonymous REST. Chromium exercises all seven templates, sidebar links, empty/published Episode sections, daily expansion/collapse, view/filter navigation, history, retry, responsive week rendering and no-JavaScript navigation/expansion. Existing season navigation and publication workflows remain in CI.

## Catalog overview and season loading

Version 2.0.1 removes the repeated synopsis beneath catalog detail titles and retains the Overview/Biography section. When description content is empty, that section uses the stored synopsis. Excerpt data and archive cards remain available. TV Show seasons and 50-Episode pagination load through fetch without replacing the document, using the existing published-only server-rendered list. Links continue to work without JavaScript. The browser workflow checks all five detail types, excerpt-only fallback, both TV sites, numeric season selection, pagination resets, Back/Forward, source credits, failed requests/retry, rapid selections and mobile rendering.

Version 2.0.2 shows Google Images thumbnail results inside each image picker through Serper, with pagination and one-click import. The backend protects the provider key, validates result links, caches matching queries and handles provider quotas/errors. Deterministic provider fixtures verify credential handling, cache separation, authorization, result sanitization, paging, quotas and errors. Browser checks cover embedded results without popup/document navigation, thumbnail dimensions, direct result imports in both editors, gallery appends, source-page attribution, failed searches/retries and CSRF. Live Google results and account access require the user's provider key and are not exercised by fixtures.

The season update now clears the loading status on success and leaves focus/selection alone. Browser checks verify there is no completion message, no heading focus and no selected heading text after navigation.

Image downloads continue to verify public URLs and redirects, local ownership, format/size limits, permission checks and temporary-file cleanup. Original source pages can use HTTP or HTTPS; actual image downloads require HTTPS.

ACF offers save-and-publish actions for this site and, for network administrators, all eligible sites. Integration checks cover existing overrides, excluded content types/platforms, missing projections, local removals, editor permissions, stale publication tokens and rollback after an injected destination failure. Browser checks exercise both buttons and verify that field changes retain their selected global/local scope.

This is an installable release candidate for local/staging testing, with actual WordPress, ACF and browser coverage. It is not a certification of every production-readiness requirement in the original project brief.

## Four-site catalogs and calendars

Version 2.0 adds four actual network logos and locally bundled OFL fonts, fictional editorial homepages, a Video Game schema and detail template, private Twitch/IGDB configuration, selective title imports and cover previews, a per-site content matrix, Sony console/handheld filtering and effective-date release calendars. `network-games.php` exercises real WordPress storage with deterministic IGDB OAuth/API/image fixtures. It checks caching, quoted queries, refreshed tokens, throttling/backoff, malformed/transport errors, identity reuse, retained overrides, exact versus partial dates, Sony projection, site toggles, calendar publication/password filters and anonymous REST. `network-browser.py` checks all four brands and menus at desktop/mobile widths, fictional article links, category archives, calendar month/back navigation, content switches, child Episodes, game import, cover previews, structured date editing and calendar updates.

Live IGDB access requires the user's Twitch credentials and has not been verified against their account. Fixture tests verify the integration contract, not an approved commercial partnership. Earlier regression suites use broader disposable-only enrollment; the four-site browser suite then applies the requested production profiles. No production defaults are broadened for testing. The design references and presentation adaptations are documented in [DESIGN.md](DESIGN.md).

## Import review, global delivery and public navigation

Version 1.8 tests duplicate draft review, one Person credited as writer/director, explicit namesakes, source ID reuse, curated biographies, global defaults/Edit links, immediate three-site delivery with the worker stopped, retained overrides, injected site failure/retry, recovery-backed Site A reconciliation and idempotency, published-only seasons, 50-Episode pagination and footer collection. Browser workflows cover real duplicate/409 responses, Select all/partial selection, both editor save paths, per-site status, public season links at desktop/mobile widths and footer-only text/image attribution.

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
