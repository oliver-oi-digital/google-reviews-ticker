<?php
/**
 * Plugin Name:       Google Review Count
 * Description:       Fetches a business's Google review count and rating on a schedule and outputs it via shortcodes. Includes a lightweight ticker for Divi Text modules.
 * Version:           1.1.0
 * Author:            Get Digital Fit
 * Author URI:        https://www.getdigitalfit.co.uk
 * License:           GPL-2.0-or-later
 * Text Domain:       sd-grc
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SD_GRC_VERSION', '1.1.0' );
define( 'SD_GRC_OPT', 'sd_grc_settings' );
define( 'SD_GRC_DATA', 'sd_grc_data' );
define( 'SD_GRC_HOOK', 'sd_grc_refresh' );
define( 'SD_GRC_URL', plugin_dir_url( __FILE__ ) );

/* -------------------------------------------------------------------------
 * Settings helpers
 * ---------------------------------------------------------------------- */

function sd_grc_settings() {
	return wp_parse_args(
		get_option( SD_GRC_OPT, array() ),
		array(
			'api_key'   => '',
			'place_id'  => '',
			'frequency' => 'daily',
			'fallback'  => '',
			'symbol'    => '&#9733;',
		)
	);
}

function sd_grc_data() {
	return wp_parse_args(
		get_option( SD_GRC_DATA, array() ),
		array(
			'count'      => null,
			'rating'     => null,
			'updated'    => 0,
			'last_error' => '',
		)
	);
}

/* -------------------------------------------------------------------------
 * Scheduling
 * ---------------------------------------------------------------------- */

register_activation_hook( __FILE__, 'sd_grc_schedule' );
register_deactivation_hook( __FILE__, 'sd_grc_unschedule' );

function sd_grc_schedule() {
	sd_grc_unschedule();
	$freq = sd_grc_settings()['frequency'];
	wp_schedule_event( time() + 60, 'weekly' === $freq ? 'weekly' : 'daily', SD_GRC_HOOK );
}

function sd_grc_unschedule() {
	wp_clear_scheduled_hook( SD_GRC_HOOK );
}

add_action( SD_GRC_HOOK, 'sd_grc_fetch' );

// Reschedule when the frequency changes.
add_action(
	'update_option_' . SD_GRC_OPT,
	function ( $old, $new ) {
		if ( ( $old['frequency'] ?? '' ) !== ( $new['frequency'] ?? '' ) ) {
			sd_grc_schedule();
		}
	},
	10,
	2
);

/* -------------------------------------------------------------------------
 * Fetch from Google Places API (New)
 * ---------------------------------------------------------------------- */

