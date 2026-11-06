# Test the four-site network on your Mac

Follow [the 2.0 setup and update guide](update-2.0.md) for full instructions, site URLs, content types, IGDB authentication, publishing, calendars and saving Docker data. First-time users can install Docker and clone the repository using [the Mac multisite guide](mac-multisite.md).

From your existing checkout, with Docker Desktop running:

```sh
export PATH="/Applications/Docker.app/Contents/Resources/bin:$PATH"
git pull --ff-only
sh bin/shared-content.sh setup
```

Preserve your `.env` and Docker volumes. Setup adds the fourth site, configures the four network brands once, and seeds fictional news and catalog examples without replacing saved content. It also creates one Release Calendar page per site. Later changes to the content-type matrix are retained on subsequent setup runs.

- A: ComingSoon — Movies, TV Shows, People; Episodes under TV Shows.
- B: SuperHeroHype — Movies, TV Shows; Episodes under TV Shows.
- C: GameRevolution — Video Games on all platforms.
- D: PlayStation LifeStyle — Video Games on Sony consoles and handhelds.

Open `http://localhost:8080/site-a/`, `/site-b/`, `/site-c/`, `/site-d/` using your configured port. Each site's navigation includes its catalog and Release Calendar. Append `wp-admin/` for its editor. Use the existing administrator credentials from your private `.env`.

Configure types at **Network Admin → Shared Content → Sites and Content Types** and private API credentials at **IGDB API**. Imports and edits default to global scope; local overrides remain explicit. New catalog entries follow the type's publication default, normally draft. Publish each site's projection through **All fields and publishing → Switch to local overrides**.

```sh
sh bin/shared-content.sh queue  # process queued synchronization
sh bin/shared-content.sh demo   # add missing fictional examples, retaining edits
sh bin/shared-content.sh stop   # stop containers while preserving volumes
```

For previous features, see the [1.8 catch-up/recovery guide](update-1.8.md), [ACF guide](../multisite-shared-content/docs/acf.md), [field reference](../multisite-shared-content/docs/content-fields.md), and [verification](../VERIFICATION.md). Build installable plugin, theme and source ZIPs with `python3 tools/package-msc.py`.
