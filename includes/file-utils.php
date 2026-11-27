<?php
/**
 * Helpers for safely editing wp-config.php. Shared by the plugin and uninstall.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'sd_grc_wpconfig_path' ) ) {
	/** Where WordPress loads wp-config.php from (same rules as wp-load.php). */
	function sd_grc_wpconfig_path() {
		if ( file_exists( ABSPATH . 'wp-config.php' ) ) {
			return ABSPATH . 'wp-config.php';
		}
		$up = dirname( ABSPATH ) . '/wp-config.php';
		if ( file_exists( $up ) && ! file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) {
			return $up;
		}
		return '';
	}
}

if ( ! function_exists( 'sd_grc_write_file' ) ) {
	/**
	 * Replace a file's contents so other requests never see it half-written.
	 *
	 * Writes a temporary file in the same folder, copies the original's
	 * permissions and swaps it in with rename(), which is atomic. The temp file
	 * has a .php extension so, if it were ever requested, it would run (and
	 * print nothing) rather than be served as text. Falls back to an in-place
	 * write only if the folder isn't writable, restoring the original if the
	 * result doesn't read back correctly.
	 */
	function sd_grc_write_file( $path, $contents ) {
		// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors
		$dir   = dirname( $path );
		$perms = @fileperms( $path );

		if ( is_writable( $dir ) ) {
			$tmp = $dir . '/.gdf-grc-' . bin2hex( random_bytes( 8 ) ) . '.php';
			if ( strlen( $contents ) === @file_put_contents( $tmp, $contents, LOCK_EX ) && @file_get_contents( $tmp ) === $contents ) {
				if ( false !== $perms ) {
					@chmod( $tmp, $perms & 0777 );
				}
				if ( @rename( $tmp, $path ) ) {
					clearstatcache( true, $path );
					return @file_get_contents( $path ) === $contents;
				}
			}
			if ( file_exists( $tmp ) ) {
				@unlink( $tmp );
			}
		}

		$orig = @file_get_contents( $path );
		if ( strlen( $contents ) === @file_put_contents( $path, $contents, LOCK_EX ) && @file_get_contents( $path ) === $contents ) {
			return true;
		}
		if ( false !== $orig ) {
			@file_put_contents( $path, $orig, LOCK_EX );
		}
		return false;
		// phpcs:enable
	}
}
