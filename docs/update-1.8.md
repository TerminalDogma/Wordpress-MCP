# Update to 1.8

With Docker Desktop running, from the Mac checkout:

```sh
export PATH="/Applications/Docker.app/Contents/Resources/bin:$PATH"
git pull --ff-only
sh bin/shared-content.sh setup
```

## Existing Site A data

Setup runs a one-time catch-up using `/site-a/` in the current network. Site A's shared-field overrides become global values. Conflicting field overrides on other participating sites are reset to those values, as requested. The promoted Site A fields resume inheritance. Text, dates, categories, images and Person relationships follow the same rules. Publication choices remain in effect.

Before each record changes, the updater stores its previous global record and participating sites' overrides in a network option named `msc_site_a_reconcile_180_backup_<record ID>`. Global revision history is retained. Progress is recorded after each record, so interrupted setup can be rerun. The `msc_site_a_reconcile_180` completion marker prevents future setup runs from repeating this policy on subsequent local edits.

Setup then drains synchronization jobs and starts the worker. It fails visibly if reconciliation or delivery needs recovery. Catch-up runs against the existing Mac database when you execute setup; development test data is not copied to that database.

## Editing and delivery

Shared Content and ACF forms default to global editing when permitted. Standard shared-content Edit links open the global form. Use **Switch to local overrides** or **Edit locally (WordPress)** for site-specific work. Local-only editors retain their permitted local default.

Global saves in both editors attempt immediate delivery to a small network, independently of WP-Cron or the worker. Durable jobs remain for retries and larger networks. **Site synchronization** shows received revisions, errors, overrides, participation and publication. **Sync this site** retries directly. Future global saves preserve intentional local overrides; the catch-up above is the one-time exception requested for this update.

## People and import selection

Imports check shared Person records, including drafts. Verified identities reuse one Person across roles. A matching name and exact birthday can connect compatible provider identities; possible name-only matches require an explicit reuse or distinct-person choice. Conflicting source IDs keep identified namesakes separate. The importer rechecks under a network lock before creating People. Reused records retain curated data and accumulate professions.

**Shared Content → Duplicate People** lists existing possible name/identity matches and links to their records. Existing drafts and links are preserved for review rather than silently consolidated. Select the existing Person in future previews when appropriate.

The **All** checkbox above an import table selects available fields, credit-role fields and the optional image. It supports clearing and partial selection. Required titles remain selected for new records; disabled options and explicit distinct-person confirmations are excluded. Both Import Content and legacy OMDb previews have this control.

## Public seasons and credits

TV Show pages have clickable **Season 1**, **Season 7**, etc. links, showing only the chosen season's published Episodes. The selected season is reflected in the URL and marked with `aria-current="page"`. Season zero and missing seasons remain distinct; draft-only seasons are omitted. Season discovery no longer has the previous 500-Episode ceiling. A season with more than 50 Episodes has next/previous Episode pages.

Public source and image credits are collected in one **Sources and image credits** section in the footer. Source links, authors, licences and adaptation details remain associated with displayed content and assets. Import previews retain source/licence details for review before saving.
