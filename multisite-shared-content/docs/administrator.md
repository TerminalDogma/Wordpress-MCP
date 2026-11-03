# Network administrator guide

## Install

Use WordPress Multisite, PHP 8.2+, and MySQL/MariaDB with InnoDB and advisory `GET_LOCK` support. WordPress 6.9+ is declared; this build is tested on the current official image, WordPress 7.1.4 and PHP 8.3. Minimum-version combinations have not been separately tested. Transactions require posts, metadata, and MSC tables on one transactional database connection. Sharded database drop-ins need a custom adapter.

Back up database and uploads. Upload the ZIP, choose **Network Activate**, and open **Network Admin → Shared Content**. Activation initializes six per-network tables. Migration reruns are safe; single-site activation is rejected.

## Participation and schemas

Enter a site ID and check its **complete** shared-type selection. Enabling or disabling queues a catch-up job in 100-record pages. Disabling unpublishes local representations and preserves data. New subsites start with no shared types and require enrollment.

The advanced network settings form exposes the complete JSON schema. Copy a starter type to define another. CPT keys begin `msc_` and are at most 20 characters. Schema keys include `label`, `slug`, `archive`, `noindex` (default true), `default_status` (draft/publish/private), and `fields`. Every type requires `title`.

Fields accept `type`, `label`, `property`, `override`, `indexed`, `target`, `options`, `min`, and `max`. Native properties are `post_title`, `post_content`, `post_excerpt`, and `post_name`. Do not map two fields to the same property. Others materialize under `msc_<field>` post meta. Use `override:false` to lock a field and `indexed:true` for scalar query fields. Lists/objects are not individually indexed.

Populated types/fields cannot be silently removed. Renaming, changing field types, modifying locked-field policy, and converting existing data require a reviewed migration. After additive schema changes, resubmit participation on affected sites to rebuild materializations. Participation sets a rewrite-refresh flag, consumed on the site's next request.

## Capabilities

| Capability | Authority |
|---|---|
| `manage_network_options` | Participation, queue recovery, global lifecycle |
| `msc_manage_schemas` | Schema configuration |
| `msc_create_global` | Create global records from enabled sites |
| `msc_edit_global` | Update/restore global data and read revisions |
| `msc_override_local` | Override/reset current-site fields |
| `msc_publish_local` | Change local publication without field-edit authority through REST or the native editor |
| `msc_trash_global` | Global lifecycle, additionally restricted to network administrators |

Site administrators receive all MSC capabilities; editors receive local override/publishing only. Review those defaults before enrollment. Native editing still requires WordPress `edit_posts`/`edit_post`. Subscribers receive no mutation capability. A site's capabilities do not grant membership on another website. Use normal role tools or `wp cap add/remove <role> <cap> --url=<site>` for delegation. Networks with a central global team should remove global creation/editing capabilities from site administrators and grant them to a dedicated role. Per-CPT capability names are not supported in this release; participation restricts the current site's type scope.

## Queue operation

Run once a minute from the main site's WordPress directory:

```sh
wp msc queue --limit=100
```

Use system cron or your process manager. Traffic-driven WP-Cron is a fallback and cannot guarantee prompt propagation. Each worker has a 20-second budget; an advisory lock serializes workers. Failed jobs retry with exponential backoff, initially 60 seconds and capped at an hour. After five attempts a job becomes failed. Network Admin shows totals and recent errors. Fix the cause, then choose Retry failed jobs or use `wp msc retry --user=<network-admin>`.

Global saves transactionally write record, audit revision, global lookup values, and fan-out job. Fan-out enumerates 100 sites at a time. Sync is idempotent; generations prevent a worker from acknowledging newer work accidentally. A zero queue is a point-in-time observation. Confirm each mapping's `version` equals the global revision and that no failed jobs remain before claiming network-wide propagation.

## Media and SEO

Media references contain owner site ID and attachment ID. They avoid blind ID copying and duplicated uploads. Owners retain files. Missing/deleted assets produce empty output or the `msc_media_fallback_url` filter. Back up every owner's uploads. Shared media is intended for public assets. Native thumbnail HTML works; themes requiring local attachment IDs should use the MSC media API.

Shared singles, archives, and taxonomies attached to a noindex shared type default to noindex. Core XML sitemaps exclude their types/taxonomies. Core robots plus an HTTP header enforce policy. Yoast and Rank Math robots/title/description and post-type sitemap hooks are included but not third-party integration-certified. Verify your actual SEO configuration, particularly taxonomy sitemaps. A shared taxonomy attached to regular content is also excluded by the core policy. Do not substitute robots.txt disallow for noindex.

## Maintenance and removal

Review cron health, failed jobs, revision/table sizes, uploads availability, and backups. Global revisions are retained indefinitely. Deactivation stops the current network's scheduled runner and preserves all data. Uninstall is non-destructive. Permanent purge is a separate backed-up DBA operation; no accidental mass-delete UI exists.

Use `sh bin/shared-content.sh setup` for the repository Mac environment. Its optional local worker polls every five seconds. The disposable integration suite runs with `MSC_DISPOSABLE_TESTS=1 sh bin/shared-content.sh test`; it creates fixtures and stops that worker. Build archives with `python3 tools/package-msc.py`. See the repository quickstart.