function sd_grc_fetch() {
	$s    = sd_grc_settings();
	$data = sd_grc_data();

	if ( empty( $s['api_key'] ) || empty( $s['place_id'] ) ) {
		$data['last_error'] = 'API key or Place ID missing.';
		update_option( SD_GRC_DATA, $data, false );
		return false;
	}

	$response = wp_remote_get(
		'https://places.googleapis.com/v1/places/' . rawurlencode( $s['place_id'] ),
		array(
			'timeout' => 15,
			'headers' => array(
				'X-Goog-Api-Key'   => $s['api_key'],
				'X-Goog-FieldMask' => 'rating,userRatingCount',
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		$data['last_error'] = $response->get_error_message();
		update_option( SD_GRC_DATA, $data, false );
		return false;
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( 200 !== $code || ! isset( $body['userRatingCount'] ) ) {
		$data['last_error'] = sprintf( 'HTTP %d: %s', $code, $body['error']['message'] ?? 'No review count returned.' );
		update_option( SD_GRC_DATA, $data, false ); // Keep the last good number.
		return false;
	}

	update_option(
		SD_GRC_DATA,
		array(
			'count'      => (int) $body['userRatingCount'],
			'rating'     => isset( $body['rating'] ) ? (float) $body['rating'] : null,
			'updated'    => time(),
			'last_error' => '',
		),
		false
	);
	return true;
}

/* -------------------------------------------------------------------------
 * Shortcodes
 * [google_review_count]               → 127
 * [google_review_count animate="yes"] → counts up from 0 when scrolled into view
 * [google_review_count round="10" suffix="+"] → 120+
 * [google_rating decimals="1"]        → 4.9
 * [google_rating display="symbol"]    → ★★★★★ (symbol repeated to the rounded rating)
 * [google_rating display="both"]      → 4.9 ★
 * [google_rating display="both" symbol="&#9733;" position="before" repeat="yes"] → ★★★★★ 4.9
 * ---------------------------------------------------------------------- */

add_shortcode( 'google_review_count', 'sd_grc_count_shortcode' );
add_shortcode( 'google_rating', 'sd_grc_rating_shortcode' );
add_shortcode( 'google_reviews', 'sd_grc_reviews_shortcode' );

/*
 * Combined shortcode: average rating and/or total review count.
 * [google_reviews]                                  → 4.9 ★ from 127 reviews
 * [google_reviews show="rating"]                    → 4.9 ★
 * [google_reviews show="count"]                     → 127 reviews
 * [google_reviews display="symbol" separator=" · "] → ★★★★★ · 127 reviews
 * Accepts every [google_rating] attribute, plus count_animate, count_round,
 * count_prefix, count_suffix and separator.
 */
function sd_grc_reviews_shortcode( $atts ) {
	$a = shortcode_atts(
		array(
			'show'          => 'both', // both | rating | count
			'separator'     => ' from ',
			'decimals'      => 1,
			'display'       => 'both', // number | symbol | both
			'symbol'        => '',
			'position'      => 'after',
			'repeat'        => '',
			'count_animate' => 'no',
			'count_round'   => 0,
			'count_prefix'  => '',
			'count_suffix'  => ' reviews',
		),
		$atts,
		'google_reviews'
	);

	$show  = in_array( $a['show'], array( 'rating', 'count' ), true ) ? $a['show'] : 'both';
	$parts = array();

	if ( 'count' !== $show ) {
		$parts[] = sd_grc_rating_shortcode(
			array(
				'decimals' => $a['decimals'],
				'display'  => $a['display'],
				'symbol'   => $a['symbol'],
				'position' => $a['position'],
				'repeat'   => $a['repeat'],
			)
		);
	}
	if ( 'rating' !== $show ) {
		$parts[] = sd_grc_count_shortcode(
			array(
				'animate' => $a['count_animate'],
				'round'   => $a['count_round'],
				'prefix'  => $a['count_prefix'],
				'suffix'  => $a['count_suffix'],
			)
		);
	}

	$parts = array_filter( $parts );
	if ( ! $parts ) {
		return '';
	}
	return '<span class="sd-grc-reviews">' . implode( esc_html( $a['separator'] ), $parts ) . '</span>';
}

function sd_grc_count_shortcode( $atts ) {
	$a = shortcode_atts(
		array(
			'animate' => 'no',
			'round'   => 0,
			'prefix'  => '',
			'suffix'  => '',
		),
		$atts,
		'google_review_count'
	);

	$count = sd_grc_data()['count'];
	if ( null === $count ) {
		$count = (int) sd_grc_settings()['fallback'];
	}
	if ( ! $count ) {
		return '';
	}

	$round = absint( $a['round'] );
	if ( $round > 1 ) {
		$count = (int) floor( $count / $round ) * $round;
	}

	$animate = in_array( strtolower( $a['animate'] ), array( 'yes', '1', 'true' ), true );
	if ( $animate ) {
		wp_enqueue_script( 'sd-grc' );
	}

	return sprintf(
		'<span class="sd-grc-count"%s>%s%s%s</span>',
		$animate ? ' data-sd-count="' . esc_attr( $count ) . '"' : '',
		esc_html( $a['prefix'] ),
		esc_html( number_format_i18n( $count ) ),
		esc_html( $a['suffix'] )
	);
}

function sd_grc_rating_shortcode( $atts ) {
	$a = shortcode_atts(
		array(
			'decimals' => 1,
			'display'  => 'number', // number | symbol | both
			'symbol'   => '',       // HTML entity or character; defaults to the setting.
			'position' => 'after',  // before | after (for display="both")
			'repeat'   => '',       // yes | no; defaults to yes for "symbol", no for "both"
		),
		$atts,
		'google_rating'
	);

	$rating = sd_grc_data()['rating'];
	if ( null === $rating ) {
		return '';
	}

	$display = in_array( $a['display'], array( 'symbol', 'both' ), true ) ? $a['display'] : 'number';
	$number  = esc_html( number_format_i18n( $rating, absint( $a['decimals'] ) ) );

	if ( 'number' === $display ) {
		return '<span class="sd-grc-rating">' . $number . '</span>';
	}

	$symbol = sd_grc_symbol( '' !== $a['symbol'] ? $a['symbol'] : sd_grc_settings()['symbol'] );
	$repeat = '' === $a['repeat'] ? 'symbol' === $display : in_array( strtolower( $a['repeat'] ), array( 'yes', '1', 'true' ), true );
	$times  = $repeat ? max( 1, min( 5, (int) round( $rating ) ) ) : 1;
	$sym    = '<span class="sd-grc-symbol" aria-hidden="true">' . str_repeat( esc_html( $symbol ), $times ) . '</span>';

	if ( 'symbol' === $display ) {
		/* translators: %s: rating number */
		return '<span class="sd-grc-rating" role="img" aria-label="' . esc_attr( sprintf( 'Rated %s out of 5', $number ) ) . '">' . $sym . '</span>';
	}

	return '<span class="sd-grc-rating">' . ( 'before' === $a['position'] ? $sym . ' ' . $number : $number . ' ' . $sym ) . '</span>';
}

/**
 * Turn an HTML entity (e.g. &#9733; or &starf;) or a literal character into plain text.
 * Tags are stripped; the result is escaped on output.
 */
function sd_grc_symbol( $raw ) {
	$raw = wp_strip_all_tags( (string) $raw );
	// The block/Divi editors may store the ampersand encoded (&amp;#9733;), so decode twice.
	return html_entity_decode( html_entity_decode( $raw, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
}

/* -------------------------------------------------------------------------
 * Front-end assets
 * The ticker CSS/JS is tiny, so it loads site-wide; it only acts on
 * elements with the class "sd-ticker".
 * ---------------------------------------------------------------------- */

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_register_script( 'sd-grc', SD_GRC_URL . 'assets/sd-grc.js', array(), SD_GRC_VERSION, true );
		wp_enqueue_style( 'sd-grc', SD_GRC_URL . 'assets/sd-grc.css', array(), SD_GRC_VERSION );
		wp_enqueue_script( 'sd-grc' );
	}
);

/* -------------------------------------------------------------------------
 * Admin: Settings → Google Review Count
 * ---------------------------------------------------------------------- */

add_action(
	'admin_menu',
	function () {
		add_options_page( 'Google Review Count', 'Google Review Count', 'manage_options', 'sd-grc', 'sd_grc_settings_page' );
	}
);

add_action(
	'admin_init',
	function () {
		register_setting(
			'sd_grc',
			SD_GRC_OPT,
			array(
				'sanitize_callback' => function ( $in ) {
					return array(
						'api_key'   => sanitize_text_field( $in['api_key'] ?? '' ),
						'place_id'  => sanitize_text_field( $in['place_id'] ?? '' ),
						'frequency' => ( $in['frequency'] ?? '' ) === 'weekly' ? 'weekly' : 'daily',
						'fallback'  => absint( $in['fallback'] ?? 0 ) ?: '',
						'symbol'    => wp_strip_all_tags( $in['symbol'] ?? '' ) ?: '&#9733;',
					);
				},
			)
		);
	}
);

add_action(
	'admin_post_sd_grc_fetch_now',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed.' );
		}
		check_admin_referer( 'sd_grc_fetch_now' );
		$ok = sd_grc_fetch();
		wp_safe_redirect( add_query_arg( 'sd_grc_fetched', $ok ? '1' : '0', admin_url( 'options-general.php?page=sd-grc' ) ) );
		exit;
	}
);

function sd_grc_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s    = sd_grc_settings();
	$d    = sd_grc_data();
	$next = wp_next_scheduled( SD_GRC_HOOK );
	?>
	<div class="wrap">
		<h1>Google Review Count</h1>

		<?php if ( isset( $_GET['sd_grc_fetched'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<?php if ( '1' === $_GET['sd_grc_fetched'] ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
				<div class="notice notice-success is-dismissible"><p>Fetched successfully.</p></div>
			<?php else : ?>
				<div class="notice notice-error is-dismissible"><p>Fetch failed: <?php echo esc_html( $d['last_error'] ); ?></p></div>
			<?php endif; ?>
		<?php endif; ?>

		<h2>Current values</h2>
		<table class="widefat striped" style="max-width:600px">
			<tr><th>Review count</th><td><?php echo null === $d['count'] ? '<em>Not fetched yet</em>' : esc_html( $d['count'] ); ?></td></tr>
			<tr><th>Rating</th><td><?php echo null === $d['rating'] ? '—' : esc_html( $d['rating'] ); ?></td></tr>
			<tr><th>Last updated</th><td><?php echo $d['updated'] ? esc_html( wp_date( 'j M Y, H:i', $d['updated'] ) ) : '—'; ?></td></tr>
			<tr><th>Next scheduled fetch</th><td><?php echo $next ? esc_html( wp_date( 'j M Y, H:i', $next ) ) : '<em>Not scheduled — save settings to schedule</em>'; ?></td></tr>
			<?php if ( $d['last_error'] ) : ?>
				<tr><th>Last error</th><td style="color:#b32d2e"><?php echo esc_html( $d['last_error'] ); ?></td></tr>
			<?php endif; ?>
		</table>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:12px 0 24px">
			<input type="hidden" name="action" value="sd_grc_fetch_now">
			<?php wp_nonce_field( 'sd_grc_fetch_now' ); ?>
			<?php submit_button( 'Fetch now', 'secondary', 'submit', false ); ?>
		</form>

		<h2>Settings</h2>
		<form method="post" action="options.php">
			<?php settings_fields( 'sd_grc' ); ?>
			<table class="form-table">
				<tr>
					<th><label for="sd_grc_key">Google API key</label></th>
					<td><input type="password" id="sd_grc_key" name="<?php echo esc_attr( SD_GRC_OPT ); ?>[api_key]" value="<?php echo esc_attr( $s['api_key'] ); ?>" class="regular-text" autocomplete="off">
					<p class="description">Places API (New) must be enabled for this key. Restrict the key to Places API and the server's IP.</p></td>
				</tr>
				<tr>
					<th><label for="sd_grc_place">Place ID</label></th>
					<td><input type="text" id="sd_grc_place" name="<?php echo esc_attr( SD_GRC_OPT ); ?>[place_id]" value="<?php echo esc_attr( $s['place_id'] ); ?>" class="regular-text">
					<p class="description">Find it with Google's Place ID Finder (starts with <code>ChIJ</code>).</p></td>
				</tr>
				<tr>
					<th><label for="sd_grc_freq">Refresh</label></th>
					<td><select id="sd_grc_freq" name="<?php echo esc_attr( SD_GRC_OPT ); ?>[frequency]">
						<option value="daily" <?php selected( $s['frequency'], 'daily' ); ?>>Daily</option>
						<option value="weekly" <?php selected( $s['frequency'], 'weekly' ); ?>>Weekly</option>
					</select></td>
				</tr>
				<tr>
					<th><label for="sd_grc_fallback">Fallback count</label></th>
					<td><input type="number" min="0" id="sd_grc_fallback" name="<?php echo esc_attr( SD_GRC_OPT ); ?>[fallback]" value="<?php echo esc_attr( $s['fallback'] ); ?>" class="small-text">
					<p class="description">Shown only if nothing has been fetched yet.</p></td>
				</tr>
				<tr>
					<th><label for="sd_grc_symbol">Rating symbol</label></th>
					<td><input type="text" id="sd_grc_symbol" name="<?php echo esc_attr( SD_GRC_OPT ); ?>[symbol]" value="<?php echo esc_attr( $s['symbol'] ); ?>" class="small-text">
					<span style="font-size:1.4em;vertical-align:middle;margin-left:8px"><?php echo esc_html( sd_grc_symbol( $s['symbol'] ) ); ?></span>
					<p class="description">HTML entity or character used by <code>[google_rating display="symbol"]</code> and <code>display="both"</code>, e.g. <code>&amp;#9733;</code> (★) or <code>&amp;#11088;</code> (⭐). Can be overridden per shortcode with <code>symbol="…"</code>.</p></td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<h2>Usage</h2>
		<p><code>[google_review_count]</code> &nbsp; <code>[google_review_count animate="yes"]</code> &nbsp; <code>[google_review_count round="10" suffix="+"]</code> &nbsp; <code>[google_rating]</code></p>
		<p>Rating and/or count in one: <code>[google_reviews]</code> → 4.9 ★ from 127 reviews &nbsp; <code>[google_reviews show="rating"]</code> &nbsp; <code>[google_reviews show="count"]</code> &nbsp; <code>[google_reviews display="symbol" separator=" · "]</code>. Also accepts <code>count_animate</code>, <code>count_round</code>, <code>count_prefix</code>, <code>count_suffix</code>.</p>
		<p>Rating with a symbol: <code>[google_rating display="symbol"]</code> → ★★★★★ &nbsp; <code>[google_rating display="both"]</code> → 4.9 ★ &nbsp; <code>[google_rating display="both" position="before" repeat="yes"]</code> → ★★★★★ 4.9 &nbsp; <code>[google_rating display="both" symbol="&amp;#11088;"]</code> → 4.9 ⭐</p>
		<p>Ticker: add a Divi Text module, give it the CSS class <code>sd-ticker</code>, and put the ticker text in it, e.g. <code>★★★★★ Rated [google_rating] from [google_review_count] Google reviews</code>. Optional: <code>sd-ticker--slow</code>, <code>sd-ticker--fast</code>, <code>sd-ticker--reverse</code>.</p>
	</div>
	<?php
}

// Make sure a schedule exists if settings were saved before activation hook ran (e.g. on migration).
add_action(
	'admin_init',
	function () {
		if ( ! wp_next_scheduled( SD_GRC_HOOK ) ) {
			sd_grc_schedule();
		}
	}
);

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=sd-grc' ) ) . '">Settings</a>' );
		return $links;
	}
);
