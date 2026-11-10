# Comic Books, Issues and Story Arcs (2.4.0)

Update the existing checkout, with Docker Desktop running:

```sh
git pull --ff-only
sh bin/shared-content.sh setup
```

The additive upgrade enables **Comic Books**, **Comic Issues**, and **Story Arcs** on SuperHeroHype (Site B). The enrollment is one-time and preserves later Comic content-type choices, saved fields, publication states and overrides. Network Admin → Shared Content → Sites and Content Types continues to control participation.

## Content model

Create one **Comic Book** per series/run or volume. Its overall page shows the cover, overview, publisher, run years and published issues. The backend list includes run/volume and publisher columns to distinguish series with identical names.

An **Issue** links to its parent Comic Book. Its published issue number is text, allowing identifiers such as `0`, `1/2`, `1.AU` and annuals. Ordinary numeric issues sort numerically. Use **Optional numeric sort order** for fractional identifiers, variants or other special placement.

An Issue can also link to a **Story Arc**, with a separate **Issue position in story arc**. For example, a book's issue #404 can be part 1 of a four-issue arc. Set **Total issues in arc** on the Arc to show “Part 1 of 4.” Positions start at 1 and remain independent of the book's published issue number. An arc can contain issues from multiple Comic Books, supporting crossovers.

Arc pages show their published issues in reading order. Issues without a reading-order position follow numbered parts. Series pages can filter their issues by published arc. Lists paginate in groups of 50, hide inaccessible/draft issues, omit excerpts and use AJAX for filters and pagination, with ordinary links as a fallback. Empty public series hide their issue section.

## Create and manage content

1. Open **Shared Content → Comic Books** and create or import the series.
2. Choose **Add issue** from its shared/ACF editor or **Comic Books → Issues by Comic Book**. The parent is preselected.
3. Set the published issue number, cover, credits and on-sale date. Choose an existing Story Arc and its position when applicable.
4. Create Story Arcs under **Comic Books → Story Arcs**. Use their **Manage issues** link for a cross-series list.
5. Save supported fields through ACF, including its existing site/global publish buttons. Global editing, local overrides, reset and synchronization work as with other shared types. New imports follow the configured publication default, initially draft.

**Bulk edit / publish issues** opens the ordinary WordPress list scoped to that Comic Book or Story Arc. Standard checkboxes, bulk editing and publication controls remain available. Native issue columns identify the parent book, published issue number and arc position.

Public relationships link only to accessible published destinations. The comic's issue number and the arc's part number appear separately. Arc pages and series issue lists include only this site's effective, published entries.

## Search and import from the Grand Comics Database

The [Grand Comics Database](https://www.comics.org/) provides the [public API](https://www.comics.org/api/) used here. No API key, subscription or paid intermediary is required.

- In **Shared Content → Import Content**, choose **Comic Books**, search a title, and choose the correct run using its year/language. Preview and select fields before importing.
- Open that Comic Book and choose **Import issues from GCD**. Select individual issues or all issues on the current page, then **Import selected issues**. The existing progress screen supports pause/resume and processes one issue per request.
- A batch selects up to 50 issues. Next/Previous pages browse the rest of the source series. Already imported identities are skipped unless **Refresh existing issues** is checked.
- Individual issue search accepts `Batman #404`. Import or connect the matching parent series first. The importer verifies source parent IDs, rather than matching names.
- Reimports reuse GCD identities and protect stale changes. Existing local overrides remain in effect. Fields absent from the source, including editorial arc assignments, are retained.

Imported data includes available publisher/run facts, issue numbers, credits, page count, cover dates, exact on-sale dates and indexed story synopses. Availability depends on the source's indexing. **Arc membership and arc positions are editorial fields** because the current GCD API does not reliably expose them. Create/link arcs and enter reading order in the editor; the importer does not guess them.

The importer keeps **cover date** separate from **on-sale date**. A month-only date or a placeholder such as `1987-01-00` does not become a fabricated release date. Only a verified complete on-sale date supplies a calendar entry.

### Licence and attribution

GCD states that its database content, except where noted otherwise, uses [CC BY-SA 4.0](https://creativecommons.org/licenses/by-sa/4.0/), which permits commercial reuse with attribution and ShareAlike conditions. Imported fields retain source/provenance data; the theme collects credits in the footer. The [GCD homepage](https://www.comics.org/) separately reserves cover-image rights to their copyright holders. Its data licence does not grant cover-image rights, so automatic imports leave cover images unchanged. Upload/select a cover or use the existing Google Images/image-URL controls.

The implementation uses the public series-name, series-detail, issue-detail and publisher-detail endpoints. It validates fixed provider hosts and source identity, caches requests, retains source cooldowns and never fetches source APIs on public page views.

## Release calendar

Published Comic Issues appear in SuperHeroHype's existing calendar using their effective on-sale date. Cards include the published parent Comic Book name. Local date/title overrides apply. Comic series/arc records do not create duplicate issue-release events, and a cover month alone does not create an event.

## Verification

`tests/shared-content/comics.php` exercises actual WordPress/ACF registration, SuperHeroHype-only initial enrollment, source contracts through HTTP fixtures, duplicate identities, parent validation, arc reading order across series, local overrides, public visibility, calendar context and pagination beyond 50 issues. Run only on an explicitly disposable local network with `MSC_DISPOSABLE_TESTS=1`. The public GCD search endpoint was also checked separately against `Batman` (1940 series).
