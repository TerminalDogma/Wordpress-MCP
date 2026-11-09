# WordPress with Advanced Custom Fields

A Docker Compose installation of **WordPress 7.1.4**, MariaDB, and **Advanced Custom Fields Free 6.8.10**. This repository contains the configuration and setup automation; a running site needs Docker or a PHP/database hosting service. GitHub Pages cannot run this installation.

## Four branded sites with shared catalogs

The **Multisite Shared Content 2.3.1** plugin provides distinct TV Show, Episode and Movie schemas, with authoritative network records, native posts on each participating site, per-field overrides, relationships, noindex, and supported ACF controls. Follow [the Mac shared-content quickstart](docs/shared-content-quickstart.md). Catalog detail pages show one overview, and TV season links load episodes without a full page refresh.

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
