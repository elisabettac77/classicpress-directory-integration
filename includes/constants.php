<?php
/**
 * -----------------------------------------------------------------------------
 * Purpose: Declare non namespaced constants for this plugin.
 * -----------------------------------------------------------------------------
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

// The API Endpoint
define( 'CLASSICPRESS_DIRECTORY_INTEGRATION_URL', 'https://directory.classicpress.net/wp-json/wp/v2/' );

// Missing Constant: The URL to the plugin folder (required for CSS/JS enqueuing)
define( 'CPDI_URL', plugin_dir_url( dirname( __FILE__ ) ) );

// Missing Constant: The version of the plugin (required for cache busting assets)
define( 'CPDI_VERSION', '1.1.7' );
