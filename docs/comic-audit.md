# Comic implementation audit — 2.4.1

Scope: the 2.4.0 comic implementation and its shared importer, ACF, schema, materialization, public-template and calendar integrations. This is a focused code review and regression pass, not a complete security certification of WordPress or every plugin.

## Confirmed findings and fixes

| Priority | Finding | Resolution |
| --- | --- | --- |
| High | The grouped issue browser required only native `edit_posts`, allowing ordinary authors to enumerate shared draft/private records. | Both the page and admin issue query now require shared-content editing capabilities. |
| Medium | Published numbers such as `#10` and `#2` sorted lexically. | Numeric sorting strips a leading `#`; explicit sort-order values still take precedence. |
| Medium | Global issue editors could show a locally overridden parent in their context panel. | Parent identity and title follow the selected global/local editing scope. |
| Medium | Disabling Comic Books also removed the grouped issue page, even when Story Arcs and Issues stayed enabled. | The grouped page registers under Issues when necessary and lists enabled owner types. |
| Medium | The image-attribution selector omitted comic posters. | Comic covers use the same footer-credit collection as other shared images. |
| Low | A record with cover artists but no overview or other creators hid its credits section. | Any supported creator credit can show the section. |
| Low | The full CI browser run still expected navigation without Reviews. | Expected links include Reviews and Site B’s comic navigation. |

## Requested additions checked during the audit

GCD covers use the public issue API’s `cover` field, validated against GCD’s image hosts and scan paths. Covers are downloaded into WordPress, carry separate artwork provenance, and can be selected in individual previews or issue batches. Missing/corrupt images preserve existing artwork. Series previews try the first three issues for a representative cover. No public page requests the provider API.

Comic Books now have a multi-select Story Arc relationship. Each issue retains its separate Story Arc and arc position. Published assignments appear on the book page; filtering to an assigned arc with no published issues keeps the browser and reset controls available. The additive upgrade is tested against a saved 2.4.0 schema.

## Suggested follow-up

- Warn editors when two issues claim the same position in one arc; retain an override for intentional alternate editions.
- Add multiple arc memberships per issue only if overlapping events become necessary. The current model deliberately has one primary arc per issue and multiple arcs per book.
- Profile database queries with a realistic catalog before optimizing: issue pages are bounded to 50 entries, but resolving each entry and its relationships still causes repeated lookups.

## Verification limits

WordPress/ACF storage, permissions, schema upgrade, image sideloads, failed-image retention, source identities, public visibility, ordering and calendars are exercised using deterministic HTTP fixtures. Chromium checks cover the public/admin workflow and AJAX navigation. GCD’s live issue API was separately checked against issue 4513 and returned the same direct scan format used by the importer. End-to-end provider downloads inside WordPress remain fixture-backed in this restricted environment.
