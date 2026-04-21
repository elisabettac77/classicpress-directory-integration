<?php

/**
 * -----------------------------------------------------------------------------
 * Plugin Name:  ClassicPress Directory Integration
 * Description:  Install and update plugins and themes from ClassicPress directory.
 * Version:      1.1.7
 * Author:       ClassicPress Contributors
 * Author URI:   https://www.classicpress.net
 * Plugin URI:   https://www.classicpress.net
 * Text Domain:  classicpress-directory-integration
 * Domain Path:  /languages
 * Requires PHP: 7.4
 * Requires CP:  2.0
 * -----------------------------------------------------------------------------
 * This is free software released under the terms of the General Public License,
 * version 2, or later. It is distributed WITHOUT ANY WARRANTY; without even the
 * implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. Full
 * text of the license is available at https://www.gnu.org/licenses/gpl-2.0.txt.
 * -----------------------------------------------------------------------------
 */

// Declare the namespace for the main loader.
namespace ClassicPress\Directory;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

/**
 * Bail if on WordPress.
 */
if ( ! function_exists( 'classicpress_version' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	add_action( 'admin_init', __NAMESPACE__ . '\deactivate_plugin_now' );
	add_action( 'admin_notices', __NAMESPACE__ . '\error_is_wp' );
	unset( $_GET['activate'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	return;
}

/**
 * Deactivate plugin on WP.
 */
function deactivate_plugin_now() {
	$plugin_path = 'classicpress-directory-integration/classicpress-directory-integration.php';
	if ( is_plugin_active( $plugin_path ) ) {
		deactivate_plugins( $plugin_path );
	}
}

/**
 * Error notice on WP.
 */
function error_is_wp() {
	$class   = 'notice notice-error';
	$message = __( 'ClassicPress Directory integration is a plugin meant to only work on ClassicPress sites.', 'classicpress-directory-integration' );
	printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
}

const DB_VERSION = 1;

// Load non-namespaced constants and functions.
require_once plugin_dir_path( __FILE__ ) . 'includes/constants.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/functions.php';

/**
 * Load Classes.
 * * We use the fully qualified class names including the \Integration sub-namespace
 * used in the actual class files.
 */
require_once plugin_dir_path( __FILE__ ) . 'classes/trait-helpers.php';

// Load Update logic (Front-end and Admin).
require_once plugin_dir_path( __FILE__ ) . 'classes/class-abstract-update.php';
require_once plugin_dir_path( __FILE__ ) . 'classes/class-plugin-update.php';
require_once plugin_dir_path( __FILE__ ) . 'classes/class-theme-update.php';

// Instantiate Updates.
new \ClassicPress\Directory\Integration\Plugin_Update();
new \ClassicPress\Directory\Integration\Theme_Update();

/**
 * Register text domain.
 */
function register_text_domain() {
	load_plugin_textdomain( 
		'classicpress-directory-integration', 
		false, 
		dirname( plugin_basename( __FILE__ ) ) . '/languages' 
	);
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\register_text_domain' );

/**
 * WP-CLI Support.
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once plugin_dir_path( __FILE__ ) . 'classes/class-wpcli.php';
	\WP_CLI::add_command( 'cpdi', '\ClassicPress\Directory\CPDICLI' );
}

/**
 * Admin-only logic (Installers).
 */
if ( is_admin() ) {
	require_once plugin_dir_path( __FILE__ ) . 'classes/class-abstract-install.php';
	require_once plugin_dir_path( __FILE__ ) . 'classes/class-plugin-install-skin.php';
	require_once plugin_dir_path( __FILE__ ) . 'classes/class-theme-install-skin.php';
	
	require_once plugin_dir_path( __FILE__ ) . 'classes/class-plugin-install.php';
	require_once plugin_dir_path( __FILE__ ) . 'classes/class-theme-install.php';

	// Instantiate Installers.
	new \ClassicPress\Directory\Integration\Plugin_Install();
	new \ClassicPress\Directory\Integration\Theme_Install();
}
