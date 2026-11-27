# Google Review Count (google-reviews-ticker)

WordPress plugin by [Get Digital Fit](https://www.getdigitalfit.co.uk). It fetches a business's Google rating and total review count from the Places API (New) on a daily or weekly schedule, outputs them through shortcodes, and includes a scrolling ticker for Divi Text modules.

## Install

Zip this folder as `gdf-google-reviews/` and upload it via **Plugins → Add New → Upload Plugin**. Then go to **Settings → Google Review Count** and add your Google API key and Place ID. Once saved, the API key is never shown again or sent to the browser; leave the field blank to keep it, paste a new key to replace it, or tick "Remove the saved key".

### API key security

The API key is stored encrypted in the database (libsodium, bundled with WordPress). By default the encryption key is derived from the site's `AUTH_KEY`/`AUTH_SALT` in `wp-config.php`. If those salts are regenerated (some security plugins do this), the saved API key can't be decrypted and must be entered again; the settings page will say so.

To avoid that, define a dedicated key in `wp-config.php` **before** saving the API key:

```php
define( 'GDF_GRC_ENCRYPTION_KEY', 'a-long-random-string' );
```

Keys saved by older versions of the plugin are encrypted automatically the next time an admin page loads.

## Shortcodes

| Shortcode | Output |
|---|---|
| `[gdf_google_review_count]` | 127 |
| `[gdf_google_review_count animate="yes" round="10" suffix="+"]` | 120+ (counts up when scrolled into view) |
| `[gdf_google_rating]` | 4.9 |
| `[gdf_google_rating display="symbol"]` | ★★★★★ |
| `[gdf_google_rating display="both" position="before"]` | ★ 4.9 |
| `[gdf_google_reviews]` | 4.9 ★ from 127 reviews |
| `[gdf_google_reviews show="rating"]` / `show="count"` | Rating only / count only |

`[gdf_google_rating]` and `[gdf_google_reviews]` accept `display` (`number`, `symbol` or `both`), `symbol` (an HTML entity such as `&#9733;`), `position` (`before` or `after`), `repeat` (`yes` or `no`) and `decimals`. `[gdf_google_reviews]` also accepts `separator`, `count_prefix`, `count_suffix`, `count_round` and `count_animate`. The default symbol is set on the settings page.

The older unprefixed names (`[google_review_count]`, `[google_rating]`, `[google_reviews]`) still work for existing pages, unless another plugin has already registered them.

## Ticker

Give a Divi Text module the CSS class `sd-ticker` and put the ticker text inside it. Optional modifier classes: `sd-ticker--slow`, `sd-ticker--fast` and `sd-ticker--reverse`.
