# Troubleshooting

| Symptom | Diagnosis and recovery |
|---|---|
| CPT absent from dashboard | Verify network activation, current-site participation, `msc_types` option, and role capabilities. New sites are not automatically enrolled. |
| Record missing locally | Inspect its map and queue totals; verify enabled type and unsuppressed map. Run the main network site's queue. A removed local post remains suppressed until its local status changes. |
| Global update not visible everywhere | Check global version versus mapping versions. Inspect pending/failed jobs. A field override intentionally prevents inherited display changes. |
| Failed synchronization | Read recent network queue error; fix database permissions, missing schema, callbacks, or media conditions. Retry failed jobs. Do not delete authoritative records to repair a projection. |
| Conflict on save | Reload to get fresh global/local version. Compare and reapply your change. Native REST clients must send `msc_expected_version`; MSC routes use `version` or `local_version`. |
| Blank/zero value persists | This may be an intentional explicit override. Uncheck Override and save to inherit instead. |
| Image missing | Verify owner site and attachment IDs, owner uploads, image MIME type, and owner not deleted/archived. Use the fallback filter. A local blank override intentionally hides imagery. |
| Rich content media wrong on another site | Embedded local IDs inside third-party blocks are not automatically migrated. Use site-aware custom blocks or explicit media APIs. Standard HTML URLs and block comments are preserved. |
| Upcoming episode wrong | Check indexed `release_date`, parent UUID, publication, and queue completion. Upcoming queries compare calendar dates, not UTC air-time instants. |
| Site published unexpectedly | Inspect configured default_status, local preference, global lifecycle, and native editor action. Defaults ship as draft. Review capability assignments. |
| Noindex absent | Confirm the schema's network noindex flag. Inspect both robots meta and X-Robots-Tag, page caching, and SEO plugin hooks. Purge stale external page caches after policy changes. |
| Sitemap contains entries | Check core post-type/taxonomy exclusions and third-party provider configuration. Third-party taxonomy sitemaps need explicit verification. |
| Lock error | A worker/edit may already hold a lock, or the DB denies GET_LOCK. Inspect MySQL connections and provider support. Do not remove locking safeguards. |
| Plugin cannot activate | Requires Multisite network activation, supported PHP, and permission to create InnoDB tables. Check the DB error log. |
| Development plugin not found | Source directories need mode 755 and files 644 for container uid 33. Only `.dev` may contain generated local secrets. |
| Local development subsite 404 | Rerun setup to install the development multisite rewrite rules. Native posts use standard theme templates. |

Useful commands:

```sh
wp msc status
wp msc queue --limit=100
wp msc retry --user=<network-admin>
```

For recovery, retain database and uploads backups. Restore global data through revisions when possible. Local post deletion never implies global deletion. Re-enrollment queues a catch-up from authoritative records and preserves overrides and local removal preferences.

Do not fix validation, TLS, or capability failures by disabling checks. Report the UUID, site ID, global/local revision, sanitized queue error, WordPress/PHP versions, and plugin version. Never include passwords, Application Passwords, proxy credentials, or an environment dump.
