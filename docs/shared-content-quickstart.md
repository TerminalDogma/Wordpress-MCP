# Test shared Movies, TV Shows, Episodes and People on your Mac

This adds the **Multisite Shared Content** plugin to the Docker environment already in this repository. It creates **three independent test subsites**, activates ACF and MSC, enrolls those subsites, and starts a local background synchronization worker. Version 1.3 also styles the sites as Site A (green), Site B (cream/red) and Site C (purple), with detail templates, linked People, native categories and image previews. See [the presentation guide](../multisite-shared-content/docs/presentation.md).

## Install or update

Keep Docker Desktop running. In your checkout:

```sh
git pull --ff-only
python3 bin/configure.py
sh bin/shared-content.sh setup
sh bin/shared-content.sh demo
```

If this is your first checkout, follow [the Mac setup guide](mac-multisite.md) to install the tools and clone the repository first. Preserve your existing `.env`; its administrator credentials remain valid. Commit or stash local source changes before pulling. Back up existing site content before changing a network configuration.

Setup adds Site C to the earlier Site A/Site B network. It does not replace your WordPress database. The source plugin is mounted from the checkout, so code changes are available without reinstalling its ZIP. Keep using the shared-content commands below so the same Compose overlay and worker are present.

## Open the demo

With the default port:

- Site A: http://localhost:8080/site-a/wp-admin/
- Site B: http://localhost:8080/site-b/wp-admin/
- Site C: http://localhost:8080/site-c/wp-admin/
- Network settings: http://localhost:8080/wp-admin/network/admin.php?page=msc-network

Log in using `siteadmin` and `WORDPRESS_ADMIN_PASSWORD` from `.env`, or your existing administrator account. On each subsite, open **Shared Content → TV Shows** or **Episodes**. The demo command prints direct editing links as well.

A fresh demo creates the fictional **The Signal**, **First Contact** and **The Last Light**, plus linked **Maya Chen** and **Julian Vale** Person profiles. Older demos keep their saved names and fields. All appear as native posts on the three sites; open `/site-a/`, `/site-b/` or `/site-c/` to browse their styled templates. Sites A and C inherit the episode date `2099-01-01`. Site B overrides it to `2099-01-02` and has its own SEO title. They are published for local preview and noindex by default. Running the demo again keeps your edits. Upgrading an older demo adds its missing Movie without resetting TV records. Movies have their own release dates, runtime, director, cast and distributor fields: see [the field reference](../multisite-shared-content/docs/content-fields.md).

## Try the key workflow

1. On Site A, open the demo Episode through Shared Content and choose **Edit supported fields with ACF**.
2. Switch to **GLOBAL EDITING**, change Release Date, and save.
3. Wait a few seconds for the worker. Sites A and C follow the new date; Site B retains its override.
4. On Site B, open the episode's ACF form in **LOCAL EDITING**. Uncheck Release Date's Override control and save.
5. Site B now inherits the newest global date. You can check Override again and save a different or intentionally empty local value.

The Show/Episode relationship uses one global UUID. Each site's ACF Post Object control resolves it to that site's native Show post ID. Global edits can be made on any enrolled site by an authorized global editor. Ordinary editors receive local editing/publishing capabilities by default; network/site administrators manage permissions.

Use **All fields and publishing** for titles, descriptions, JSON, publication and global revisions. Both editors support Person/category selectors and media previews; ACF provides searchable, ordered Person links. Each theme controls its own rendering. ACF is used for the supported custom fields listed in [the ACF guide](../multisite-shared-content/docs/acf.md), not for unsupported Pro or third-party fields.

## Synchronization and daily commands

The local `shared-worker` service checks for queued work every five seconds. Propagation is asynchronous; the global save is authoritative immediately, while native post/meta projections can lag. Inspect **Network Admin → Shared Content** for pending or failed jobs.

```sh
# Manually process a bounded queue batch
sh bin/shared-content.sh queue

# Inspect queue status
sh bin/shared-content.sh wp msc status

# Run normal WP-CLI with the source plugin mounted
sh bin/shared-content.sh wp --url=http://localhost:8080/site-b/ plugin list

# Stop the environment while preserving site data
sh bin/shared-content.sh stop

# Resume the same environment and worker
COMPOSE_FILE=compose.yaml:compose.shared-content.yaml docker compose up -d --wait db wordpress shared-worker
```

The worker is a local development convenience. A production network needs a supervised queue/cron process, backups, monitoring and staging validation. Network-wide success is established by queue/mapping status, not merely by clicking Save.

## Install the ZIP elsewhere

Build with `python3 tools/package-msc.py`, then upload `dist/multisite-shared-content-1.5.2.zip` from **Network Admin → Plugins → Add New → Upload Plugin** and choose **Network Activate**. Activate ACF separately if you want its controls. Open **Network Admin → Shared Content**, enter each participating site ID and its complete type selection, and save. Activation alone does not enroll every site or copy every record.

## Automated testing

The integration suite deliberately creates test records, users and media. Run it only in a separate disposable Docker project, never on a publishing network or a valued local dataset. After configuring a separate project/port and running setup:

```sh
MSC_DISPOSABLE_TESTS=1 sh bin/shared-content.sh test
python3 tools/package-msc.py
```

The test command stops the background worker so deliberate failure/retry tests are deterministic. Restart it afterward with `COMPOSE_FILE=compose.yaml:compose.shared-content.yaml docker compose up -d shared-worker` if you continue using that disposable environment.

Read the [editor guide](../multisite-shared-content/docs/editor.md), [administrator guide](../multisite-shared-content/docs/administrator.md), [developer reference](../multisite-shared-content/docs/developer.md), and [verification report](../VERIFICATION.md) for supported behavior and remaining limits.

## Search and import content

Open **Shared Content → Import Content**. No API key is required. Movies and People use Wikidata/Wikipedia; Shows and Episodes use TVmaze. To import Episodes, search for the Show, connect/import it first, choose a season and select Episodes. The batch saves progress after each Episode and can be resumed. Review field selection and licences, and publish drafts separately. See [the complete import guide](../multisite-shared-content/docs/import-content.md), including how to connect older Episode records without duplicating them.
