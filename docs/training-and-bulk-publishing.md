# Training catalog, bulk publishing and release-post review (2.6.0)

With Docker Desktop running, update the existing Mac checkout:

```sh
export PATH="/Applications/Docker.app/Contents/Resources/bin:$PATH"
git pull --ff-only
sh bin/shared-content.sh setup
sh bin/shared-content.sh training
```

The training command imports a bundled dataset of **250 Movies and 250 TV Shows**. It does not need a source API key or make 500 live API requests. Alternatively, open **Shared Content → Training Catalog**, choose **Import 250 Movies and 250 TV Shows**, and start the resumable import. Pause/resume is supported for the same user/site for 24 hours.

New title records and their participating-site copies are **drafts**, including on sites whose usual default is publish. Movie/TV data is available on the enabled entertainment sites; gaming-only sites retain their content-type restrictions. Existing matching records keep their fields, overrides and publication states. Missing source IDs can be filled; conflicting IDs, ambiguous title/year matches and archived records are reported for review rather than overwritten.

The dataset includes actual source IDs, descriptions, genres, dates and available original-language/country/director/runtime/TV episode-count facts. It does not invent plots, posters, critic scores or cast relationships. Existing image/source-import tools can enrich individual records.

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
