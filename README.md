# WordPress with Advanced Custom Fields

A Docker Compose installation of **WordPress 7.1.4**, MariaDB, and **Advanced Custom Fields Free 6.8.10**. This repository contains the configuration and setup automation; a running site needs Docker or a PHP/database hosting service. GitHub Pages cannot run this installation.

## Comic Books on SuperHeroHype

Version 2.8.0 adds [story timelines](docs/timelines.md), including an editable MCU example with movie and TV columns, phase colors and relative dates.

Version 2.7.0 replaces thin training stubs with a complete-data rebuild requiring descriptions, linked cast/crew and downloaded poster/featured artwork, and adds shared Franchise pages containing multiple movies and TV shows. See [the training and publishing workflow](docs/training-and-bulk-publishing.md) and [Franchise pages](docs/franchises.md).

Version 2.5.0 added live upcoming-release guides, streaming-service logos/profiles and an admin generator for monthly pages. See [the release-page workflow](docs/upcoming-releases.md).

Version 2.4.1 added GCD cover imports and direct Comic Book arc assignments, with fixes from the comic audit. Version 2.4.0 introduced Comic Books, Comic Issues and Story Arcs on Site B. Series pages list their issues; arcs provide a reading order across series. Search and batch-import metadata from the free Grand Comics Database API, then set arc membership and part numbers in the editor. Exact on-sale dates feed the release calendar. See [the comic workflow](docs/comic-books.md) and [audit findings](docs/comic-audit.md).

## Four branded sites with shared catalogs

The **Multisite Shared Content 2.10.3** plugin provides distinct TV Show, Episode and Movie schemas, with authoritative network records, native posts on each participating site, per-field overrides, relationships, noindex, and supported ACF controls. Follow [the Mac shared-content quickstart](docs/shared-content-quickstart.md). Catalog detail pages show one overview, and TV season links load episodes without a full page refresh.

Movie editors can set original synopses, viewing links, FAQs, additional crew, score context and release history in Shared Content or ACF. Use **Production budget source URL** to cite the budget figure. Tag news posts with the movie title or slug and with a coverage type such as **Interviews**, **Features**, **Reviews** or **News**. On the local four-site network, `sh bin/shared-content.sh movie-preview` applies the Iron Man example as Site A overrides and adds sample coverage. The optional positional arguments are Site A attachment IDs for the backdrop, poster and gallery image.

