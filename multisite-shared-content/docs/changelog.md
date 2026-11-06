# Changelog and release boundaries

## 1.7.0

Episodes now opens a Show-first browser by default, with one row per Show and Episode/season counts. Open a Show's season batches, then manage only the chosen season in the native editor list. Show search and pagination support larger libraries; All Episodes remains available. Counts follow site-specific relationships, drafts, missing season values and season zero. No record migration or title changes are required.

## 1.6.0

Adds Show-aware Episode administration: parent/season/episode/air-date columns, numeric default ordering, Show and season filters, contextual row titles, and direct Show-to-Episode navigation. Native lists respect local parent and numbering overrides; the paginated Shared Content catalog uses global relationships and supports Show-title search. Scoped Add Episode links preselect the Show, including the native Add New action in a filtered list. Native, ACF and full-field editors expose parent/sibling navigation. Stored titles and canonical UUID relationships are preserved. Includes real WordPress query/permission tests and a browser workflow. See [the guide](episode-administration.md).

## 1.5.2

TV Show episode lists display episode numbers, linked titles and dates without images or synopses. Episode titles use 18px text on desktop and 16px text on mobile. Source credits apply only to the fields displayed in each row. Episode detail pages and catalog cards retain their images and descriptions.

## 1.5.1

Replaces promotional headings, taglines, decorative content labels and empty-state copy with descriptive catalog labels. The three demo sites are named Site A, Site B and Site C and retain their distinct colors and layouts. Homepages explain shared records and per-site field overrides. Existing record titles, content and overrides are retained.

## 1.5.0

Adds keyless, source-aware imports using TVmaze, Wikidata, Wikipedia and individually licensed Commons images. People are identified by provider IDs and can be enriched with biographies, birth dates and headshots. Replaces Episode title search with verified Show/season lists, individual review and resumable sequential batches. Retains original airing dates, specials, missing values, per-site overrides and concurrent-edit protection. Attribution follows field values and actual media across the bundled theme. Legacy OMDb screens are disabled by default; old data is retained without relicensing. Adds provider-contract and browser coverage. See [the guide](import-content.md).

## 1.4.0

Adds server-side OMDb title/year/type search, paginated results, user/site-bound previews and selective global imports for Movies, TV Shows and Episodes. IMDb IDs prevent duplicate imports; stale previews reject updates. Existing local overrides, unrelated IDs and metadata remain intact. Includes reviewed Person links, safe optional poster downloads, caching/rate limits, credential-safe error handling, and network/server-managed key configuration. Episodes gain an External IDs JSON field through a narrow additive upgrade. Database transactions now use savepoints for atomic nested Person/title writes. Provider-contract and browser tests use disposable fixtures; live OMDb is blocked by the development cloud's network policy. See [the guide](omdb.md).

## 1.3.0

Adds Person records and ordered actor/director/writer/producer/creator relationships, category-backed native taxonomies with network-wide choices, and thumbnail media controls in both editors (including ACF Free). The optional Screening Room theme supplies distinct green, cream/red and purple demo sites, responsive Movie/TV Show/Episode/Person templates, archives, search, season guides and published filmographies. The additive upgrade preserves existing text credits, custom schemas and overrides. The demo adds original locally generated artwork and linked fictional People without replacing saved values. Includes a separate theme ZIP and integration/browser coverage. See [the guide](presentation.md).

## 1.2.0

Adds a separate Movies type with movie-specific release dates, runtime, credits, distribution and rating fields. TV Shows retain series dates and season/episode fields; Episodes remain linked to Shows. The additive versioned upgrade preserves custom schemas, native-property aliases, records and local overrides. ACF headings identify each type. The existing demo gains a Movie without replacing TV edits. Integration/browser tests cover field isolation, upgrades and Movie inheritance.

## 1.1.0

Adds real ACF Free controls for supported custom fields, explicit global/local atomic forms, falsey overrides and reset, date and Post Object conversion, local `get_field`/`update_field` integration, and permission/nonce/revision protection. Includes Mac setup/demo, a local queue worker, integrated CI and clean packaging. Upgrades preserve deliberately removed role capabilities.

## 1.0.0

Initial functional implementation: UUID global records, native per-site posts, explicit overrides and reset, revisions and optimistic concurrency, extensible schemas and fields, TV Shows/Episodes, indexed relationships/calendar-date queries, transactional durable queue, bounded participation catch-up, retry monitoring, separate global/local admin screens, PHP/REST APIs, media references, noindex policy, dynamic Gutenberg block, integration suite, template examples, and release packaging.

## Known limitations

- Integration validation is on WordPress 7.1.4/PHP 8.3/MariaDB 11.4. Other supported versions, alternative databases, persistent caches, multisite multi-network administration, and third-party SEO plugins need staging validation.
- The three-site batch exercise is functional coverage, not a production-scale load test. A 20-second worker budget does not bound an individual slow third-party callback.
- Schema editing uses JSON. Media has a native current-site picker and explicit cross-site owner/attachment references. There is no visual field builder.
- There are no per-CPT granular capability names or schema-controlled role assignments. Publish-only users use the native editor or REST API; the field-management screen requires local override authority.
- Native Gutenberg conflict middleware is included; its browser interaction has not received automated browser coverage. Real REST requests are covered.
- ACF support is limited to the documented Free scalar, single/multiple-relationship and owner-aware media types. Repeaters/flexible content, arbitrary third-party field widgets and nested validation are not implemented.
- Dates/times/zones are stored separately. Upcoming queries use calendar dates, not UTC instants or daylight-saving-aware airing schedules.
- Relationship lists are bounded (500 in the grouped panel). There is no drag-and-drop season organizer or infinite scrolling.
- Embedded rich-content local attachment IDs, reusable blocks, shortcodes, and site-specific URLs are preserved, not rewritten or copied. Use site-aware integration blocks for those assets.
- Global revisions are retained indefinitely; only the latest 100 are listed. There is no configurable retention/pruning UI or permanent global purge.
- Field-type conversions, removed populated fields, and changing existing override lock policies require explicit data migrations. Schema versioning currently covers database layout, not automated semantic field conversion.
- WordPress/object caches are handled; external page/CDN caches need site-specific invalidation integrations.
- Hooks for Yoast/Rank Math are included but those plugins and third-party taxonomy sitemaps are not certified by this test run.

## Roadmap (not shipped)

1. Browser-tested field builder, cross-site media-library browsing, and richer episode management.
2. Production load/concurrent-writer benchmarks, queue telemetry, retention controls, and operational dashboards.
3. Per-type capability/role configuration and independent publish-only workflows.
4. Extend the validated ACF adapter to nested structures and advanced fields.
5. UTC airing index with time-zone/DST policy and richer calendar queries.
6. Semantic schema migration tooling and safe opt-in permanent purge with backups.
7. Third-party SEO/cache compatibility matrix, embedded-media adapters, and richer REST query validation.
8. Multiple database-engine/version and persistent-cache CI coverage.
