# ACF shared fields

ACF Free is optional. Without it, the plugin's complete Shared Content editor and PHP/REST APIs still work. With ACF 6.8.10 active, MSC registers real ACF field groups and controls for supported custom fields from the network schema.

## Editor workflow

1. On Site A, open **Shared Content → TV Shows** and create a global record. Its native title/body, images, publication and advanced fields use the existing editor.
2. Open the record and choose **Edit supported fields with ACF**.
3. In **GLOBAL EDITING**, saving updates the shared values for inheriting sites. The form shows global values, not a local site's overrides.
4. Switch to **LOCAL EDITING**. Check **Override on this site** for each field you want to change. A checked field can be intentionally empty, zero, false, or an empty list.
5. Uncheck a field's Override control and save to resume inheritance from the newest global value.

The controls are on a dedicated ACF form inside WordPress admin. They are not silently attached to the ordinary Gutenberg save operation. Native title/body saves retain their documented local-override behavior. The ACF form does not create new records or change publication status; use **All fields and publishing** for those actions.

Global and local forms carry separate revision tokens. Stale forms return HTTP 409 and save no changes; reload and review the latest record before retrying. The action verifies its nonce, current-site post editing permission, field/type ownership, participation and the appropriate MSC capability. A local editor cannot turn a local form into a global save by changing its submitted scope.

## Supported types

| MSC field | ACF control | Storage behavior |
|---|---|---|
| text | Text | Sanitized text |
| richtext | WYSIWYG | Sanitized HTML; preserved block comments |
| integer / number | Number | Validated numeric value, min/max |
| boolean | True/False | Explicit boolean including false |
| date | Date Picker | ACF raw Ymd converts to canonical YYYY-MM-DD |
| time | Time Picker | HH:MM[:SS] |
| timezone | Select | Valid IANA time-zone identifier |
| url | URL | Valid public HTTP(S) URL |
| select / multiselect | Select / Checkbox | Schema choices; empty list preserved |
| relationship | Single Post Object | Local post ID translates to a permanent global UUID |

Only non-native custom fields are included in these groups. Native title/body/excerpt/slug, owner-scoped media, media lists, arbitrary JSON, ACF Pro repeaters, flexible content, galleries, clones and third-party field types are not bridged. Media and JSON remain available through the full MSC editor; unsupported fields are excluded rather than copied with incorrect site IDs. A related record needs a local representation for the Post Object selector. Use the full UUID relationship editor when the target type is not enrolled on that site.

The network schema remains the source of field definitions. These generated PHP field groups are not independent, editable ACF schemas. Other ordinary ACF field groups continue to work normally. Do not create a second ACF field using a reserved `msc_` name on a managed post.

## PHP integration

For a native shared post on the current site:

```php
$date = get_field('msc_release_date', $post_id); // Effective ISO date.
$raw = get_field('msc_release_date', $post_id, false); // ACF raw Ymd.
$show_id = get_field('msc_parent_show', $post_id); // Current site's local post ID.
```

`update_field()` for a supported managed field creates an explicitly local override, checks current-user local permissions, and never changes the global record implicitly:

```php
update_field('msc_release_date', '20990203', $post_id);
```

For an atomic global update or reset, use the documented versioned MSC Store API. Batch editor submissions are atomic, but independent programmatic `update_field()` calls are separate local mutations; they do not supply an editor's stale-version token. Use the versioned API for competing external writers. Use `msc_get_record()` for a complete record; ACF `get_fields()` enumeration is not an adapter API.

The adapter short-circuits ACF value loads and updates. MSC metadata stays a materialization, not a second authority. No synchronization is driven by a naive `acf/save_post` mirror, so projections cannot create recursive global updates. Cross-site relationships never rely on identical numeric post IDs.

## Validation

Tests exercise real ACF field registration, raw/formatted dates, name/key resolution, local API writes, field locks, empty overrides, reset, global propagation with local preservation, relationship conversion, unrelated ACF behavior, role-upgrade policy, and authenticated admin-form HTTP saves with CSRF and stale-version rejection. Chromium also exercises calendar selection, global save, local preservation and reset. A full accessibility review and ACF Pro compatibility remain unverified.

Official references: [acf_form](https://www.advancedcustomfields.com/resources/acf_form/), [save timing](https://www.advancedcustomfields.com/resources/acf-save_post/) and [save validation](https://www.advancedcustomfields.com/resources/acf-validate_save_post/). Load/update short-circuit behavior was checked against the pinned ACF 6.8.10 source.