For commercial score display, Rotten Tomatoes® provides licensing and attribution guidance, and Metacritic’s terms call for written permission to use its content commercially. [Rotten Tomatoes licensing](https://www.rottentomatoes.com/help_desk/licensing) · [Metacritic terms](https://www.fandom.com/terms-of-service-pp1)

`sh bin/shared-content.sh movie-portraits` adds the bundled Wikimedia Commons portraits to linked Iron Man People profiles on Site A and publishes their drafts. Existing portraits take precedence. Each bundled portrait has source, author and license details in the movie and profile footers.

### Advertising takeovers

In the site's dashboard, open **Ad Takeovers → Add Ad Takeover**. Choose **Entire site**, **Selected pages**, or **Tagged pages**. For selected pages, type a few letters of a published title and choose it from the dropdown. For tagged pages, choose one or more WordPress tags. A published article or page qualifies when it has any of those tags. Individual page selections take precedence over tags, followed by entire-site campaigns.

Choose Media Library artwork, destinations and a palette. The optional wallpaper covers the page background and the campaign logo appears in each ad. Publish to display it, or choose **Off** to suspend it. On wide screens the side ads follow the reader’s scroll and stop above the footer. The top banner also appears on narrow screens.

In **Publishing period**, enter a start, an end, or both using the site timezone. Publish the campaign to arm the schedule, and use **Preview saved takeover** to check it as an administrator. Existing campaigns with blank dates keep their usual behavior. If a CDN or server serves cached pages before WordPress runs, exclude takeover URLs from that cache when scheduling ads.

Run `sh bin/shared-content.sh takeover-demo` with Docker Desktop running to install or refresh the page-only Avengers: Doomsday example on Site A. It finds the published movie or creates a local preview and prints its URL. The ads combine Marvel Studios’ official logo with original concept artwork. The command adds verified character credits, fills missing images, and publishes linked People drafts on Site A. Six bundled credit cards use initials where a reusable photo could not be verified. Existing trailer selections and profile images are preserved.

Run `sh bin/shared-content.sh star-wars-demo` to refresh Site A’s A New Hope movie and install its separate 50th-anniversary takeover. The sample runs February 12 through February 21, 2027, in the site timezone. The command prints the campaign editor link and the movie URL. The movie lists the February 19 theatrical re-release in its release history. [StarWars.com announcement](https://www.starwars.com/news/star-wars-50th-anniversary-theatrical-release)

The manual WordPress installer `site-takeovers-1.3.1.zip` is built by `python3 tools/package-msc.py`.

YouTube trailers use their individual video thumbnails automatically. Vimeo trailers show their own player preview. Movie pages display genre labels in title case and omit a trailing “film” while keeping the saved category names. On an existing local demo, run `sh bin/shared-content.sh restore-demo-genres` to restore the genre assignments supplied by earlier takeover installers.

In **Shared Content → Manage fields** and the ACF editor, use the visual editors for descriptions and synopses. Search categories by name and use the remove button on a selection to clear it. In **Cast and crew**, find a Person once and assign all of their roles on the same row, including an actor’s character. Movie ratings and release statuses use controlled menus. Network administrators can add choices under **Shared Content Network → Categories**.

In **Manage fields**, add release dates, viewing links, questions and answers, and external IDs with labeled controls. Both editors put Cast and crew after the other fields. Aggregate review scores, optional editorial details and additional settings open on demand.

Catalog archives now use designs suited to Movies, TV Shows, Games, People, Episodes and company/platform directories, with metadata instead of excerpts, scoped search/filtering and asynchronous pagination. See [the archive guide](docs/catalog-archives.md).

Version 2.3 adds site-specific editorial Reviews with 1–10 half-point scores, verdicts and positives/negatives. Item images and score sections prioritize the site’s own published review, with Gold/Silver/Copper recommendations. Image badges show only published editorial reviews; aggregates appear on item pages. Games import Metacritic/OpenCritic from Wikipedia without API keys; Movies/TV keep OMDb. See [the review editing and score-source guide](docs/reviews.md).

Version 2.2 adds trailer fields for Movies, TV Shows and Video Games, embedded YouTube search using the existing Serper key, and a compact trailer carousel showing three videos across on desktop, two on tablets and one on phones. Sidebars show five actors in saved credit order with a link to the full cast.

Version 2.1 adds shared company, developer, publisher, console/platform, streaming-service and TV-network pages, with published relationships in detail sidebars. Empty Episode sections are hidden. Release calendars offer month/week views and expandable daily limits. See [the update and editing guide](docs/update-2.1.md).

Poster and other image fields include **Google Images / image URL**: search Google Images inside the editor, select a result, preview it and save. Configure a private Serper key under **Network Admin → Shared Content → Image Search**. See [the image workflow](multisite-shared-content/docs/presentation.md#google-images-and-image-urls).

ACF includes **Save and publish on this site** and a network-administrator **Save and publish globally** action. See [the ACF publishing guide](multisite-shared-content/docs/acf.md#publish-from-acf).

For existing installations, [the 1.8 update guide](docs/update-1.8.md) covers the one-time Site A catch-up, duplicate Person checks, Select all import data, global defaults and delivery status, public season pages and footer credits.

```sh
sh bin/shared-content.sh setup
sh bin/shared-content.sh demo
```

This creates four branded sites: ComingSoon, SuperHeroHype, GameRevolution and PlayStation LifeStyle, with fictional news, Video Games via IGDB, per-site content-type controls, Sony filtering and dynamic release calendars. Follow [the 2.0 update guide](docs/update-2.0.md) for Mac update commands and private IGDB configuration. It runs a local synchronization worker. See [the ACF guide](multisite-shared-content/docs/acf.md) and [verification/limits](VERIFICATION.md). Build the installable ZIP with `python3 tools/package-msc.py`. See [the TV/Movie field lists and upgrade instructions](multisite-shared-content/docs/content-fields.md).

## Mac multisite testing

For a local network with a main site plus **two test sites**, follow [the Mac multisite guide](docs/mac-multisite.md). After generating `.env`, run:

```sh
sh bin/start.sh multisite
sh bin/verify.sh
```

This creates `http://localhost:8080/site-a/` and `http://localhost:8080/site-b/`, enables ACF across the network, and configures Apache subdirectory routing. The same command can convert the original single-site installation while preserving its content and administrator account. Back up an existing installation before conversion. Subsequent setup runs preserve the network and avoid duplicate sites.

## Start locally as a single site

Requires Docker Engine with Compose v2, Python 3, and a POSIX shell. On macOS or Windows, use Docker Desktop (and WSL or a compatible shell on Windows).

```sh
git clone https://github.com/TerminalDogma/Wordpress-MCP.git
cd Wordpress-MCP
python3 bin/configure.py
# Edit .env to set your admin email and optional site title.
sh bin/start.sh
sh bin/verify.sh
```

Open **http://localhost:8080** and **http://localhost:8080/wp-admin/**. Sign in as `siteadmin` using `WORDPRESS_ADMIN_PASSWORD` from your local `.env` file. Configuration generates random passwords, never prints them, and preserves an existing `.env`. Keep that file private; it is excluded from Git and the Docker build context. Editing the admin password or email in `.env` after installation does not change the existing WordPress account.

ACF is activated during setup. Use **ACF → Field Groups** to create your fields. This uses ACF's official upstream source, not the separate Secure Custom Fields fork. ACF Pro is not included and needs your own license and package.

The web port binds to loopback, and the database has no published port. To use a different port, change both `WORDPRESS_PORT` and `WORDPRESS_URL` in `.env` **before the first setup**. Later URL changes also require updating WordPress's `home` and `siteurl` options.

## Manage the installation

```sh
# WP-CLI commands
docker compose run --rm -T cli plugin list
docker compose run --rm cli user update siteadmin --prompt=user_pass

# Stop without deleting the database or uploaded files
docker compose down

# Restart an installed site
docker compose up -d --wait db wordpress
```

Named volumes retain the database and WordPress files, including uploads. `docker compose down --volumes` **deletes that data**. The setup command can be rerun against the same database; it preserves existing accounts and content and reinstalls the ACF version pinned in the image.

Add project PHP code under `wp-content/mu-plugins/`; it is mounted read-only into WordPress. Use ACF's JSON export or PHP export to keep field definitions in Git. Do not commit database exports, uploads containing private data, credentials, or licensed plugin packages.

## Versions and verification

The WordPress/PHP 8.3, WP-CLI, and MariaDB 11.4 images are pinned by digest in `Dockerfile` and `compose.yaml`. ACF 6.8.10 is pinned to upstream commit `fa4ceb7b2773ac520d9b3c36d7271853bccd7a8f`, and its archive checksum is verified during the build. The image includes WP-CLI and the ACF package, so setup does not need to download plugins at runtime.

`bin/verify.sh` checks the installed database, ACF activation, and a real ACF field write/read. It removes its temporary draft page afterward. GitHub Actions checks fresh single-site and multisite installations, conversion with existing content, ACF on every test site, authenticated dashboards, subsite asset routing, repeat setup, and availability of both amd64 and arm64 image manifests.

## Hosting and updates

This is configured for local use. To deploy publicly, select a Docker-capable server or adapt it to a WordPress host, set the real URL, configure HTTPS and a reverse proxy, change `WP_ENVIRONMENT_TYPE` to `production`, and arrange persistent storage and backups. Do not expose the initial WordPress installer on a public address. No hosting account, domain, or public deployment is configured by this repository.

Back up both named volumes before upgrades. Updating a base image alone does not replace WordPress core already stored in the volume; use WordPress's supported core update process and test the result. Update the ACF commit/checksum and rebuild before rerunning setup to change the managed ACF dependency. Check supported upstream versions and security updates regularly; digest pins do not update automatically.

Managed build environments using a private proxy CA can pass it as an optional BuildKit secret:

```sh
docker build --secret id=proxy_ca,src=/path/to/ca-bundle.pem -t wordpress-acf:local .
docker compose up -d --wait db wordpress
docker compose run --rm -T setup
```

### Site designs, People, categories and image previews

MSC 2.0 includes the optional Screening Room theme, four network brands, Movie/TV Show/Episode/Person/Video Game templates, linked cast and crew, reusable categories, and ACF image previews. See [the update and editing guide](multisite-shared-content/docs/presentation.md). Existing shared values and site overrides are retained.

### Search and import content

MSC 1.5 adds keyless TVmaze TV/episode data, Wikidata Movie/Person facts, Wikipedia biographies, and individually licensed Commons images. **Shared Content → Import Content** includes reviewed field selection, persistent attribution and a resumable Show → season → Episode importer. Sources permit commercial reuse under their stated licences; CC BY-SA obligations still apply. Legacy OMDb screens are disabled by default and previously imported content needs review. See [the import and Mac update guide](multisite-shared-content/docs/import-content.md).

### Episode administration

MSC 1.7 opens **Episodes → By TV Show** by default: one row per Show, then season batches, then the selected batch's native Episode list. Counts and search keep large libraries manageable; **All Episodes** remains available for bulk work. Show editors link directly to related Episodes and parent-prefilled creation; native lists honor local overrides while the Shared Content catalog displays global relationships. See [the administration guide](multisite-shared-content/docs/episode-administration.md).
