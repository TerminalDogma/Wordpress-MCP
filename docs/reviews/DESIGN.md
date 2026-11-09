# Review design reference and implementation

Reference: [GameRevolution — Hogwarts Legacy Review](https://www.gamerevolution.com/review/934630-hogwarts-legacy-review-ps5-pc-xbox-ps4-switch).

The source page's content and branding extraction establish the article title/byline, review body, Final Verdict, numeric score, and Positives and Negatives structure. Extracted brand roles are primary/accent `#DF1362`, secondary `#5D6374`, background `#FEFEFE`, text `#181C25`, Play headings and Rubik body text. The extraction includes a generated screenshot reference; its pixels were not inspected, so spacing and layout below are proposed implementation choices rather than measured source dimensions.

## Implementation

Retain each existing site's own logo, font and accent variables. Use a large title, author/date, wide editorial image, readable article column, a compact score summary, and a final verdict with separate positives/negatives lists. The review archive uses image-led cards with overlaid scores and no excerpts. Review imagery uses the editor's featured image, falling back to the linked catalog image or the existing placeholder; no reference-site editorial images are copied.

Item detail pages show a compact score box on the featured image and a three-card score section. The local site's published review appears first with its name; applicable critic aggregates follow in their original units. Catalog image cards use a smaller version of the same badge.

User-requested medal tokens are independent of the reference site's grading design: gold `#EDC45B`, silver `#CBD2DC`, copper `#D69A70` on dark score boxes. Verdict panels use darker gold/silver/copper numeric variants for legibility on light surfaces. Labels state the award so meaning does not depend on color alone.

Desktop review content has a readable article column with a 250px summary. At narrower widths it becomes a single column. Aggregate cards and positives/negatives lists stack on phones. These responsive rules are implementation choices, checked in Chromium at desktop and 390px mobile widths.

Implementation sources: `single-msc_review.php`, `archive-msc_review.php`, `assets/reviews.css`, and the plugin's `Reviews` / `ReviewScores` classes. Source provider configuration and persistence are documented in [the editing guide](../reviews.md).
