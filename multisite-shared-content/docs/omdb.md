# Search and import from OMDb

Version 1.4 adds title search, a review screen and selective imports for Movies, TV Shows and Episodes. It uses the existing shared fields, Person relationships, categories and owner-aware media. Imports change global values; local overrides remain intact.

## Update your Mac

Keep Docker Desktop running. From your existing `Wordpress-MCP` folder:

```sh
export PATH="/Applications/Docker.app/Contents/Resources/bin:$PATH"
git pull --ff-only
sh bin/shared-content.sh setup
```

Your database, administrator credentials, existing records and local overrides are retained. The additive schema update gives Episodes an External IDs field. It preserves custom field definitions and does not reintroduce types or unrelated fields intentionally omitted from an already upgraded 1.3 schema.

## Configure the API key once

1. Open **My Sites → Network Admin → Shared Content → OMDb API**.
2. Paste your OMDb key and click **Save OMDb settings**.
3. The status changes to **Configured**. The saved key is never shown again; a blank submission retains it. Use Remove to clear it.

The key stays in the WordPress network database and is never committed to GitHub, embedded in page source, or sent to browser JavaScript. OMDb calls are made on the server over HTTPS. Only network administrators can configure the key; only users with global creation/editing permission can search/import. Local-only editors cannot use the network key.

For server-managed configuration, set `OMDB_API_KEY` in the gitignored `.env` and rerun `setup` to recreate containers, or define `MSC_OMDB_API_KEY` in your private `wp-config.php`. Precedence is config constant, environment variable, then network settings. When a server-managed key is present, the settings page reports that source and disables replacement. Avoid commands that print the resolved Compose environment when diagnosing credentials.

## Find a title

On Site A, B or C, open **Shared Content → Search OMDb**. Enter a title, choose Movies, TV Shows, Episodes or All titles, and optionally enter a four-digit release year. Search returns up to ten candidates per page, with title, year, type, IMDb ID and a poster when available. Previous/Next navigates the provider's supported pages (up to 100).

Choose **Preview [title]** to retrieve full details. You can also start from an existing record's **Search OMDb and import title information** button. Its type and destination are carried into the review screen.

## Review and import

- New records start with available fields selected. The Title field is required. The import uses your schema's publication default, which is **draft** in the supplied setup.
- When an IMDb ID already exists in this network, the preview targets that record. Update fields start **unchecked**. Select only the global values you want to replace. Unselected data, other external IDs, custom metadata and all per-site overrides are preserved.
- IMDb identity is always merged into **External IDs → `imdb`**. Repeating a create submission reuses the existing record instead of creating another title or more People. Multiple records with the same IMDb ID, archived/trashed matches, wrong-type destinations and conflicting identities require review rather than silent merging.
- Check poster import to download the returned image into the **current site's** media library. Other sites inherit its owner/attachment reference. Existing posters remain unchanged unless you select that option. A missing/unavailable poster produces a warning while the selected text fields still import.
- Select Actors, Directors or Screenwriters to import Person links. Review each name's selector: reuse an existing Person, create a new Person, or do not link. A unique exact name match is suggested, but OMDb does not supply Person IDs, biographies, headshots or birth dates. Ambiguous names start unlinked. New People receive only the supplied name, selected professions and credit-source information. No biography or identity is invented.
- Person selection lists contain up to 500 active records. You can finish more complex linking in ACF after import. Each credit list preserves OMDb's ordering, and one newly created Person can fill several selected roles.

After import, use **All fields and publishing** to review/publish the title on each desired site. New People can be published from **Shared Content → People**. The front-end templates show Person cards and filmographies only when the Person and credit are published on that site. Other participating sites receive changes through the existing queue worker.

Imports use a 15-minute, user/site-bound preview snapshot and optimistic global revision checks. If somebody changes the shared record while your preview is open, the import returns **HTTP 409**. Open a fresh preview and review the latest values. New People and title changes roll back together after a write conflict; a poster created for a failed import is removed.

## Fields OMDb can supply

| Source information | Destination |
| --- | --- |
| Title and full plot | Native title, description and synopsis |
| Genre | Native genre categories |
| Country / language | First value in existing single-value fields; complete original lists retained in metadata |
| Movie release / DVD date | Theatrical / physical release dates |
| Runtime | Movie / Episode runtime in minutes |
| Series release and season count | TV premiere date and number of seasons |
| Episode season, number and release | Episode numbering and release date |
| Director, Writer, Actors | Movie legacy credits plus reviewed shared Person relationships |
| Rated / Production / Website | Movie rating category, production companies and official website where the schema supports them |
| IMDb ID | Merged External IDs |
| Scores, votes, awards, box office, year and full source information | Additional Metadata → `omdb` |
| Returned Poster URL | Optional owner-aware poster / episode image import |

Missing `N/A` values are skipped. A year alone is not converted into a made-up premiere/release date. OMDb Production is not treated as a distributor, and network/streaming availability is not guessed. Custom native body aliases are respected. Fields that do not fit custom schema validation are skipped with a note; the existing definitions are not replaced.

An Episode must link to an existing shared TV Show. When its `seriesID` matches an imported Show's IMDb ID, the parent is suggested automatically. Otherwise choose the Show in the preview, or import the Show first. Importing a TV Show does not fetch every season or Episode automatically.

## Caching, limits and images

Successful title searches are cached for 15 minutes; full title details for six hours. The cache is network-wide and separated by a fingerprint of the configured key. Changing the key does not reuse another key's cached responses. A user can make up to 30 new provider requests per minute; cached results remain usable. OMDb's [free plan](https://www.omdbapi.com/apikey.aspx) allows 1,000 requests per day; its own quota and activation errors are reported without exposing the key.

The optional poster importer uses public image URLs returned by the ordinary OMDb response. It does not call the separate paid Poster API. Downloads accept supported IMDb/Amazon image hosts over HTTPS, reject redirects and credential-bearing URLs, and enforce size/format limits. Posters are copied only when explicitly selected; gallery/headshot images are not invented from title posters.

## Verification

Automated provider-contract tests use controlled OMDb-shaped HTTP responses with the real WordPress HTTP API, database, media library and ACF adapter. They cover pagination/filtering, caching, missing data, errors, quotas, credential isolation, field mapping, selective updates, Person ordering/reuse, ID deduplication, stale versions, cross-site overrides and transaction/poster cleanup. Chromium exercises the real search, preview, import, poster/ACF and conflict screens.

The development cloud blocks outbound requests to OMDb, so the supplied live key could not be verified there. These automated tests do not claim a live OMDb response. Your Mac's server makes the live request when you submit a title search.

Use only a disposable network for the fixture tests:

```sh
MSC_DISPOSABLE_TESTS=1 sh bin/shared-content.sh test
MSC_DISPOSABLE_TESTS=1 sh bin/omdb-browser-tests.sh
```

The browser helper temporarily installs a test-only plugin and config constants, then removes them on exit. Normal setup/import never enables fixtures. No real API key is present in the tests or release packages.

Official references: [OMDb usage, parameters and changes](https://www.omdbapi.com/), [API key plans](https://www.omdbapi.com/apikey.aspx).
