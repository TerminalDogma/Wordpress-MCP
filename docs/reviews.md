# Reviews and aggregate scores

Update your existing checkout with Docker Desktop running:

```sh
export PATH="/Applications/Docker.app/Contents/Resources/bin:$PATH"
git pull --ff-only
sh bin/shared-content.sh setup
```

## Publish a review

Open the relevant site's WordPress dashboard, then **Reviews → Add Review**. Write the article, set its featured image and author, and complete **Review verdict and score**:

- **Reviewed item:** an existing Movie, TV Show, Episode or Video Game on this site.
- **Review score:** 1–10 in half-point increments.
- **Final verdict:** a short concluding assessment.
- **Positives / Negatives:** one point per line.
- **Reviewed platform / edition:** optional context, such as PlayStation 5.

Publish the review using the WordPress editor. Its linked catalog item must also be published and enabled on that site for the review page to be public. Reviews are site-specific editorial posts; their reviewed catalog items retain their shared UUIDs. A review on GameRevolution does not become PlayStation LifeStyle's own review. Both sites can review the same game independently. **Network Admin → Shared Content → Sites and Content Types → Reviews** turns reviews on or off per site while preserving their posts.

Published, unprotected reviews appear at `/reviews/`. Their templates include the article, author/date, featured image, verdict, score and positives/negatives. The archive omits excerpts. The latest published, unprotected review of an item on that site supplies its editorial score. Draft/private/password-protected reviews do not contribute item scores. Unpublishing a review immediately restores the applicable aggregate fallback.

## Score presentation

Catalog items show their editorial score first in **Review Scores**, under the site's name. That score also takes priority on their featured image and catalog cards. Awards apply to editorial scores:

| Score | Recommendation |
| --- | --- |
| 9.5 or 10 | Gold |
| 9 | Silver |
| 8.5 | Copper |

Outside aggregates keep their original scales and receive no editorial recommendation. Movies/TV/Episodes show Rotten Tomatoes and Metacritic; games show Metacritic and OpenCritic. Unavailable/unrated values stay absent. An explicit empty local score override hides that provider on that site. Legacy OMDb ratings already saved in `additional_metadata` appear without reimporting unless an explicit score field overrides them.

## Fetch outside scores

Configure game credentials under **Network Admin → Shared Content → Review Score Sources**:

- Movies/TV use the existing OMDb key.
- Game Metacritic scores use a RAWG API key.
- OpenCritic uses a key subscribed to the official OpenCritic API on RapidAPI.

Choose accounts/plans suitable for your commercial use. Provider credentials are not bundled with the repository. Blank password fields retain saved keys; remove checkboxes clear them. Server-managed `MSC_RAWG_API_KEY` / `MSC_OPENCRITIC_API_KEY` constants or `RAWG_API_KEY` / `OPENCRITIC_API_KEY` environment variables take precedence when configured in the PHP/container environment. Adding values only to `.env` does not pass new variables into an existing container; use the network settings for this installation.

Open the catalog item's **ACF Shared Fields** or **All fields and publishing** editor. For Movies/TV, imported IMDb/Wikidata identities are reused; otherwise enter **IMDb title ID for aggregate scores**. For games, enter **RAWG game ID or slug** and **OpenCritic game ID**. A RAWG `/games/elden-ring` page uses `elden-ring`; an OpenCritic `/game/1234/...` page uses `1234`.

Choose **Fetch aggregate scores**, check the returned title matches the correct release/edition, select the scores you want, then choose **Use selected scores**. This fills the form; save or publish with the existing global/local controls. No catalog records are changed by preview alone, and unsaved form content is retained. Provider page URLs remain editable. Rotten Tomatoes requires the corresponding title link and its brand attribution; enter the correct title URL when Wikidata has no identifier. Game Metacritic values are supplied by RAWG; OpenCritic uses its top-critic average, rather than percent-recommended or user ratings. Confirm the relevant edition/platform when a provider aggregates differently from your reviewed edition.

Requests run only in the authenticated editor, use six-hour caches and honor provider cooldowns. Public pages render stored values without external API requests. Missing keys, unrated titles or unavailable services leave other scores intact. Provider links appear in the score section and data-provider credits are collected in the existing footer.

## Source references

- [GameRevolution review reference](https://www.gamerevolution.com/review/934630-hogwarts-legacy-review-ps5-pc-xbox-ps4-switch)
- [RAWG API access](https://rawg.io/apidocs)
- [OpenCritic API](https://rapidapi.com/opencritic-opencritic-default/api/opencritic-api)
- [OpenCritic API contact](https://opencritic.com/contact)
- [Rotten Tomatoes data licensing and score display](https://www.rottentomatoes.com/help_desk/licensing)

The RAWG documentation site timed out during implementation. Its published API description confirms Metacritic support, but historical free-tier limits are not treated as current commercial terms. Check the provider's current plan for your network. Live provider access and video/game identities require your credentials; automated checks use deterministic API fixtures.
