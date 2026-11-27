<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
delete_option( 'sd_grc_settings' );
delete_option( 'sd_grc_data' );
wp_clear_scheduled_hook( 'sd_grc_refresh' );
