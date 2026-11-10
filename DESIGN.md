# Network presentation design

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
