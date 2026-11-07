# Screening Room: sites, templates, people, categories and images

Version 2.0 supplies four branded editorial homepages with actual network logos and fictional articles, plus Movie, TV Show, Episode, Person and Video Game templates. See [the Mac update guide](../../docs/update-2.0.md) for complete setup, private IGDB configuration and release-calendar instructions.

```sh
export PATH="/Applications/Docker.app/Contents/Resources/bin:$PATH"
git pull --ff-only
sh bin/shared-content.sh setup
```

Setup adds Site D, applies the profiles once and seeds missing fictional examples without replacing saved records or subsequent content-type choices. The site ribbon switches between the four sites, and the admin bar identifies the current brand.

| Site | Brand | Shared catalog |
| --- | --- | --- |
| A | ComingSoon.net | Movies, TV Shows, People; child Episodes |
| B | SuperHeroHype | Movies, TV Shows; child Episodes |
| C | GameRevolution | Video Games, all platforms |
| D | PlayStation LifeStyle | Video Games, Sony consoles and handhelds |

Open `/site-a/`, `/site-b/`, `/site-c/` or `/site-d/` on your configured local domain and port. Each site links to its enabled catalogs and Release Calendar. The matrix under **Network Admin → Shared Content → Sites and Content Types** controls participation. Shared records and overrides remain stored when a site disables a type.

`sh bin/shared-content.sh style` reapplies the supplied theme and identities only to these four named subsites. Other sites retain their themes. `sh bin/shared-content.sh demo` adds missing fictional presentation content and retains saved edits. Sources and image credits are collected in the footer.

## People and linked credits

1. On an enrolled site, open **Shared Content → People → Create global record**. Enter a name, biography, headshot, professions, country, birth date or website as appropriate.
2. Save, then publish the local Person on each site that should display their profile. The global Person is shared; publication and overrides are per site.
3. Open a Movie, TV Show or Episode, then **Edit supported fields with ACF**. Select existing People under **Actors**, **Directors**, **Screenwriters** or **Producers**. TV Shows also have **Creators**. The ACF selectors support search, multiple selections and ordering.
4. Save globally to share the credits, or use local override checkboxes to customize one site. Reset an override to inherit the latest global list. An empty overridden list intentionally removes that credit group on that site.

Links store immutable global UUIDs. The ACF adapter translates these to the correct local post IDs. People must be enrolled and synchronized on the editing site for ACF selection. The full Shared Content form can also link global People by name, including those without a local projection. Its initial list contains 500 active records plus current selections; ACF provides search for larger catalogs. If an existing related Person is unavailable locally, ACF rejects a submission that could silently drop the link; use the full form to resolve it.

The original Movie `director`, `writers` and `cast` text fields are retained. They are never guessed or automatically converted into people. Link real People using the new fields; the theme prefers linked directors and writers and retains cast notes. The Person template derives a filmography from that site's published, effective credits; draft/private/removed People and credits do not appear publicly. Filmographies show up to 100 entries; TV season guides paginate 50 episodes within the selected season.

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

Video Game platforms, developers, publishers and modes use `msc_game_platform`, `msc_game_developer`, `msc_game_publisher` and `msc_game_mode` categories.

## Images and galleries

Both **ACF Shared Fields** and **All fields and publishing** show thumbnail cards for featured images, posters, episode images, Person headshots and promotional image galleries. Use **Choose image or media** / **Choose gallery images** to select or upload an asset from the current site's WordPress media library. **Remove** clears the field; in local mode it checks the override box so the removal applies only here. Uncheck Override and save to restore the shared image.

Inherited images show their owner-site number and can be previewed even when stored on another site. The underlying `{site_id, attachment_id}` references remain intact and hidden from the ordinary editing interface. Non-image attachments show a file link; unavailable assets show an unavailable state. Media is not copied between sites. Do not delete the owner attachment while other sites still use it.

### Google Images and image URLs

Expand **Google Images / image URL** beneath any poster, headshot, episode image, featured image or gallery in either editor. Search terms start with the record title and image kind. Select **Search images** to show Google Images thumbnails inside the editor. Each result includes dimensions and its original source page. Select **Import image** to download and preview it without leaving the editor. **More results** adds the next page. Gallery selections append images. Save the record to apply selections in its current global or local mode.

A network administrator connects search once under **Network Admin → Shared Content → Image Search**, using a private [Serper](https://serper.dev/) API key. Serper supplies Google Images results and currently offers 2,500 trial queries. Saved keys remain on the server. Alternatively set `SERPER_API_KEY` in your private `.env` and recreate the containers with setup. Searches use the account's credits; matching queries are cached for 20 minutes and uncached requests have per-user limits.

Google's [Custom Search JSON API](https://developers.google.com/custom-search/v1/overview) is closed to new customers and its current documentation gives a January 1, 2027 discontinuation date for existing customers. The picker uses Serper instead. Its public homepage documents the image result fields; the request/paging contract is also described by this [published OpenAPI specification](https://openapisearch.com/openapi/serper). Live search requires your own provider key. Automated tests use provider fixtures and do not verify your account access.

The result's source page is retained for footer attribution. Optional image credits can be entered in the same panel. **Import an image URL** remains available for a direct URL. Imports create owner-aware attachments in the current site's library. Supported files are JPEG, PNG, WebP, GIF and AVIF up to 8 MB and 20 megapixels. Failed searches or imports retain the selected media. Local imports check the field's override box; resetting it restores the shared image.

## Templates and customization

The theme lives in `wp-content/themes/msc-screening-room/`:

- `single-msc_movie.php` → `parts/detail-movie.php`: poster, dates/runtime/rating, synopsis, release facts, linked cast/crew and gallery.
- `single-msc_show.php` → `parts/detail-show.php`: series information, season/episode guide, linked cast/creators and gallery.
- `single-msc_episode.php` → `parts/detail-episode.php`: landscape image, episode numbering, calendar date/time/time zone, parent Show link and credits.
- `single-msc_person.php` → `parts/detail-person.php`: headshot, biography, professions and filmography.
- `front-page.php`: four branded fictional news layouts; `single.php`: local news/features.
- `index.php` / `archive.php`: catalog archives, category results, search and pagination.
- `single-msc_game.php` → `parts/detail-game.php`: cover, game details and regional/platform release table.
- `page.php`: regular pages, including the release calendar shortcode.
- `style.css` and `assets/network.css`: catalog layouts and four network identities, with local fonts, responsive menus, keyboard focus and reduced-motion styling. [DESIGN.md](../../DESIGN.md) documents reference sources, assets and adaptations.

Create a child theme for your changes; parent theme updates replace tracked files. Reuse `MSC\Presentation::record()`, `image()`, `credits()` and `filmography()` in your own templates. `record()` enforces the current site's publication and password protection rules. General `msc_get_record()` / `msc_related_list()` helpers are trusted PHP APIs and do not themselves filter for public display.

A separately installable `msc-screening-room-2.1.0.zip` is included in the CI release artifacts and generated by `python3 tools/package-msc.py`. On an existing non-Docker network, upload and network-enable the theme, then activate it per site. The plugin ZIP remains independently installable. No ACF Pro fields are required.

Detail pages show the overview or biography once, below the title area; an excerpt fills that section when no description exists. TV season and episode-page links update the episode list without reloading the page, retain browser history and refresh footer credits. Ordinary links remain available when JavaScript is disabled.
