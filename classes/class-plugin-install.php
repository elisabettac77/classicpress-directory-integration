<?php
namespace ClassicPress\Directory;

class PluginInstall extends Abstract_Install {

	public function __construct() {
		$this->type = 'plugins';
		parent::__construct();
	}

		public function create_menu() {
		if ( ! current_user_can( 'install_plugins' ) ) {
			return;
		}

		$this->page = add_submenu_page(
			'plugins.php',
			esc_html__( 'Install ClassicPress Plugins', 'classicpress-directory-integration' ),
			esc_html__( 'Install CP Plugins', 'classicpress-directory-integration' ),
			'install_plugins',
			'classicpress-directory-integration-plugin-install',
			array( $this, 'render_menu' ),
			2
		);

		add_action( 'load-' . $this->page, array( $this, 'activate_action' ) );
		add_action( 'load-' . $this->page, array( $this, 'install_action' ) );
	}

	public function rename_menu() {
		if ( ! current_user_can( 'install_plugins' ) ) {
			return;
		}

		global $submenu;
		if ( isset( $submenu['plugins.php'] ) ) {
			foreach ( $submenu['plugins.php'] as $key => $value ) {
				if ( $value[2] !== 'plugin-install.php' ) {
					continue;
				}
				$submenu['plugins.php'][ $key ][0] = esc_html__( 'Install WP Plugins', 'classicpress-directory-integration' ); // phpcs:ignore
			}
		}
	}

		protected function get_local_cp_items() {
		if ( $this->local_cp_items !== false ) { return $this->local_cp_items; }
		
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		
		$all_plugins = get_plugins();
		$cp_plugins  = array();
		
		foreach ( $all_plugins as $slug => $plugin ) {
			$cp_plugins[ dirname( $slug ) ] = array(
				'WPSlug'   => $slug,
				'Name'     => $plugin['Name'],
				'Version'  => $plugin['Version'],
				'PluginURI'=> isset( $plugin['PluginURI'] ) ? $plugin['PluginURI'] : '',
				'Active'   => is_plugin_active( $slug ),
			);
		}
		
		$this->local_cp_items = $cp_plugins;
		return $this->local_cp_items;
	}

		// Deal with activation requests
	public function activate_action() {

		// Load local plugins information
		$local_cp_plugins = $this->get_local_cp_items();

		// Security checks
		if ( ! isset( $_GET['action'] ) ) { return; }
		if ( $_GET['action'] !== 'activate' ) { return; }
		if ( ! check_admin_referer( 'activate', '_cpdi' ) ) { return; }
		if ( ! current_user_can( 'activate_plugins' ) ) { return; }
		if ( ! isset( $_REQUEST['slug'] ) ) { return; }

		// Check if plugin slug is proper
		$slug = sanitize_key( wp_unslash( $_REQUEST['slug'] ) );
		if ( ! array_key_exists( $slug, $local_cp_plugins ) ) { return; }

		// Activate plugin
		$result = activate_plugin( $local_cp_plugins[ $slug ]['WPSlug'] );

		if ( $result !== null ) {
			// Translators: %1$s is the plugin name.
			$message = sprintf( esc_html__( 'Error activating %1$s.', 'classicpress-directory-integration' ), $local_cp_plugins[ $slug ]['Name'] );
			$this->add_notice( $message, true );
		} else {
			// Translators: %1$s is the plugin name.
			$message = sprintf( esc_html__( '%1$s activated.', 'classicpress-directory-integration' ), $local_cp_plugins[ $slug ]['Name'] );
			$this->add_notice( $message, false );
		}

		$sendback = remove_query_arg( array( 'action', 'slug', '_cpdi' ), wp_get_referer() );
		wp_safe_redirect( $sendback );
		exit;
	}

	// Deal with installation requests
	public function install_action() {

		// Security checks
		if ( ! isset( $_GET['action'] ) ) { return; }
		if ( $_GET['action'] !== 'install' ) { return; }
		if ( ! check_admin_referer( 'install', '_cpdi' ) ) { return; }
		if ( ! current_user_can( 'install_plugins' ) ) { return; }
		if ( ! isset( $_REQUEST['slug'] ) ) { return; }

		// Check if plugin slug is proper
		$slug = sanitize_key( wp_unslash( $_REQUEST['slug'] ) );

		// Get github release file via API
		$args     = array(
			'byslug'  => $slug,
			'_fields' => 'meta,title',
		);
		$response = self::do_directory_request( $args, 'plugins' );
		
		if ( ! $response['success'] || ! isset( $response['response'][0]['meta']['download_link'] ) ) {
			$message = esc_html__( 'API error: Could not fetch download link.', 'classicpress-directory-integration' );
			$this->add_notice( $message, true );
			$sendback = remove_query_arg( array( 'action', 'slug', '_cpdi' ), wp_get_referer() );
			wp_safe_redirect( $sendback );
			exit;
		}

		$installation_url = $response['response'][0]['meta']['download_link'];
		$plugin_name      = $response['response'][0]['title']['rendered'];

		// Install plugin
		$skin     = new PluginInstallSkin( array( 'type' => 'plugin' ) );
		$upgrader = new \Plugin_Upgrader( $skin );
		$response = $upgrader->install( $installation_url );

		if ( $response !== true ) {
			// Translators: %1$s is the plugin name.
			$message = sprintf( esc_html__( 'Error installing %1$s.', 'classicpress-directory-integration' ), $plugin_name );
			$this->add_notice( $message, true );
		} else {
			// Translators: %1$s is the plugin name.
			$message = sprintf( esc_html__( '%1$s installed.', 'classicpress-directory-integration' ), $plugin_name );
			$this->add_notice( $message, false );
		}

		$sendback = remove_query_arg( array( 'action', 'slug', '_cpdi' ), wp_get_referer() );
		wp_safe_redirect( $sendback );
		exit;
	}
}
