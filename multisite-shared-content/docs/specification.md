# Functional specification

This describes the shipped behavior and practical limits. It is not a promise of unimplemented features.

| Area | Implemented behavior |
|---|---|
| Global identity | UUID independent of local post IDs; unique internal numeric key |
| Global create/edit | Authorized participating-site users; dedicated global form, PHP service, REST API |
| Global revisions | Immutable JSON snapshots with actor, origin site, time; last 100 visible; restore produces a new revision |
| Native posts | One per participating record/site, distinct local IDs, ordinary permalinks, templates, Gutenberg, WordPress queries |
| Native editor semantics | First full save creates global data; subsequent native post-property saves are local overrides |
| Inheritance | Global plus explicit per-field override rows; falsey values preserved; resetting deletes only the override row |
| Field registry | Native properties and text/richtext/numeric/date/time/timezone/select/list/boolean/URL/media/relationship/JSON fields |
| Validation | Type, numeric min/max, options, valid calendar/time/zone, media ownership, relationship target type; sanitization and escaping |
| Extensibility | Custom field sanitizers, schema-defined CPTs, generic relationship targets, materialization action, media fallback filter |
| Starter content | TV Show and Episode schemas include description/synopsis, schedule, imagery, networks/services, SEO, identifiers, and arbitrary metadata |
| Episodes | Parent UUID, season/episode numbering, season grouping, indexed date lookup, upcoming and next-episode queries |
| Editorial UI | Separate global/local banners, global value preview, override state/control, reset by unchecking, conflict notices, UUID columns, inheritance list filter |
| Episode UI | Bounded season-grouped episode list on a show; prefilled Add Episode; links to manage existing episodes |
| Network settings | JSON schema editor, labels/slugs, archive/noindex/default-status policy, field override locks, site enrollment, queue monitoring |
| Participation | Explicit opt-in per site/type; bounded background catch-up; disable unpublishes materializations and preserves data |
| Local publication | Draft, publish, private, unpublished as native draft, removed as suppressed local representation; reenable recreates with overrides |
| Deletion | Local delete suppresses only local projection; global trash/archive/restore limited to network administrator; no permanent purge UI |
| Media | Owner site + attachment ID references; no duplicate upload required; local replacement/empty override; missing-media fallback |
| Rich content | Sanitized HTML and block comment structure retained; no global rewriting of embedded local attachment IDs or links |
| Noindex | Default network policy for singles, archives, applicable taxonomies; core sitemap exclusions; robots hooks and HTTP header |
| SEO | Per-site SEO fields; core description/title integration; Yoast/Rank Math hooks included, external combinations unverified |
| Frontend | Trusted PHP read/query API, permissioned REST, native templates, optional dynamic block; no forced site-wide layout |
| Queue | Transactional outbox, keyset batches, generation-aware acknowledgments, advisory locks, retry/backoff, failed-job monitoring |
| Consistency | Current global+override resolver; eventually consistent native posts/meta/site indexes |
| Concurrency | Global/local expected-version CAS; native editor reserves a local revision before write; stale REST writes report 409 |
| Caching | Core post cache invalidation and version-keyed object-cached overrides |
| Security | Capabilities, current-site scope, admin nonces, WordPress REST authentication, sanitization, escaping, prepared queries, safe JSON |
| Activation | Network-only, repeatable schema migration, per-site role initialization; new sites require enrollment |
| Deactivation/uninstall | Scheduled runner stopped on current network; data retained; no implicit destructive uninstall |
| Packaging | Build script emits plugin ZIP, source ZIP, and SHA-256 manifest without development credentials |

## Policy details

A field's existence and value are separate. `false`, `0`, `""`, and `[]` are not interpreted as inheritance. Unknown fields are rejected. A schema-locked field cannot receive a new override; changing an already-used lock policy requires a migration of existing overrides rather than silently discarding editorial work.

Publication is per site and separate from global lifecycle. A global record may be active while its local post is draft/private/removed. A trashed or archived global record forces local drafts, then restoration reapplies prior publication preferences. Parent relationships retain UUIDs through trash/archive; the record remains addressable for editorial recovery. New relationships cannot target inactive or wrong-type records.

A global save confirms only the authoritative revision and queued work. Native rendering may show the prior revision until the worker runs. The dynamic block uses current resolved fields but requires an actually published local post. Field values read through PHP can be fresher than a native `WP_Query` meta condition during propagation.

## Deliberate limits

Schema configuration is an advanced JSON interface. Media has a native current-site picker plus explicit owner references for cross-site assets. The ACF adapter supports documented scalar and single-relationship fields; Pro/nested/media adapters are excluded. No nested field-specific editor, per-CPT capability mapping, instant UTC schedule index, taxonomy mirroring, or permanent global purge is supplied. Arbitrary Gutenberg content is preserved but embedded local attachment IDs/shortcodes/block references may require site-aware blocks to work on another site. See the roadmap for further work and the verification report for untested configurations.
