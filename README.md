# Google Review Count (google-reviews-ticker)

WordPress plugin by [Get Digital Fit](https://www.getdigitalfit.co.uk). It fetches a business's Google rating and total review count from the Places API (New) on a daily or weekly schedule, outputs them through shortcodes, and includes a scrolling ticker for Divi Text modules.

## Install

Zip this folder as `shutterdog-review-count/` and upload it via **Plugins → Add New → Upload Plugin**. Then go to **Settings → Google Review Count** and add your Google API key and Place ID.

## Shortcodes

| Shortcode | Output |
|---|---|
| `[google_review_count]` | 127 |
| `[google_review_count animate="yes" round="10" suffix="+"]` | 120+ (counts up when scrolled into view) |
| `[google_rating]` | 4.9 |
| `[google_rating display="symbol"]` | ★★★★★ |
| `[google_rating display="both" position="before"]` | ★ 4.9 |
| `[google_reviews]` | 4.9 ★ from 127 reviews |
| `[google_reviews show="rating"]` / `show="count"` | Rating only / count only |

`[google_rating]` and `[google_reviews]` accept `display` (`number`, `symbol` or `both`), `symbol` (an HTML entity such as `&#9733;`), `position` (`before` or `after`), `repeat` (`yes` or `no`) and `decimals`. `[google_reviews]` also accepts `separator`, `count_prefix`, `count_suffix`, `count_round` and `count_animate`. The default symbol is set on the settings page.

## Ticker

Give a Divi Text module the CSS class `sd-ticker` and put the ticker text inside it. Optional modifier classes: `sd-ticker--slow`, `sd-ticker--fast` and `sd-ticker--reverse`.
