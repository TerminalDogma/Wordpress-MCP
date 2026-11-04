# TV Show, Movie, Episode and Person fields

The plugin has separate schemas and generated ACF groups for TV Shows, Movies, Episodes and People. Shared values and local overrides work within each type. A Movie cannot receive TV season fields, and an Episode's parent must be a TV Show.

## Fields available on every type

Title, description, synopsis, URL slug, featured image, SEO title, SEO description and additional extensible metadata. Each site can override these individually. Native title/body and publishing use the full editor; supported custom fields use the ACF form.

## TV Shows — `msc_show`

| Fields | Stored keys / notes |
|---|---|
| Original title, status | `original_title`, `status` |
| Series premiere and finale | `premiere_date`, `finale_date`; indexed ISO dates |
| Network and streaming service | `network`, `streaming_service` |
| Number of seasons and episodes | `number_of_seasons`, `number_of_episodes`; nonnegative integers |
| Genres, country, language | `genres` list, `country`, `language` |
| Poster and promotional images | `poster`, `promotional_images`; owner-aware media |
| Official website and external identifiers | `official_website`, `external_ids` |

Episodes remain separate records, related through a permanent Show UUID. Their season and episode numbers are stored on each Episode.

## Movies — `msc_movie`

| Fields | Stored keys / notes |
|---|---|
| Original title and release status | `original_title`, `release_status` |
| Theatrical release | `release_date`; indexed ISO date |
| Digital, streaming and physical releases | `digital_release_date`, `streaming_release_date`, `physical_release_date`; indexed ISO dates |
| Additional regional dates | `additional_release_dates`; extensible JSON |
| Runtime in minutes | `runtime`; nonnegative number |
| Director and writers | `director`, `writers`; text |
| Cast and roles | `cast`; sanitized rich text |
| Distributor and production companies | `distributor`, `production_companies`; text |
| Content rating | `content_rating`; text |
| Streaming service | `streaming_service` |
| Genres, country and language | `genres` list, `country`, `language` |
| Poster, promotional images and featured image | Owner-aware media |
| Official website and external identifiers | `official_website`, `external_ids` |

Movies have no series premiere/finale fields, TV network, season/episode counts, or parent-show field. The shared chat defined the detailed TV list and outlined Movies in terms of release dates, cast, runtime and distributor; the additional film fields above are the shipped starter configuration.

## Episodes — `msc_episode`

Parent Show, season number, episode number, overall episode number, release date, air time, IANA time zone, network, streaming service, runtime, episode image, production code and additional release dates, alongside the common fields.

## Upgrade an existing Mac installation

```sh
git pull --ff-only
sh bin/shared-content.sh setup
sh bin/shared-content.sh demo
```

Setup enables TV Shows, Episodes, Movies and People on the three demo subsites. The demo adds a fictional Movie even if your Show/Episode demo already exists, without replacing edits to those records. Open **Shared Content → Movies**, select the movie, and choose **Edit supported fields with ACF**.

The one-time additive schema upgrade adds Movies and missing built-in fields to stored network configuration. It keeps existing labels, field definitions, options, override policies, values, UUIDs, revisions, native post IDs and local overrides. It does not automatically enroll other sites in Movies. For ZIP installations, network-activate the update, then enable Movies and People on the desired sites under **Network Admin → Shared Content**.

## People and structured credits — `msc_person`

People have a name, biography, synopsis, headshot, professions, country, birth date, website and external IDs, in addition to common SEO/media fields. Movies, TV Shows and Episodes link ordered Person lists using `actors`, `directors`, `screenwriters` and `producers`; TV Shows also use `creators`. The original Movie `director`, `writers` and `cast` text remains available for legacy/character-role notes.

The source of field definitions remains the network schema. Custom field definitions take precedence over new defaults. ACF supports scalar controls, category selectors, single/multiple relationships and owner-aware media previews. JSON uses the full editor. Category-backed fields materialize native taxonomies; genres/professions support multiple choices. See [the presentation and editing guide](presentation.md) for all category names, image controls, Person links and per-site templates.
