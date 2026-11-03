# Editor guide

## Create a show

1. Open **Shared Content → TV Shows** in your site's dashboard.
2. Choose **Create global record**. If absent, ask your administrator for Create Global permission.
3. The yellow GLOBAL EDITING banner means participating sites inherit the information you save.
4. Enter title, description, synopsis, dates, and other known fields. Leave optional unknown values empty.
5. Save. Your local post is created; other participating sites are queued. New posts default to draft.
6. Switch to local overrides to set the publication status for your website.

You can also use the ordinary TV Shows → Add New editor when authorized. Its first full save creates a global record. Subsequent native editor saves create **local** changes.

## Add episodes

Open a show's Manage fields screen. Under **Episodes by season**, choose **Add episode to this show**. The parent's permanent global ID is prefilled. Enter season number, episode number, release date, air time, and IANA time zone (such as `America/New_York`), then save. Episodes are grouped and sorted numerically. Change numbering through each episode's fields. Seasons are numbers, not separate posts. The panel is bounded to 500 episodes; use the normal episode list and APIs for larger sets.

## Change information everywhere

Open the record in Shared Content, choose **Switch to global editing**, and check the yellow GLOBAL EDITING banner. Save your changes. The saved revision becomes authoritative; other sites update through the queue. Local overrides continue to win. You do not need to visit a master website.

A conflict notice means another editor saved first. Reload, review their changes, and enter your changes again. Do not repeatedly submit an old form.

## Override or reset one field

1. Use the blue LOCAL EDITING screen.
2. Expand **Global value** to see the shared value and current inheritance status.
3. Check **Override on this site**, enter your value, and save.
4. To reset, uncheck Override and save. This restores the newest global value.

A checked override may be intentionally blank, zero, false, or an empty list. A Network locked field cannot be overridden. Native Gutenberg title, body, excerpt, and slug changes create local overrides. Use Manage fields for custom data, media, and resets. Rich text accepts HTML and Gutenberg comment markup.

Use **Choose media from this site** to select or upload an asset in the native WordPress Media Library. The picker records its owning site automatically. For references to another site, the advanced text area accepts JSON objects with both `site_id` and `attachment_id`, for example `{"site_id":2,"attachment_id":45}`. A local media override may reference a different attachment, or `[]` for no image. The Media Library item's URL contains its attachment ID. An inherited or overridden asset also has a View current asset link.

## Publish locally

Local Editing offers draft, publish, private, unpublished, or removed. Unpublished retains a native draft. Removed deletes the local post but preserves its overrides and global record. Selecting another status recreates the local post. Global trash/archive forces all local posts to draft after synchronization, retaining publication preferences for restoration.

The normal post list supports search, bulk status changes, and an inheritance filter. Its Shared content box shows UUID, override count, and local revision. Native trash is local unpublishing; it never deletes global data.

## Recover mistakes

Reset a mistaken override, or use native WordPress revisions for supported post properties. Authorized global editors can use Global revision history to restore old data as a new global revision. Lifecycle restoration is a separate network administrator action. For sync issues, send your administrator the UUID and affected site ID.

Each theme controls layout. The same show can appear as a schedule, card, or full page on different websites. Noindex is not access control: use draft/private for nonpublic content.

## ACF editing

When ACF is active, existing records have an **Edit supported fields with ACF** button. Use its explicit global/local mode and per-field Override controls. See [the ACF guide](acf.md). Titles, native body content, media and complex data remain in the full editor.
