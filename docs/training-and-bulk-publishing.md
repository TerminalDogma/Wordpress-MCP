# Training catalog, bulk publishing and release-post review (2.7.0)

With Docker Desktop running, update the existing Mac checkout:

```sh
export PATH="/Applications/Docker.app/Contents/Resources/bin:$PATH"
git pull --ff-only
sh bin/shared-content.sh setup
sh bin/shared-content.sh training
```

The command rebuilds **250 Movies and 250 TV Shows** using their exact source IDs. It downloads descriptions, genres, dates, linked cast and crew, available company records, and real artwork from Wikidata/Wikipedia, TVmaze and the configured OMDb key. Internet access is required. The bundled JSON selects the titles; its basic facts are no longer imported as stand-alone entries.

Alternatively, open **Shared Content → Training Catalog**, choose **Rebuild complete training catalog**, and start the import. Browser requests process one title at a time, pause/resume is supported for 24 hours, and source throttling delays the current title rather than skipping it. The CLI prints a token; resume an interrupted run with `sh bin/shared-content.sh training --resume=TOKEN`.

Starting a rebuild archives only untouched version-one training stubs. Complete replacements reuse their global UUIDs and become **drafts on participating sites**, even if a site's usual default is publish. Your manually created entries and training entries you edited are retained, with their fields, local overrides and publication states. Ambiguous duplicates and conflicting IDs are reported for review.

A replacement must have a substantial description, genres, linked cast, linked crew, a downloaded poster and a featured-image reference. Failed artwork or missing required metadata leaves the old stub archived and creates no partial replacement. Repeating the command retries failed titles and keeps completed entries. The CLI exits with an error if any titles remain incomplete, rather than claiming all 500 succeeded.

Artwork becomes ordinary WordPress attachments with portable ownership and footer credits. A separate TV background is used when available; otherwise the real source poster fills both image fields. Images are never generated or replaced by an unrelated placeholder. Credits reuse existing unpublished People, merge professional roles, and appear as names until their pages are published. Metadata and image credits retain their actual sources; the bundled CC0 facts are distinct from downloaded article prose and artwork.

## What “popular” means

This is a broad all-time training sample using **Wikipedia-language coverage as a popularity proxy**, not an official worldwide top-250 ranking. The candidate pools include live-action and animated films, television series, animated shows and miniseries. Source facts use Wikidata's CC0 licence. The exact method and source properties are recorded in `multisite-shared-content/data/README.md`; the frozen JSON contains 250 distinct identities of each type.

## Publish en masse from a post list

1. Open **Movies** or **TV Shows** in the ordinary WordPress post list. Choose **Training catalog** in the filter dropdown to find the imported titles. Filter to **Drafts** to review only new, unpublished entries.
2. To select all 250 on one screen, open **Screen Options** and set the number of items per page to **250**. The top checkbox selects that page's rows.
3. Choose **Publish selected globally** or **Publish selected on this site** in **Bulk actions**, then **Apply**.
4. Review the selection count, choose **Start / resume publishing**, and let the progress screen finish. It processes ten records per request and can pause/resume.

Global publication requires network-administrator/shared-publication permissions. It reaches enabled participating sites while preserving local field overrides and locally removed copies. Local publication affects the current site. Permissions are checked before a job starts. Records that change or lose permission after selection are skipped with a reason. Return to the post list keeps its filters.

Regular WordPress Posts, Pages and Reviews also have the local bulk-publish action. The global action is restricted to shared custom post types. Scheduled regular Posts selected for immediate publication are published now.

## Upcoming release guides must be reviewed

Open **Shared Content → Release Posts** (the existing admin URL remains `admin.php?page=msc-release-pages`). Generate 1–12 monthly guides, optionally scoped by type/service. These are regular **Posts**, created as **Pending Review** and hidden from logged-out readers.

Use **Review pending release posts**, or the normal **Posts** list's **Release guides** filter. Edit the title/introduction, preview the listing, and publish normally or through the local bulk action. An authorized publisher must publish the post; generation itself cannot publish it. Once published, the guides also appear in the homepage’s Release guides section. Their catalog listings remain live and continue to use published catalog dates.

The upgrade moves older plugin-generated release Pages to regular Posts without deleting their text or changing their IDs. Previously published/scheduled guides become Pending Review; private, draft and trashed states are retained. Check and republish the guides you want live. Their permalink format may change when moved from Pages to Posts.

The rolling **Upcoming Releases** calendar hub stays a Page. Ordinary manually authored Pages and unrelated news posts are not converted. Repeating a generation reuses an existing guide and preserves editorial copy and publication status.
