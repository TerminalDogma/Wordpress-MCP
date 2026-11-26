# Network presentation design

## ComingSoon homepage preview

Firecrawl captured [Vulture’s homepage](https://www.vulture.com/) on November 25, 2026. The page text showed a mix of prominent criticism and features, a named news feed, recaps and streaming coverage. Branding data identified the Miller Text serif headline family, a white base, a red link accent and square edges. The signed screenshot returned HTTP 403, so the implementation draws on the available page structure and branding data.

Vulture’s Movies page leads with criticism and mixes story formats in a dated list. Its TV page gives interviews and show discovery their own modules. Both identify Elderkin as their display family. The same 403 affected their signed screenshots. [Movies](https://www.vulture.com/movies/) · [TV](https://www.vulture.com/tv/)

The preview uses ComingSoon’s wordmark, bundled Archivo, and Georgia headlines. The lead overlays a full-bleed still alongside a compact Latest column. The tilted Movies lettering crosses the top of the film still. Its review uses a warm `#F5E6BB` inset, and a lavender `#EEE7F4` criticism panel gives way to compact news rows. The italic TV lettering overlaps a right-aligned photo. The mint `#E2F0EA` TV section pairs a featured recap with a thumbnail list. What to Watch uses an overlapping text panel, Interviews uses a portrait-led feature, and Trailers mixes wide, portrait and landscape crops. All art is original bundled WebP. The layouts reflow at 940px and 680px.

The chronological feed groups stories by editorial format. Format filters and the full-list link work without JavaScript, and JavaScript updates the same view in place.

The preview uses a dedicated Page template at `/site-a/homepage-preview/` and links to 28 sample stories under the same path. From an existing local multisite checkout, run `sh bin/shared-content.sh comingsoon-preview` to add the stories. Re-running the installer preserves existing page content and the configured homepage.

Preview B uses red `#ED514C` for the masthead and Movies lettering, yellow `#E7ED4C` for highlights, and sky blue `#4CA5ED` for TV and navigation accents. Its stylesheet loads on `/site-a/homepage-preview-b/`. Both previews use the same layout and story pages. Run `sh bin/shared-content.sh comingsoon-preview-b` to create the comparison page and seed any missing sample stories.

Preview C uses Polygon’s cover-led story grouping, compact headline modules, and coverage organized around guides. Firecrawl’s November 25, 2026 capture showed four featured covers, a five-story Popular News strip with images, a topical review collection, a guides module, and a Latest feed farther down. The HTML requested a desktop lead crop of 845 × 560 and secondary crops of 272 × 260. These are asset sizes. Branding data reported Montserrat and magenta accents. The signed reference screenshot returned HTTP 403. [Polygon homepage](https://www.polygon.com/)

ComingSoon’s adaptation uses its own wordmark, Archivo typography and original story art. Preview C uses a compact light masthead, a caption that overlaps the large lead image, two cover cards, and a split feature with a sky-blue text panel. A five-story news strip sits directly below the lead, followed by a two-column review collection and a full-width What to Watch panel. The red, yellow, and sky-blue palette carries over from Preview B. The feature grid stacks on phones. Movies and TV have separate desks, and the Latest feed carries all 28 stories. Run `sh bin/shared-content.sh comingsoon-preview-c` to install `/site-a/homepage-preview-c/`.

Run `sh bin/shared-content.sh comingsoon-detail-previews` to add six standalone comparison URLs on Site A. The `movie-page-preview-a` through `movie-page-preview-c` pages use the published Iron Man catalog entry. The `article-preview-a` through `article-preview-c` posts use the same fictional news story and original *The Last Meridian* artwork. A uses a warm paper background, dark teal and serif headings. B pairs the same editorial typography with a split lead and red, yellow and sky-blue accents. C uses compact Archivo headings and an overlapping caption. Each preview links to the other designs and carries a noindex directive. The installer preserves existing preview edits and leaves the homepage settings in place.

### Competitor research previews

Run `sh bin/shared-content.sh comingsoon-research-previews` to install the `homepage-preview-research`, `article-preview-research`, and `movie-page-preview-research` routes on Site A. The utility links connect the three designs.

| Research pattern | Design choice |
| --- | --- |
| Strong curated leads and quick access to new stories | A single main story with three supporting stories, a short Latest News rail, a compact sticky desktop masthead, and the complete filterable feed farther down |
| Lists are easier to scan than grids of posters, and dates become stale quickly | A chronological shortlist comes from the published local movie and TV catalog. It starts today, flags same-day releases, names the parent show on episode rows, and links to the full schedule. The adjacent streaming section is a small set of editorial recommendations. |
| Distinctive formats give readers a reason to return | Box office, reviews, recaps, and interviews each get a distinct layout. The three box-office figures are labeled sample data. Topic links reflect editorial curation. |
| Clear headlines, useful related links, and an uninterrupted article body | The sample news article opens with its release details, keeps the reading column clear, and links to related reporting and upcoming dates. |
| Catalog basics should be visible and related coverage should sit higher | Iron Man puts the U.S. date, trailer, and watch options near the title. Coverage follows the overview. The film page uses the regular cast, trailer, review, and franchise components. Outside scores appear in the review section. |

### Studio previews

Run `sh bin/shared-content.sh comingsoon-studio-previews` on Site A. It installs standalone homepage, Iron Man, and article routes for `orbit`, `cut`, and `tower`. The previous Signal, Fan Zine, and Disc Menu previews move to Draft. The installer checks each old entry’s preview metadata before retiring it.

| Preview | Interaction |
| --- | --- |
| Orbit | Choose an orbital waypoint to change the artwork and open its story or film section. The links also support arrow keys. |
| Cut Room | The editorial homepage puts a lead and news rail above separate Movies, TV & Recaps, What to Watch, and Interviews & Features desks. The article uses a continuous reading column. Both filmstrips link to visible sections. The Iron Man guide uses the film-frame selector. |
| The Tower | Select a floor to open its content. An elevator indicator follows the active floor. |

The three concepts use original generated film art and fictional articles. The movie previews read the published Iron Man record for the date, overview, trailers, cast, providers, reviews, and film facts. The Cut Room homepage uses the shared ComingSoon preview stories and its own *Spin Cycle* lead. Its Latest Stories filters and filmstrip links work with JavaScript disabled.

### Homepage layout explorations

`sh bin/shared-content.sh comingsoon-layout-previews` installs three standalone Site A pages. The header links between them for comparison.

| Preview | First action |
| --- | --- |
| Release Runway (`homepage-preview-runway`) | Change the date window or open a release from a chronological schedule populated by the catalog. News and streaming picks follow the schedule. |
| Contact Sheet (`homepage-preview-sheet`) | Scan a photo wall or narrow it to a coverage format. Every tile opens its article. |
| Cross-Reference (`homepage-preview-index`) | Look up a published movie or show by title and see its catalog entry beside related coverage. The featured list includes selected fictional examples from the preview library. |

Release windows, story filters, and title search can be used with JavaScript disabled. Contact Sheet also filters in place when JavaScript is available.

References were captured in a desktop browser at 1440 × 1000 on 6 November 2026, using full-page screenshots, HTML, font and branding inspection. News, reviews and catalog examples in the presentation are fictional.

| Site | Reference | Masthead | Accent | Heading / body | Observed homepage structure |
| --- | --- | --- | --- | --- | --- |
| A | [ComingSoon](https://www.comingsoon.net/) | `#122E39` | `#187DAF`, yellow `#DFE000` | Archivo / Open Sans | Three Top Stories, four Must Read cards, Breaking News rows and sidebar |
| B | [SuperHeroHype](https://www.superherohype.com/) | `#87060C` | `#C30912`, gold `#F6C223` | Roboto / Rubik | Three Top Stories, four Breaking News cards, Latest News rows and sidebar |
| C | [GameRevolution](https://www.gamerevolution.com/) | White | `#DF1362` | Play / Rubik | Large review hero, five compact reviews, Gaming Culture cards, Gaming News |
| D | [PlayStation LifeStyle](https://www.playstationlifestyle.net/) | `#030C1B` | `#0072BB`, `#2BA1CB` | Inter / Inter | Large news feature, five compact stories, More Stories and Trending |

## Geometry and responsive behavior

White pages; 1200px maximum content width; 120px side margins at 1440px; 24px gutters. Square editorial cards, bold 24px headlines, roughly 36px uppercase section headings and landscape artwork. Film sites have three equal lead cards. Game sites use a two-thirds/one-third lead split. C has magenta triangular review scores; D has rounded blue category badges. At tablet widths four cards become two; at phone widths features and sidebars stack, and a keyboard-accessible menu replaces desktop navigation. Catalog details inherit brand typography and accents. Episode listings remain text-only. Calendars use a desktop month grid and mobile agenda.

## Assets

Theme paths below are relative to `wp-content/themes/msc-screening-room/`.

- `assets/network/logo-a.svg` through `logo-d.svg`: actual desktop SVG marks extracted from the respective homepages linked above. Paths and colors are preserved. GameRevolution lacked a viewBox; the measured browser bounding box `0 0 350 38` was added for responsive sizing. These are the user's network brands, used for the requested presentation.
- Local WOFF2 fonts and individual OFL licenses are in `assets/fonts/`. Sources: [Google Fonts](https://github.com/google/fonts), directories `ofl/archivo`, `ofl/opensans`, `ofl/rubik`, `ofl/roboto`, `ofl/play`, `ofl/inter`. No external font request is required.
- `editorial-film-hero.webp` and `editorial-game-tech.webp`: original generated 2 × 2 artwork sheets, rendered as eight CSS background quadrants at 200% scale. They depict fictional science-fiction, superhero and game concepts. No reference-site photographs or articles are copied.
- Imported source and image provenance stays attached to records and is rendered in the footer. Published IGDB-sourced games receive a visible static IGDB footer link.

## Presentation adaptations

The game-site references have approximately 300px advertising/loading gaps above the hero. The demo uses a 120px labeled advertisement placeholder. Ads, trackers, social embeds and third-party widgets are omitted. Navigation targets local category archives and catalogs. A compact site switcher and fictional-content label support the presentation. Colors are observed from source branding and screenshots; minor spacing and responsive behavior are implementation approximations.

## API contract

[IGDB's official documentation](https://api-docs.igdb.com/) supplies Twitch authentication, game search, platform IDs and regional release-date formats. IGDB supports commercial use through its partner program. Commercial publication should use the appropriate IGDB agreement. Credentials are configured privately in Network Admin. The presentation uses fictional records and fixture-based verification until credentials are configured.


## Upcoming-release guide reference (2.5.0)

Reference: https://www.tvinsider.com/shows/calendar/ (retrieved 2026-11-10 using Firecrawl).

Observed from page content and branding metadata: a TV Premieres Calendar heading, month tabs, date-grouped entries, premiere labels, provider-logo badges, artwork and linked show titles. Branding extraction reports Open Sans body text, IBM Plex Sans Condensed headings, white backgrounds and blue accents (#0996D6 / #00AEEF). These values describe the reference, not the network theme.

The reference's screenshot URL was returned but its storage host was denied by the environment network policy. Source screenshot pixels were therefore not inspected; no exact spacing or pixel-fidelity claim is made. The implementation preserves each network site's existing logos, colors and typography. Local Chromium screenshots verify the implemented layout.

Proposed improvements implemented here: full-width release-guide heading and count; nine scrollable month choices; title search and type filters; published service-logo selectors with counts; separate service-profile links; date-grouped artwork cards without excerpts; compact list view; service filters retained in month/week grids; AJAX navigation with ordinary-link fallbacks; sixty-entry result pagination and clear empty states. Generated monthly WordPress pages use the same live renderer and retain editorial introductions. Responsive layouts use three, two and one card columns; service/month rows scroll within their own bounds.

Service assets and sources: multisite-shared-content/assets/services/README.md. SVG originals and PNG renderings are bundled, then seeded into ordinary shared Streaming Service records and WordPress media attachments.
