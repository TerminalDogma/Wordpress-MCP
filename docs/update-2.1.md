# Linked catalog records and calendar views

With Docker Desktop running, update from the existing Mac checkout:

```sh
export PATH="/Applications/Docker.app/Contents/Resources/bin:$PATH"
git pull --ff-only
sh bin/shared-content.sh setup
```

Keep the existing `.env` and Docker volumes. The additive upgrade preserves saved fields, imported category names, post IDs and local overrides. Setup enables the relevant new types once; subsequent setup runs retain changes made in **Network Admin → Shared Content → Sites and Content Types**.

## Company, platform and service pages

The new shared types are Production Companies, Distributors, Game Developers, Game Publishers, Game Consoles and Platforms, Streaming Services, and TV Networks. Film/TV sites receive the applicable company/service/network types; game sites receive developers, publishers and platforms. Site D applies its Sony console and handheld policy to platform pages and linked games as well.

Create records through the relevant WordPress menu or **Shared Content → Create global record**. Add the description, logo/product image, website and other profile details. Console/platform records also offer platform type, manufacturer, launch date and optional IGDB platform ID. Image fields support the same library and embedded image-search controls. Publish through ACF's existing site/global buttons or the full editor.

On a Movie, TV Show, Episode or Video Game, ACF's **Linked …** fields select these records. Stored category/text fields remain available for existing imports. A matching published record automatically links an existing name in the sidebar; ambiguous names remain plain text until an editor chooses an explicit relationship. Explicit relationships continue to work when a record is renamed. An empty local relationship override removes its links on that site while preserving legacy text.

Linked People and companies display their names even while their profile pages are unpublished or unavailable on that site. Publishing the profile automatically turns the name into a link without importing again or resaving the referring title; unpublishing returns it to plain text. Cast and crew sections follow the same rule. Only the credited name is shown from unpublished profiles. The details sidebar also includes published parent Shows. Links follow the current site's effective fields and publication state. Draft, private, password-protected, removed, archived and disabled destinations do not receive public links. Each new entity page has a profile template and related published titles, bounded to 100 candidates as with the existing filmography. Existing labels are not converted into automatically published entity records.

## Trailers and sidebar cast

Movies, TV Shows and Video Games have a **Trailers** field in ACF and the full shared editor. Choose **Search YouTube**, enter a title, preview the thumbnail results, then choose **Add trailer**. Search stays inside the editor and reuses the Serper key configured under **Network Admin → Shared Content → Image Search**. It finds YouTube videos through Google video search, rather than the YouTube Data API. Searches use the existing provider account credits and caches.

You can also paste YouTube or public Vimeo video URLs, one per line, up to twelve. Change line order to reorder the carousel; remove a line to remove a trailer. Save or publish using the existing global/local controls. Global trailer values inherit across sites; local overrides and resets work like other supported fields. Public pages show a compact row of three trailer embeds on desktop, two on tablets and one on phones. Previous/Next controls appear when more trailers exist than fit in the row. Changing rows or reducing the screen width unloads players that become hidden. Empty fields hide the area; without JavaScript, watch links remain available. Live playback depends on the video owner permitting embedding.

Actor sidebars show the first five saved credits and a **View full cast** link. ACF relationship ordering can place preferred actors first; the current movie source does not guarantee billing or prominence order. This affects display only and does not truncate imports or saved relationships. Adjacent details lists now have clear spacing and long names wrap within the sidebar.

## Episode sections

A TV Show without accessible published Episodes has no Episode heading, message or empty section. Draft-only Episodes do not display a public guide. Publishing an Episode linked to the Show makes its season guide available. Existing AJAX season navigation remains available when Episodes exist.

## Month and week calendars

Each site's Release Calendar has **Month** and **Week** links. Week view uses the site's WordPress start-of-week setting, includes all seven days and can span month/year boundaries. Choose a date under **Week containing**, then **Show releases**. Previous/Next moves one week in that view. View changes, filters and navigation update through AJAX and support Back/Forward; ordinary links/forms also work with JavaScript disabled.

**Daily display** offers the first 3, 5, 10 or all releases. The default is 3. A busy day shows **Show N more** to expand its remaining entries in place and **Show fewer** to collapse them. This also works without JavaScript. The view and display choice remain in the URL when navigating and can be bookmarked.

Calendars continue to use effective published dates and the site's type/platform rules. Published console/platform launch dates now also appear. Company foundation dates and Person birthdays do not create release events. Undated release windows remain on the title page.
