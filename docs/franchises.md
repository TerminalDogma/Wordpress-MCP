# Franchise pages

Update the existing installation with `git pull --ff-only` and `sh bin/shared-content.sh setup`. **Franchises** is enabled on Sites A and B, which already support movies and TV. It can be turned off or enabled elsewhere through **Network Admin → Shared Content → Sites and Content Types**. Later setup runs respect an administrator's choice to disable it.

1. Open **Shared Content**, select **Franchises**, and create a record in the default global editing mode.
2. Add a title, description, franchise poster and featured image. The featured image forms the wide hero background. Optionally enter a six-digit accent color such as `#b90d24`.
3. Add multiple **Movies** and **TV Shows** using the existing ACF relationship controls. Draft members can be assigned before publication.
4. Save, preview, and use **Publish globally** to publish the collection on participating sites, or the local publish action for one site. Member records retain their own publication states.

Public pages combine movies and TV shows in release-date order, place undated titles last, and show type/year with each poster. Draft, private, locally removed, disabled and password-protected members are omitted. The collection uses the site's brand styling plus its own artwork/accent, and has no card excerpts. Published movie/TV pages automatically link to the published franchises that contain them. The archive is available through **Franchises** in the catalog navigation.

Membership is stored as shared record UUIDs, so a collection can display on multiple sites without copying site-specific post IDs. Local overrides and existing global/local publication controls apply to Franchise records in the same way as other shared types.
