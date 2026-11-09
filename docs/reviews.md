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

Published, unprotected reviews appear at `/reviews/`. Their templates include the article, author/date, featured image, verdict, score and positives/negatives. The archive omits excerpts. The latest published, unprotected review of an item on that site supplies its editorial score. Draft/private/password-protected reviews do not contribute item scores. Unpublishing a review removes its image badges; aggregate scores remain in the item-page section.

## Score presentation

Catalog items show their editorial score first in **Review Scores**, under the site's name. Only that site’s published editorial score appears on featured images, posters and catalog cards. Items without an eligible editorial review have no image/card score badge. Aggregate scores are limited to the item page’s Review Scores section. Awards apply to editorial scores:

| Score | Recommendation |
| --- | --- |
| 9.5 or 10 | Gold |
| 9 | Silver |
| 8.5 | Copper |

Outside aggregates keep their original scales and receive no editorial recommendation. Movies/TV/Episodes show Rotten Tomatoes and Metacritic; games show Metacritic and OpenCritic. Unavailable/unrated values stay absent. An explicit empty local score override hides that provider on that site. Legacy OMDb ratings already saved in `additional_metadata` appear without reimporting unless an explicit score field overrides them.

## Fetch outside scores

Game Metacritic and OpenCritic values come from **Wikipedia without API keys or paid subscriptions**. Movie/TV/Episode imports keep the existing **OMDb** path for Rotten Tomatoes and Metacritic.

Open the game's **ACF Shared Fields** or **All fields and publishing** editor:

1. Enter its **Wikipedia article title or URL**, or choose **Fetch aggregate scores** to search Wikipedia using the game's title.
2. Choose the matching article from the results and fetch again. Check the returned article title.
3. Choose the Metacritic platform/edition from the available values. Scores stay separate; the importer does not average platforms or automatically choose the highest. Sony-only sites offer eligible Sony platform scores.
4. Review OpenCritic's measure. When Wikipedia lists a percentage of critics recommending the game, it is stored and displayed as **% Critics recommend**. A published critic average retains its **/100** scale.
5. Select the scores, choose **Use selected scores**, then save or publish using the existing global/local controls.

The fetched article title, exact Wikipedia revision, platform and measure are retained. Wikipedia contributor attribution and its license appear in the footer. Provider citation links are retained when Wikipedia supplies them; otherwise the source link points to the Wikipedia revision. Preview alone does not save or discard existing form edits. Missing/unrated values remain absent from the preview and do not erase existing scores.

Wikipedia coverage varies. The importer reads explicit aggregate rows and clearly identified prose values; it does not infer a score from descriptions such as “Mighty”, positive-review percentages, or a review count. If the article has no usable value, enter a verified value manually or leave that provider blank.

Old RAWG/OpenCritic lookup IDs are hidden from the editors while their stored data is preserved. The importer no longer makes RAWG or RapidAPI requests or requests their credentials. Existing saved values are retained until you replace or clear them. Legacy source credits remain for data that has not yet been replaced.

For Movies/TV, continue using the existing IMDb identity/OMDb workflow. Their score imports are unchanged. **Network Admin → Shared Content → Review Score Sources** explains the current sources.

Requests run only in the authenticated editor and use six-hour caches with provider cooldowns. Public pages render stored values without calling Wikipedia. Global saves propagate aggregate data normally while each site's editorial reviews remain independent.

## Source references

- [GameRevolution review reference](https://www.gamerevolution.com/review/934630-hogwarts-legacy-review-ps5-pc-xbox-ps4-switch)
- [MediaWiki article parsing API](https://www.mediawiki.org/wiki/API:Parsing_wikitext)
- [Wikipedia reception-table example](https://en.wikipedia.org/wiki/Clair_Obscur:_Expedition_33#Reception)
- [Wikipedia licensing](https://en.wikipedia.org/wiki/Wikipedia:Copyrights)
