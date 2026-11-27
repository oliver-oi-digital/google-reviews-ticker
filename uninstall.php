<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
delete_option( 'sd_grc_settings' );
delete_option( 'sd_grc_data' );
wp_clear_scheduled_hook( 'sd_grc_refresh' );

// Remove the encryption key line this plugin added to wp-config.php.
require_once __DIR__ . '/includes/file-utils.php';
$sd_grc_cfg = sd_grc_wpconfig_path();
if ( $sd_grc_cfg && is_writable( $sd_grc_cfg ) ) {
	$sd_grc_src = file_get_contents( $sd_grc_cfg ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$sd_grc_new = preg_replace( '#// GDF Google Reviews: encryption key[^\r\n]*\R\s*define\(\s*\'GDF_GRC_ENCRYPTION_KEY\',\s*\'[a-f0-9]{64}\'\s*\);\R\R?#', '', (string) $sd_grc_src, 1 );
	if ( $sd_grc_src && $sd_grc_new && $sd_grc_new !== $sd_grc_src ) {
		sd_grc_write_file( $sd_grc_cfg, $sd_grc_new );
	}
}
delete_option( 'sd_grc_wpconfig_lock' );
delete_transient( 'sd_grc_wpconfig_failed' );
