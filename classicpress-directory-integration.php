<?php
/**
 * -----------------------------------------------------------------------------
 * Plugin Name:  ClassicPress Directory Integration
 * Description:  Install and update plugins and themes from ClassicPress directory.
 * Version:      1.1.7
 * Author:       ClassicPress Contributors
 * Author URI:   https://www.classicpress.net
 * Text Domain:  classicpress-directory-integration
 * Domain Path:  /languages
 * Requires PHP: 7.4
 * Requires CP:  2.0
 * -----------------------------------------------------------------------------
 */

namespace ClassicPress\Directory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 1. ClassicPress Environment Check
if ( ! function_exists( 'classicpress_version' ) ) {
	add_action( 'admin_init', function() {
		deactivate_plugins( plugin_basename( __FILE__ ) );
	} );
	add_action( 'admin_notices', function() {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'ClassicPress Directory integration is for ClassicPress sites only.', 'classicpress-directory-integration' ) . '</p></div>';
	} );
	return;
}

// 2. Load Constants and Functions
require_once plugin_dir_path( __FILE__ ) . 'includes/constants.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/functions.php';

// 3. Load Helpers and Core Abstracts
require_once plugin_dir_path( __FILE__ ) . 'classes/trait-helpers.php';
require_once plugin_dir_path( __FILE__ ) . 'classes/class-abstract-update.php';

// 4. Initialize Update Logic (Corrected Class Names & Namespaces)
require_once plugin_dir_path( __FILE__ ) . 'classes/class-plugin-update.php';
require_once plugin_dir_path( __FILE__ ) . 'classes/class-theme-update.php';

new \ClassicPress\Directory\Integration\Plugin_Update();
new \ClassicPress\Directory\Integration\Theme_Update();

// 5. Admin-Only Logic
if ( is_admin() ) {
	// CRITICAL: Load the core WP files needed for "Skins" to prevent Fatal Errors
	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

	require_once plugin_dir_path( __FILE__ ) . 'classes/class-abstract-install.php';
	
	// Load Skins
	require_once plugin_dir_path( __FILE__ ) . 'classes/class-plugin-install-skin.php';
	require_once plugin_dir_path( __FILE__ ) . 'classes/class-theme-install-skin.php';
	
	// Load Installers
	require_once plugin_dir_path( __FILE__ ) . 'classes/class-plugin-install.php';
	require_once plugin_dir_path( __FILE__ ) . 'classes/class-theme-install.php';

	// Instantiate Installers using correct namespaces
	new \ClassicPress\Directory\Integration\Plugin_Install();
	new \ClassicPress\Directory\Integration\Theme_Install();
}

// 6. CLI and Translations
add_action( 'plugins_loaded', function() {
	load_plugin_textdomain( 'classicpress-directory-integration', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once plugin_dir_path( __FILE__ ) . 'classes/class-wpcli.php';
	\WP_CLI::add_command( 'cpdi', '\ClassicPress\Directory\CPDICLI' );
}
