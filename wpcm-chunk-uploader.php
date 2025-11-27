<?php
/**
 * Plugin Name: WPCM Chunk Uploader
 * Plugin URI: https://github.com/danielpx-coder/wpcm-chunk-uploader
 * Description: Upload de arquivos grandes (XML, vídeos, ZIPs) em servidores com limites restritos usando chunked uploads via Plupload
 * Version: 1.0.0
 * Author: Daniel PX
 * Author URI: https://github.com/danielpx-coder
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wpcm-chunk-uploader
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 *
 * @package WPCM_Chunk_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Define plugin constants
 */
define( 'WPCM_CHUNK_UPLOADER_VERSION', '1.0.0' );
define( 'WPCM_CHUNK_UPLOADER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPCM_CHUNK_UPLOADER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPCM_CHUNK_UPLOADER_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main Plugin Class
 */
class WPCM_Chunk_Uploader {

	/**
	 * Instance of the class
	 *
	 * @var WPCM_Chunk_Uploader
	 */
	private static $instance = null;

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Get singleton instance
	 *
	 * @return WPCM_Chunk_Uploader
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		// Intercept upload size limit filter
		add_filter( 'upload_size_limit', array( $this, 'modify_upload_size_limit' ) );

		// Modify Plupload configuration
		add_filter( 'plupload_init', array( $this, 'modify_plupload_init' ) );

		// Enqueue scripts for media uploader
		add_action( 'wp_enqueue_media', array( $this, 'enqueue_media_scripts' ) );

		// Load plugin text domain
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Modify the upload size limit to allow large files
	 * Returns extremely high value to bypass frontend validation
	 *
	 * @param int $size Original upload size limit
	 * @return int Modified upload size limit
	 */
	public function modify_upload_size_limit( $size ) {
		// Return PHP_INT_MAX to allow frontend to select large files
		return PHP_INT_MAX;
	}

	/**
	 * Modify Plupload initialization to enable chunking
	 *
	 * @param array $plupload_init Plupload configuration array
	 * @return array Modified Plupload configuration
	 */
	public function modify_plupload_init( $plupload_init ) {
		// Get actual server limits
		$upload_max_filesize = $this->convert_to_bytes( ini_get( 'upload_max_filesize' ) );
		$post_max_size       = $this->convert_to_bytes( ini_get( 'post_max_size' ) );

		// Get the smallest limit
		$server_limit = min( $upload_max_filesize, $post_max_size );

		// Calculate chunk size as 90% of the server limit
		// This ensures headers + payload don't exceed 413 errors
		$chunk_size = intval( $server_limit * 0.90 );

		// Ensure chunk_size is at least 1MB and not larger than 50MB
		$chunk_size = max( 1048576, min( 52428800, $chunk_size ) );

		// Enable chunking
		$plupload_init['chunk_size'] = $chunk_size . 'b'; // Plupload expects 'b' suffix for bytes

		// Set maximum retries for stability
		$plupload_init['max_retries'] = 5;

		// Enable multiple chunk uploads simultaneously
		$plupload_init['max_file_size'] = PHP_INT_MAX . 'b';

		return $plupload_init;
	}

	/**
	 * Convert human-readable filesize to bytes
	 *
	 * @param string $value Filesize string (e.g., "128M", "2G", "512K")
	 * @return int Filesize in bytes
	 */
	private function convert_to_bytes( $value ) {
		$value = trim( $value );
		$last  = strtoupper( substr( $value, -1 ) );

		$value = intval( $value );

		switch ( $last ) {
			case 'G':
				$value *= 1024;
				// Fall through
			case 'M':
				$value *= 1024;
				// Fall through
			case 'K':
				$value *= 1024;
				break;
		}

		return $value;
	}

	/**
	 * Enqueue media scripts
	 */
	public function enqueue_media_scripts() {
		// Enqueue custom Plupload configuration script
		wp_enqueue_script(
			'wpcm-chunk-uploader-plupload',
			WPCM_CHUNK_UPLOADER_PLUGIN_URL . 'assets/js/wpcm-chunk-uploader.js',
			array( 'plupload' ),
			WPCM_CHUNK_UPLOADER_VERSION,
			true
		);

		// Pass plugin data to JavaScript
		wp_localize_script(
			'wpcm-chunk-uploader-plupload',
			'wcmChunkUploader',
			array(
				'upload_max_filesize' => ini_get( 'upload_max_filesize' ),
				'post_max_size'       => ini_get( 'post_max_size' ),
				'chunk_enabled'       => true,
			)
		);
	}

	/**
	 * Load plugin text domain for translations
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'wpcm-chunk-uploader',
			false,
			dirname( WPCM_CHUNK_UPLOADER_BASENAME ) . '/languages'
		);
	}
}

/**
 * Initialize the plugin
 */
function wpcm_chunk_uploader_init() {
	WPCM_Chunk_Uploader::get_instance();
}

add_action( 'plugins_loaded', 'wpcm_chunk_uploader_init' );
