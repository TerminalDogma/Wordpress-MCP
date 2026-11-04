# Import Movies, TV Shows, Episodes and People

MSC 1.5 replaces the default OMDb screen with **Shared Content → Import Content**. These sources require no API key and permit commercial reuse under their stated licences. Source credits and licences are retained for each imported field and supported image. CC BY-SA content retains its attribution and ShareAlike obligations; the importer does not relicense it as proprietary content.

## Update the installation on your Mac

Start Docker Desktop, open Terminal in your `Wordpress-MCP` checkout, and run:

```sh
export PATH="/Applications/Docker.app/Contents/Resources/bin:$PATH"
git pull --ff-only
sh bin/shared-content.sh setup
```

Setup preserves your existing `.env`, WordPress records, uploads and site overrides. The plugin and theme are mounted from your checkout, so a new ZIP installation is unnecessary. If Git reports local source changes, commit or stash those changes before pulling. Do not delete your Docker volumes to update.

Open `http://localhost:8080/site-a/wp-admin/` (or your configured port), sign in with your existing administrator account, and choose **Shared Content → Import Content**. The same workflow works on Sites B and C. Ordinary editors have local editing permissions; a global editor/creator is needed for imports.

## Sources and coverage

| Content | Source | What is imported | Licence |
| --- | --- | --- | --- |
| TV Shows | TVmaze | Title, description, dates, network/platform, country, language, genres, status, season count, selected cast/crew | CC BY-SA 4.0 |
| Episodes | TVmaze | Verified parent Show, title, description, season/episode numbers, original air date/time, known network timezone, runtime, individually reviewed guest cast/crew | CC BY-SA 4.0 |
| Movies | Wikidata | Structured title, dates, runtime, country/language/genres, companies, selected cast/directors/writers/producers, verified external IDs | CC0 1.0 |
| People | Wikidata; optionally TVmaze | Wikidata name, birth date, country, occupations, website and IMDb Person ID where available; TVmaze name, birth date and country | CC0 for Wikidata; CC BY-SA for TVmaze |
| Introductions/biographies | English Wikipedia article identified by the Wikidata item | Article introduction, adapted to description and synopsis | CC BY-SA 4.0 |
| Movie/Person images | Wikimedia Commons file identified by Wikidata | Optional image with verified author/source/licence metadata | Explicit CC0, CC BY or CC BY-SA per file |

Data completeness varies. Movies are English-language Wikidata search results verified as films or film subclasses; People must be human items. A film/person with no English article can still supply structured facts. TVmaze has TV People, but no biography text. Movie dates use the earliest available full Gregorian release date, which may be a festival/foreign release, and do not imply a specific local theatrical release. Check it against your intended release category. Partial dates are retained as source details without inventing a day/month. Runtime quantities are converted from supported seconds/minutes/hours into minutes.

TVmaze posters, episode stills and headshots are **excluded** because its API data licence does not establish commercial rights to those studio images. Commons files with missing licence/author metadata, NC/ND terms or reported restrictions are excluded. Jurisdiction-only public-domain claims are not automatically accepted. An available Commons image may be a photo or illustration rather than a theatrical poster. You can use the existing ACF image picker for assets you have rights to use. Copyright licence metadata does not establish endorsement or clear every possible use of a person's likeness.

## Import or enrich a Movie, Show or Person

1. Choose a content type, enter a title/name, and select **Search**. For People, choose Wikidata + Wikipedia or TVmaze. Results show source IDs and descriptions to distinguish similar names.
2. Open a preview and check the selected identity, values and licences. On an existing record's global editor, **Search and import content** explicitly connects that record to the selected source. Verified IMDb IDs also connect matching existing Movies/Shows/People automatically; titles/names alone never merge records.
3. Select fields. New records start checked; updates start unchecked. Unselected fields, other external IDs, custom metadata, local overrides and existing publication settings are retained. External identity and selected field attribution are always saved together.
4. Select the credit roles you want linked. People reuse matching source IDs across cast/crew roles; new People are separate shared records. Same-name People with different source IDs remain separate. Credit People initially have the available source facts; search/import their profile afterward to enrich their biography and headshot.
5. Opt into an image if offered. Its preview shows the author, source and licence. Downloads create owner-aware WordPress media with persistent credit metadata/caption. An unavailable image produces a warning while preserving your current image and saving the valid text.
6. Import, then use **Edit with ACF** or **All fields and publishing**. New records follow the configured status (draft by default); import does not automatically publish a catalog. Other enrolled sites receive updates through the existing synchronization worker.

If an existing name-only Person needs connecting, start from that Person's global editor. A different already-identified Person is not overwritten or silently merged. Duplicate/conflicting external IDs and archived matching records require explicit editorial review.

## Import Episodes correctly

