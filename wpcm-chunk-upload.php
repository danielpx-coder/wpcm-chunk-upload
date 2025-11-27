<?php
/**
 * Plugin Name: WPCM Chunk Uploader
 * Plugin URI:  https://example.com/plugins/wpcm-chunk-uploader
 * Description: Enables reliable chunked uploads for large files using WordPress’ native Plupload, bypassing restrictive PHP upload limits without modifying server configuration.
 * Version:     2.1.7
 * Author:      Daniel Oliveira da Paixao
 * License:     GPL-2.0-or-later
 * Text Domain: wpcm-chunk-uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WPCM_Chunk_Uploader {

	/**
	 * Bootstraps the plugin.
	 */
	public static function init() {
		// Only run in the admin where the media uploader lives.
		if ( is_admin() ) {
			add_filter( 'upload_size_limit', array( __CLASS__, 'override_upload_size_limit' ), 999 );
			add_filter( 'plupload_init', array( __CLASS__, 'configure_plupload_chunking' ), 999 );
		}
	}

	/**
	 * Returns a very large upload limit so the Media Library selector never blocks large files.
	 *
	 * @param int $size Current limit in bytes.
	 *
	 * @return int
	 */
	public static function override_upload_size_limit( $size ) {
		return PHP_INT_MAX;
	}

	/**
	 * Configures Plupload to use dynamic chunk sizes based on the server’s real limits.
	 *
	 * @param array $plupload_settings Existing Plupload settings.
	 *
	 * @return array
	 */
	public static function configure_plupload_chunking( $plupload_settings ) {
		$effective_limit = self::get_effective_php_upload_limit();

		if ( $effective_limit <= 0 ) {
			// Fallback to a safe default if limits cannot be detected.
			$effective_limit = 2 * 1024 * 1024; // 2 MB
		}

		// Use ~90% of the smallest PHP limit to avoid hitting 413 errors.
		$chunk_size = max( floor( $effective_limit * 0.9 ), 256 * 1024 ); // Never go below 256 KB.

		// Plupload accepts chunk_size as an integer (bytes) or human-readable string (e.g., '1mb').
		$plupload_settings['chunk_size']  = self::bytes_to_plupload_size( $chunk_size );
		$plupload_settings['max_retries'] = isset( $plupload_settings['max_retries'] )
			? max( (int) $plupload_settings['max_retries'], 3 )
			: 3;

		// Ensure chunking is enabled explicitly.
		$plupload_settings['chunking'] = true;

		return $plupload_settings;
	}

	/**
	 * Determines the smallest non-zero limit between upload_max_filesize and post_max_size.
	 *
	 * @return int Limit in bytes.
	 */
	private static function get_effective_php_upload_limit() {
		$upload_max = self::convert_php_size_to_bytes( ini_get( 'upload_max_filesize' ) );
		$post_max   = self::convert_php_size_to_bytes( ini_get( 'post_max_size' ) );

		$limits = array_filter( array( $upload_max, $post_max ), function( $value ) {
			return $value > 0;
		} );

		if ( empty( $limits ) ) {
			return 0;
		}

		return (int) min( $limits );
	}

	/**
	 * Converts a PHP shorthand size notation (e.g. 2M, 1G) to bytes.
	 *
	 * @param string|int $size Size value from php.ini.
	 *
	 * @return int Size in bytes.
	 */
	private static function convert_php_size_to_bytes( $size ) {
		if ( is_numeric( $size ) ) {
			return (int) $size;
		}

		if ( ! is_string( $size ) ) {
			return 0;
		}

		$size  = trim( $size );
		$value = (float) $size;
		$unit  = strtolower( substr( $size, -1 ) );

		switch ( $unit ) {
			case 'g':
				$value *= 1024;
				// no break
			case 'm':
				$value *= 1024;
				// no break
			case 'k':
				$value *= 1024;
				break;
		}

		return (int) round( $value );
	}

	/**
	 * Formats bytes into a Plupload-compatible chunk size string.
	 *
	 * @param int $bytes Size in bytes.
	 *
	 * @return string
	 */
	private static function bytes_to_plupload_size( $bytes ) {
		if ( $bytes >= 1024 * 1024 * 1024 ) {
			return floor( $bytes / ( 1024 * 1024 * 1024 ) ) . 'gb';
		}

		if ( $bytes >= 1024 * 1024 ) {
			return floor( $bytes / ( 1024 * 1024 ) ) . 'mb';
		}

		if ( $bytes >= 1024 ) {
			return floor( $bytes / 1024 ) . 'kb';
		}

		return (string) max( 1, $bytes ) . 'b';
	}
}

WPCM_Chunk_Uploader::init();
