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

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * STRICT CHECK: ClassicPress Only.
 * WordPress does not have the classicpress_version() function.
 * If we are on WP, we hook into admin_init to deactivate and show a notice.
 */
if ( ! function_exists( 'classicpress_version' ) ) {
	
	// Deactivate the plugin.
	add_action( 'admin_init', function() {
		if ( function_exists( 'deactivate_plugins' ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
		}
	} );

	// Display the error notice.
	add_action( 'admin_notices', function() {
		?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'ClassicPress Directory Integration is incompatible with WordPress. It has been deactivated.', 'classicpress-directory-integration' ); ?></p>
		</div>
		<?php
	} );

	// Stop execution here so no CP-specific classes are loaded.
	return;
}

/**
 * -----------------------------------------------------------------------------
 * CORE LOADER (ClassicPress Only)
 * -----------------------------------------------------------------------------
 */

// Define plugin constants.
require_once plugin_dir_path( __FILE__ ) . 'includes/constants.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/functions.php';

// Load Helpers and Base Abstracts.
require_once plugin_dir_path( __FILE__ ) . 'classes/trait-helpers.php';
require_once plugin_dir_path( __FILE__ ) . 'classes/class-abstract-update.php';

// Initialize Update Logic (Runs on both front and back end).
require_once plugin_dir_path( __FILE__ ) . 'classes/class-plugin-update.php';
require_once plugin_dir_path( __FILE__ ) . 'classes/class-theme-update.php';

new \ClassicPress\Directory\Integration\Plugin_Update();
new \ClassicPress\Directory\Integration\Theme_Update();

/**
 * Admin-specific components.
 */
if ( is_admin() ) {
	require_once plugin_dir_path( __FILE__ ) . 'classes/class-abstract-install.php';
	require_once plugin_dir_path( __FILE__ ) . 'classes/class-plugin-install-skin.php';
	require_once plugin_dir_path( __FILE__ ) . 'classes/class-theme-install-skin.php';
	require_once plugin_dir_path( __FILE__ ) . 'classes/class-plugin-install.php';
	require_once plugin_dir_path( __FILE__ ) . 'classes/class-theme-install.php';

	// Instantiate the Installers.
	new \ClassicPress\Directory\Integration\Plugin_Install();
	new \ClassicPress\Directory\Integration\Theme_Install();
}

/**
 * Initialize WP-CLI and Translations.
 */
add_action( 'plugins_loaded', function() {
	load_plugin_textdomain( 'classicpress-directory-integration', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once plugin_dir_path( __FILE__ ) . 'classes/class-wpcli.php';
	\WP_CLI::add_command( 'cpdi', '\ClassicPress\Directory\CPDICLI' );
}