1. Choose **Episodes**, search the **show's title**, and select **Choose episodes from …**.
2. Import/connect that TV Show first if prompted. To connect an existing Show, start from its global editor, search for it and import selected TVmaze fields. Its stable TVmaze ID identifies the parent; the importer never guesses from the show name.
3. Choose a season. The list includes specials, dates where known and unnumbered episodes. **Preview episode** allows individual field and guest-credit review, or choose several episodes/select the entire season.
4. **Import selected episodes**, then **Start / resume**. Progress is saved after each episode. Keep the page open while it runs. Pause after the current episode, or return to the same URL as the same user/site within 24 hours to resume. Temporary rate limits retry with a bounded pause; other failures leave an actionable result and can be retried individually.
5. Existing source-identified Episodes are skipped by default. Check **Refresh existing Episodes** to replace the available source fields. Batch refresh does not import Person credits or images; use an individual preview for those. Source-missing fields and all local overrides are retained. Concurrently edited Episodes are marked **Needs review** instead of overwritten. A completed batch reports results; it is not a claim that every item succeeded.

Each Episode response must identify the same Show as the batch parent. Season zero/episode zero are preserved; absent numbers, dates and runtime remain absent. Air date uses the provider's original local broadcast date, not a UTC conversion of the timestamp. Country/network timezone is only mapped when supplied and valid. This is the original network airing, not a guarantee of release timing or streaming availability in every region.

An older OMDb Episode without a TVmaze ID is not merged by name or number. Open that Episode's global editor, use **Search and import content**, select its Show/season and preview the matching Episode to connect it explicitly and correct its selected fields. This avoids creating another record for the same Episode when migrating a hand-curated catalog.

## Attribution, legacy content and custom themes

The Screening Room theme displays source/author/licence links and change notices for imported fields, card text, credit portraits, galleries and hero images. Field attribution is tied to a value hash: replacing imported prose with your own local copy does not incorrectly label it as Wikipedia prose. Media credit follows the actual attachment, including references to another site's media library. The structured provenance remains under `additional_metadata.open_import`; image credit also remains in the attachment caption/content and `_msc_open_source` metadata. Retain these credits if you export or adapt the content.

For a custom theme, render `\MSC\Attribution::render($resolved_record)` with imported text and `\MSC\Attribution::image_html($resolved_record)` with the hero/card image. Use `media_html($owner_scoped_reference)` for gallery images and `render($record, ['title', 'excerpt'])` for a card. Pass the effective current-site record and enforce its publication state as the bundled theme does. Do not remove required source/author/licence attribution when changing templates or exporting data.

OMDb's homepage states **CC BY-NC 4.0**. Its old screens are disabled by default in 1.5; new imports use the sources above. Existing OMDb records, images, keys and site overrides are retained, with a warning in the import review. They have **not** been retroactively cleared for commercial use. Replace/review legacy fields, excerpts, images and overrides before commercial publication; a selective refresh only replaces the fields you choose. An application with independently appropriate OMDb rights can explicitly enable the legacy screens with `define('MSC_ENABLE_LEGACY_OMDB', true);` in server configuration. That switch does not grant a commercial licence.

## Reliability and verification

API requests run on the server over HTTPS, with a descriptive User-Agent, bounded response sizes/timeouts and no redirects. Successful TVmaze results cache for one hour; Wikimedia search results for one hour and entity/text/file details for one day. Each provider has a network-wide limit of 12 new requests per 10 seconds. Cached results remain available during cooldown. `429`, `Retry-After`, Wikimedia lag and transient transport failures are reported without claiming content was imported. Episode batches process one at a time and can resume after a connection interruption.

Previews are server-side, user/site/network-bound and expire after 30 minutes. Imports require permission and nonces, validate IDs/schema/parent relationships, deduplicate by identity under database locks, and reject stale global versions. Imported People/title writes are transactional; failed media/conflict imports clean up only their newly downloaded image. Existing records are never deleted as part of an import.

Automated tests use provider-shaped fixtures with the real WordPress HTTP, media, storage, ACF and synchronization APIs. Chromium exercises Show → season → batch progress/reload, Movie/Person imports, licensed images and attribution. Public endpoint shapes were also read independently. This development cloud restricts direct outbound requests to the source services, so these automated tests do not establish a live WordPress-to-provider connection; perform your first live search on your Mac. No source API key is needed.

Official references: [TVmaze API/licence](https://www.tvmaze.com/api), [TVmaze staff on commercial data and images](https://www.tvmaze.com/threads/6552/using-posters-and-data-from-tvmaze-api-on-a-commercial-website), [Wikidata licensing](https://www.wikidata.org/wiki/Wikidata:Licensing), [Wikimedia content reuse](https://developer.wikimedia.org/en-gb/use-content/content/), [Wikipedia reuse](https://en.wikipedia.org/wiki/Wikipedia:Reusing_Wikipedia_content), [Commons file reuse](https://commons.wikimedia.org/wiki/Commons:Reusing_content_outside_Wikimedia).
