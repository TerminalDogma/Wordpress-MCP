# Upcoming release guides (2.6.0)

Update the existing Mac checkout with Docker Desktop running:

```sh
export PATH="/Applications/Docker.app/Contents/Resources/bin:$PATH"
git pull --ff-only
sh bin/shared-content.sh setup
```

Setup creates **Upcoming Releases** at `/upcoming-releases/` on each demo site and adds it to the catalog navigation. The guide follows each site's enabled content types and platform policy, including Sony-only releases on Site D and Comic Issues on Site B. The original Release Calendar remains available.

## Generate monthly posts for review

1. Open **Shared Content → Release Posts**.
2. Choose a first month and **1–12 months**.
3. Optionally choose a content type and/or a published streaming service.
4. Click **Generate release posts for review**.

These are ordinary WordPress Posts created as Pending Review. They must be reviewed and published before readers can see them. The rolling Upcoming Releases hub remains a Page. Editors can add an introduction around the shortcode. The listings remain live: changing a published date, title, artwork, service assignment, local override or publication state updates the page's contents. No scheduled scraper, paid API or recurring regeneration is required.

Repeating the same month/type/service selection reuses the existing page and preserves its copy and publication state. Trashed guides stay trashed; restore them in Posts → Trash if needed. Generated posts can be viewed/edited from the generator table and the ordinary Posts list.

For a manually created page, insert `[msc_upcoming_releases]`. Optional attributes include `month="2030-05"`, `type="msc_show"`, `service="SERVICE-UUID"` and `view="list"`. Omit the month for a rolling current-month guide.

## Reader experience

Month tabs, title/show search, enabled content-type filters and service-logo selectors update without a full page refresh. Ordinary links/forms also work with JavaScript disabled. Cards show artwork, release labels, service badges and links to published catalog entries. Episodes show their parent TV Show and season/episode identifiers. There are no synopsis excerpts. A compact list view and sixty-entry pagination keep busy months manageable.

The current month hides earlier dates by default; **Include earlier dates** restores them. Explicitly selected past months remain browsable. Empty results offer a filter reset. **Calendar grid** carries the selected service and type into the existing month/week calendar, which also shows small service logos.

## Streaming services and logos

One-time network setup adds Netflix, Prime Video, Disney+, Hulu, Apple TV, HBO Max, Paramount+ and Crunchyroll as shared Streaming Service records, with local media-library logos. New records are published on participating sites. Existing matching records keep their publication choices and existing logos; an existing draft must be published before it appears in public filters. Ambiguous names are retained for editorial review rather than merged.

Edit these under **Shared Content → Streaming Services**. The ordinary Logo field supports replacement artwork. Add any further service using the same custom post type; published services automatically join the directory. Profile pages link back to their upcoming releases and to official websites. Known bundled brand links are available without a runtime provider lookup. Logo credits appear in the source footer. Asset sources are recorded in `multisite-shared-content/assets/services/README.md`.

Assign services through **Linked Streaming Services** on a Movie, TV Show or Episode. Existing unambiguous legacy service names and known service aliases are also recognized. Episodes inherit the public parent show's assignments when they have none of their own; an explicit local empty relationship override suppresses inheritance. Private, password-protected, disabled or unpublished services and parents cannot leak into public filters or inherited badges.

Movie service badges/filtering apply to **Streaming release date**, keeping theatrical, digital purchase/rental and disc dates distinct. Service assignments are editorial scheduling information, not a regional subscription-availability feed. JustWatch availability integration is separate.

## Data sources and dates

The guide reads the existing date index and effective published local projections. It does not scrape TV Insider's listings or invent new catalog entries. Import or enter the relevant dates on Movies, TV Shows, Episodes, Video Games, platform records and Comic Issues. Partial dates remain on title pages until an exact day exists. Game releases retain platform/region metadata and each site's catalog restrictions.

The TV Insider reference supplied layout patterns; site branding, cards, filters and the implementation are independent. See `DESIGN.md` for reference observations and implementation choices.
