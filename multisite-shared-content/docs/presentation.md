# Screening Room: sites, templates, people, categories and images

Version 1.3 adds a reusable **Person** post type (the **People** menu), linked credits, native WordPress taxonomies and image previews in both shared-field editors. The optional **MSC Screening Room** theme supplies complete Movie, TV Show, Episode and Person templates, collection archives, search and a catalog homepage.

## Update your Mac installation

Run these commands from the existing `Wordpress-MCP` folder with Docker Desktop running:

```sh
git pull --ff-only
sh bin/shared-content.sh setup
sh bin/shared-content.sh demo
```

If `docker` is still missing from your shell, run this first:

```sh
export PATH="/Applications/Docker.app/Contents/Resources/bin:$PATH"
```

`setup` recreates containers to mount the new theme, adds Person participation on the three named demo sites, and rematerializes their existing shared records. The database volumes and credentials are retained. `demo` adds fictional people, categories and original geometric artwork. Existing demo field values (including intentionally empty values), local overrides and dates are retained; only missing demo fields are filled. People created by the demo are published on all three sites.

With the default port, open:

| Site | URL | Visual identity |
| --- | --- | --- |
| A | http://localhost:8080/site-a/ | **Observatory** — forest green, lime, editorial serif |
| B | http://localhost:8080/site-b/ | **The Matinee** — warm cream, red, print-inspired typography |
| C | http://localhost:8080/site-c/ | **After Hours** — purple, lavender, rounded cards |

Use your configured port if it differs. The site ribbon links between sites; colored badges also identify the current site in the WordPress admin bar. The sample Movie, Show and Episode have detail links from the homepage. Click a cast member to open their Person page and published credits.

To reapply only the demo themes and identities:

```sh
sh bin/shared-content.sh style
```

These commands activate the supplied theme on `/site-a/`, `/site-b/`, and `/site-c/`. The network main site and other sites retain their themes. Plugin activation by itself does not switch themes. Change an individual site's theme through **My Sites → [site] → Dashboard → Appearance → Themes**; shared records and overrides remain independent of the theme.

## People and linked credits

1. On an enrolled site, open **Shared Content → People → Create global record**. Enter a name, biography, headshot, professions, country, birth date or website as appropriate.
2. Save, then publish the local Person on each site that should display their profile. The global Person is shared; publication and overrides are per site.
3. Open a Movie, TV Show or Episode, then **Edit supported fields with ACF**. Select existing People under **Actors**, **Directors**, **Screenwriters** or **Producers**. TV Shows also have **Creators**. The ACF selectors support search, multiple selections and ordering.
4. Save globally to share the credits, or use local override checkboxes to customize one site. Reset an override to inherit the latest global list. An empty overridden list intentionally removes that credit group on that site.

Links store immutable global UUIDs. The ACF adapter translates these to the correct local post IDs. People must be enrolled and synchronized on the editing site for ACF selection. The full Shared Content form can also link global People by name, including those without a local projection. Its initial list contains 500 active records plus current selections; ACF provides search for larger catalogs. If an existing related Person is unavailable locally, ACF rejects a submission that could silently drop the link; use the full form to resolve it.

The original Movie `director`, `writers` and `cast` text fields are retained. They are never guessed or automatically converted into people. Link real People using the new fields; the theme prefers linked directors and writers and retains cast notes. The Person template derives a filmography from that site's published, effective credits; draft/private/removed People and credits do not appear publicly. Filmographies show up to 100 entries; TV season guides show up to 500 episodes.

For a non-demo site, enable `msc_person` together with the other required types in **Network Admin → Shared Content → Site participation**. Schema upgrades never automatically enroll additional sites.

## Reusable categories

The following fields now use category selectors instead of plain text/JSON controls:

| Fields | Native taxonomy |
| --- | --- |
| Genres | `msc_genre` |
| TV network | `msc_network` |
| Streaming service | `msc_platform` |
| Country, language | `msc_country`, `msc_language` |
| TV status, Movie release status | `msc_show_status`, `msc_movie_status` |
| Movie content rating | `msc_rating` |
| Person professions | `msc_profession` |

A network administrator can add choices under **Network Admin → Shared Content → Categories**. Starter choices are examples, not an exhaustive country, language or service directory. Existing saved values remain selectable. Genres and professions allow multiple choices; other existing fields keep their single-value format.

Saving a record projects its effective categories into native terms on each participating site, so taxonomy archives and `tax_query` work normally. A local override also controls that site's terms. Category names are portable across sites; site-specific term IDs are never shared. The native taxonomy editing controls and term REST writes are intentionally hidden: use the explicit global/local field forms so inheritance and conflict checks apply. Programmatic writes should use the MSC Store API; direct `wp_set_object_terms()` calls modify only a projection and are replaced on the next synchronization.

Custom schema labels, field types, choices and locks survive the additive upgrade. Existing category option restrictions still apply. The new `taxonomy` schema property may be set to `false` to opt a customized field out of category selectors and projection. After changing schemas, re-save site participation to rebuild projections. Category archives retain the noindex policy of their attached shared types.

## Images and galleries

Both **ACF Shared Fields** and **All fields and publishing** show thumbnail cards for featured images, posters, episode images, Person headshots and promotional image galleries. Use **Choose image or media** / **Choose gallery images** to select or upload an asset from the current site's WordPress media library. **Remove** clears the field; in local mode it checks the override box so the removal applies only here. Uncheck Override and save to restore the shared image.

Inherited images show their owner-site number and can be previewed even when stored on another site. The underlying `{site_id, attachment_id}` references remain intact and hidden from the ordinary editing interface. Non-image attachments show a file link; unavailable assets show an unavailable state. Media is not copied between sites. Do not delete the owner attachment while other sites still use it.

## Templates and customization

The theme lives in `wp-content/themes/msc-screening-room/`:

- `single-msc_movie.php` → `parts/detail-movie.php`: poster, dates/runtime/rating, synopsis, release facts, linked cast/crew and gallery.
- `single-msc_show.php` → `parts/detail-show.php`: series information, season/episode guide, linked cast/creators and gallery.
- `single-msc_episode.php` → `parts/detail-episode.php`: landscape image, episode numbering, calendar date/time/time zone, parent Show link and credits.
- `single-msc_person.php` → `parts/detail-person.php`: headshot, biography, professions and filmography.
- `index.php` / `archive.php`: catalog homepage, archives, category results, search and pagination.
- `style.css`: three palettes, responsive layouts, keyboard focus and reduced-motion styling.

Create a child theme for your changes; parent theme updates replace tracked files. Reuse `MSC\Presentation::record()`, `image()`, `credits()` and `filmography()` in your own templates. `record()` enforces the current site's publication and password protection rules. General `msc_get_record()` / `msc_related_list()` helpers are trusted PHP APIs and do not themselves filter for public display.

A separately installable `msc-screening-room-1.4.0.zip` is included in the CI release artifacts and generated by `python3 tools/package-msc.py`. On an existing non-Docker network, upload and network-enable the theme, then activate it per site. The plugin ZIP remains independently installable. No ACF Pro fields are required.
