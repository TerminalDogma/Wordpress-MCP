# Four network sites, Video Games and release calendars

## Update your Mac

Open Docker Desktop. In Terminal, from your existing repository checkout:

```sh
export PATH="/Applications/Docker.app/Contents/Resources/bin:$PATH"
docker version
git pull --ff-only
sh bin/shared-content.sh setup
```

Preserve your `.env` and Docker volumes. Setup adds Site D, applies the four brand profiles once, creates calendar pages, and adds fictional presentation articles and catalog examples. It preserves existing records, post IDs, saved edits and later changes to the content-type matrix. The existing one-time Site A reconciliation retains its recovery snapshots and completion marker. New catalogs participate only on sites where their types are enabled.

Use your configured port if it differs from 8080:

| Site | Homepage | Enabled shared content |
| --- | --- | --- |
| A · ComingSoon | http://localhost:8080/site-a/ | Movies, TV Shows, People, child Episodes |
| B · SuperHeroHype | http://localhost:8080/site-b/ | Movies, TV Shows, child Episodes |
| C · GameRevolution | http://localhost:8080/site-c/ | Video Games, all platforms |
| D · PlayStation LifeStyle | http://localhost:8080/site-d/ | Video Games, Sony consoles and handhelds |

Append `release-calendar/` to each homepage URL for its calendar, or use its Release Calendar navigation link. Site D includes PlayStation 1–5, PSP, PS Vita and PlayStation VR/VR2. Mixed-platform games can appear on C and D, with only Sony platform labels and Sony release dates displayed on D. PC-only games stay off D. Movie/TV/Person records remain stored when their site participation is turned off.

## Turn types on or off

Go to **My Sites → Network Admin → Shared Content → Sites and Content Types**. Check or uncheck types for each site, select the game platform policy, and save. The local Shared Content menu also links to this matrix. The background worker updates native projections; disabled content disappears from public catalog rendering immediately. Re-enabling retains shared records, local overrides and publication preferences. Episodes remain under TV Shows on A/B, with the Show/season browser and existing public season links.

The brand profiles are applied once. Re-running setup does not undo later matrix choices. Network news posts are local fictional presentation content, separate from the shared catalog.

## Connect IGDB

IGDB was chosen for title search, cover images, game companies, platform categories and separate regional release dates. Follow [IGDB account creation](https://api-docs.igdb.com/#account-creation) to create a **confidential Twitch application** and obtain its Client ID and Client Secret. Enter these privately at **Network Admin → Shared Content → IGDB API**. Saved secrets are never printed in the form, URLs or browser scripts. Alternatively configure `IGDB_CLIENT_ID` and `IGDB_CLIENT_SECRET` in your private `.env`, then run setup to recreate containers with the environment values. Do not commit credentials.

IGDB documents free commercial use through its [partner program](https://api-docs.igdb.com/#partnership). Arrange the appropriate agreement for commercial publication. Imported data retains provenance; published IGDB data receives a visible footer credit. The fictional demo works without API credentials. Live IGDB access has not been verified with your account because no Twitch credentials were provided.

On C or D, open **Shared Content → Import Content**, choose Video Games, search a title, and open its preview. Use **Select all import data** or choose individual fields and the cover image. Repeated IGDB identities update the existing shared record. Imports retain local overrides and start as drafts unless your configured schema says otherwise. In ACF, use **Save and publish on this site**, or **Save and publish globally** as a network administrator. The full editor also provides per-site publication status controls.

Genres, platforms, developers, publishers and game modes are reusable categories. Supported fields use ACF; the full editor includes an editable platform/region/date table. Exact dates use `YYYY-MM-DD`. Month/year/quarter/TBD windows remain descriptive labels and do not invent a calendar day.

## Calendar behavior

The calendar reads effective published catalog data: Movie theatrical/digital/streaming/disc dates, TV premieres/finales, Episode air dates and Video Game platform/regional release dates. It respects enabled types, local date overrides, publication state and Sony restrictions. Draft, private, password-protected, suppressed and archived records are excluded; People birthdays are excluded. Changing a global field propagates to participating sites; intentional local overrides remain authoritative. Month navigation and filters update without a page reload, with normal links and forms as a fallback.

If an authored page already occupies `release-calendar`, setup creates a unique page instead and the navigation uses that page automatically. Use `[msc_release_calendar]` on another page to embed the same calendar.

## Stop and resume

```sh
sh bin/shared-content.sh stop
# Later:
sh bin/shared-content.sh setup
```

Stopping preserves content in Docker volumes. Do not use `docker compose down --volumes` for a network whose data you want to keep.
