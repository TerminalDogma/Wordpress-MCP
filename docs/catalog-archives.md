# Catalog archive designs and browsing

Update the existing Mac checkout with Docker Desktop running:

```sh
export PATH="/Applications/Docker.app/Contents/Resources/bin:$PATH"
git pull --ff-only
sh bin/shared-content.sh setup
```

The Screening Room theme now gives each catalog family its own listing design:

- Movies: compact poster grid with release dates, genres, runtimes and content ratings.
- TV Shows: series cards with posters, premiere dates, seasons, networks and status.
- Video Games: cover grid with release dates, genres and platform labels.
- People: portrait directory with professions and countries.
- Companies, developers, publishers, distributors, streaming services and TV networks: logo/profile directory with location and foundation information when available.
- Consoles/platforms: product cards with manufacturer, platform type and launch dates.
- Episodes: text cards with the published parent Show, season/episode numbers, air dates and runtimes.

Headings use the catalog name, such as **Movies**, without an “Archives:” prefix. Category pages for catalog taxonomies use the same browsing system. Layouts inherit each site's brand colors and fonts. Upcoming dated catalogs highlight their next release and link to the Release Calendar; profile directories link to related catalogs.

Listings show metadata without excerpts or description paragraphs. Existing synopsis/description data is retained, and detail-page overviews and editorial homepage excerpts continue to use it.

Use the archive's title/name search, relevant category filter, release year, platform filter and sorting controls. Available options come from eligible published records on the current site. Undated items sort after dated items when sorting by release date. Archive pages show up to 20 entries, with pagination. Applying a filter starts at page one. Filters and pagination update through AJAX, support Back/Forward and can be bookmarked; ordinary forms and links work when JavaScript is disabled.

Local title/date/category overrides apply to search, filters and card metadata. Site D uses its Sony release dates and platform policy. Draft, private, password-protected, removed, archived and disabled content does not contribute titles, counts or filter options. Source and image credits remain in the footer.

The archive model reads shared records and local overrides in batches of 200 and discards body/excerpt content before retaining card data. It builds a per-request list of eligible records for filtering and sorting. This avoids one storage read per ordinary record but is not a high-volume performance benchmark; the broader scale limits in `VERIFICATION.md` still apply.
