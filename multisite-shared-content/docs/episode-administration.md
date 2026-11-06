# Manage Episodes by TV Show

Version 1.6 makes the existing parent TV Show relationship visible throughout the backend. No title-based matching or bulk renaming is required.

## Default Episodes browser

Version 1.7 makes **Episodes → By TV Show** the default entry point. It displays one row per Show with season and Episode counts, rather than a row for every Episode. Search by Show title; results paginate in groups of 25 Shows.

Select **View seasons** to see that Show's season batches, then **Manage Episodes** to open only that season in the native post list. Normal WordPress editing, bulk actions, publication and inheritance controls are available there. Links above the list return to the Show's seasons or all TV Shows. The Show page also offers **View Episodes** for the entire Show and **Add Episode** with the parent preselected when permitted.

**All Episodes** remains a secondary menu option and a link from the Show browser. Episodes with no parent appear together under **No TV Show**. Missing seasons appear as **Season not set**, separately from season zero. Only Shows with Episodes on the current site appear in the browser; counts include drafts, exclude trash and reflect local parent and season overrides. The global Shared Content catalog retains its separate global scope.

## Browse a Show's Episodes

- In the native **TV Shows** list, use the **Episodes** action beneath a Show's title.
- In a Show's native editor, ACF editor or Shared Content editor, use **View Episodes**.
- Use **Add Episode** to create a global Episode with that Show already selected. The new Episode form identifies its parent at the top.

The native Show editor and local editing views open the current site's Episode list. Global editing views open the global Shared Content catalog.

## Native Episodes list

The **Episodes** menu displays rows such as:

```text
The Signal — S01E02 — Episode title
```

Columns show **TV Show**, **Season**, **Episode** and **Air date**. The default ordering is TV Show, season, then episode number. Numbers sort numerically: episode 2 precedes episode 10. Column headers support sorting, and normal WordPress search, publication filters, bulk actions and inheritance filters remain available.

Use the **All TV Shows** and **All seasons** selectors to narrow the list. Selecting **No TV Show** finds Episodes with no assigned parent. **Season not set** finds missing season values; it is distinct from season zero. A Show-specific list includes an Add Episode link; its normal **Add New** action also carries that Show into the new global-record form.

These lists include drafts and other statuses according to normal WordPress administration permissions. They use the effective parent and numbering for the current site, including local overrides. Screen Options can hide columns; the primary row title still identifies the Show and episode number.

## Shared Content → Episodes

The global catalog has Show/season selectors, a search box for Episode or Show titles, and numbered pagination. It displays global relationships and includes active records that have not been published on a site. Filters persist when moving between pages.

A local parent override can therefore move an Episode to another Show in one site's native list while the global catalog retains its global parent. The catalog links to the current site's native list when you want to work on local publishing or relationships.

## Edit or assign a parent

Episode editors identify the current parent Show and numbering above the fields, with links to **Manage TV Show** and **View Episodes**. Global editing explicitly labels the global parent.

To assign an unlinked Episode, open **Edit supported fields with ACF** or **Manage fields**, choose the appropriate editing scope and select **Parent Show**. Save globally to update inheriting sites, or use a permitted local override for the current site. Resetting that override resumes the global relationship.

The Show/number prefix is a display label in the native Episode list. Stored titles, URLs, UUIDs and frontend titles retain their existing values. The canonical relationship remains the global Show UUID, with native post IDs resolved independently on each site.

The Show selector displays up to 500 active Shows and always includes a valid selected Show. For a larger library, find the Show in the native TV Shows list and use its **Episodes** action to open a scoped list directly.

## Update on the Mac

With Docker Desktop running, from your repository checkout:

```sh
git pull --ff-only
sh bin/shared-content.sh setup
```

Existing relationships are picked up automatically. If a recently changed parent has not yet appeared in another site's native list, let the synchronization worker catch up or run `sh bin/shared-content.sh queue`.
