# Changelog and release boundaries

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
- ACF support is limited to the documented Free scalar and single-relationship types. Repeaters/flexible content, media adapters, arbitrary third-party field widgets and nested validation are not implemented.
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
4. Extend the validated scalar/single-relationship ACF adapter to owner-aware media and nested structures.
5. UTC airing index with time-zone/DST policy and richer calendar queries.
6. Semantic schema migration tooling and safe opt-in permanent purge with backups.
7. Third-party SEO/cache compatibility matrix, embedded-media adapters, and richer REST query validation.
8. Multiple database-engine/version and persistent-cache CI coverage.
