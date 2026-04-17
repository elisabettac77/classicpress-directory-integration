<?php
namespace ClassicPress;

class PluginInstall extends AbstractInstall {

	public function __construct() {
		$this->type = 'plugins';
		parent::__construct();
	}

	public function create_menu() {
		if ( ! current_user_can( 'install_plugins' ) ) return;
		$this->page = add_submenu_page( 'plugins.php', esc_html__( 'Install ClassicPress Plugins', 'classicpress-directory-integration' ), esc_html__( 'Install CP Plugins', 'classicpress-directory-integration' ), 'install_plugins', 'classicpress-directory-integration-plugin-install', array( $this, 'render_menu' ), 2 );
		add_action( 'load-' . $this->page, array( $this, 'activate_action' ) );
		add_action( 'load-' . $this->page, array( $this, 'install_action' ) );
	}

	public function rename_menu() {
		if ( ! current_user_can( 'install_plugins' ) ) return;
		global $submenu;
		if ( isset( $submenu['plugins.php'] ) ) {
			foreach ( $submenu['plugins.php'] as $key => $value ) {
				if ( $value[2] !== 'plugin-install.php' ) continue;
				$submenu['plugins.php'][ $key ][0] = esc_html__( 'Install WP Plugins', 'classicpress-directory-integration' ); // phpcs:ignore
			}
		}
	}

	protected function get_local_cp_items() {
		if ( $this->local_cp_items !== false ) return $this->local_cp_items;
		if ( ! function_exists( 'get_plugins' ) ) require_once ABSPATH . 'wp-admin/includes/plugin.php';
		
		$all_plugins = get_plugins();
		$cp_plugins  = array();
		foreach ( $all_plugins as $slug => $plugin ) {
			$cp_plugins[ dirname( $slug ) ] = array(
				'WP_Slug'   => $slug,
				'Name'      => $plugin['Name'],
				'Version'   => $plugin['Version'],
				'PluginURI' => isset( $plugin['PluginURI'] ) ? $plugin['PluginURI'] : '',
				'Active'    => is_plugin_active( $slug ),
			);
		}
		$this->local_cp_items = $cp_plugins;
		return $this->local_cp_items;
	}

	public function activate_action() {
		if ( ! isset( $_GET['action'] ) || $_GET['action'] !== 'activate' ) return;
		if ( ! check_admin_referer( 'activate', 'cpdi' ) || ! current_user_can( 'activate_plugins' ) ) return;
		if ( ! isset( $_REQUEST['slug'] ) ) return;

		$local_cp_plugins = $this->get_local_cp_items();
		$slug = sanitize_key( wp_unslash( $_REQUEST['slug'] ) );
		
		if ( array_key_exists( $slug, $local_cp_plugins ) ) {
			$result = activate_plugin( $local_cp_plugins[ $slug ]['WP_Slug'] );
			if ( is_wp_error( $result ) ) {
				$this->add_notice( sprintf( esc_html__( 'Error activating %1$s.', 'classicpress-directory-integration' ), $local_cp_plugins[ $slug ]['Name'] ), true );
			} else {
				$this->add_notice( sprintf( esc_html__( '%1$s activated.', 'classicpress-directory-integration' ), $local_cp_plugins[ $slug ]['Name'] ), false );
			}
		}
		
		wp_safe_redirect( $this->get_redirect_url() );
		exit;
	}

	public function install_action() {
		if ( ! isset( $_GET['action'] ) || $_GET['action'] !== 'install' ) return;
		if ( ! check_admin_referer( 'install', 'cpdi' ) || ! current_user_can( 'install_plugins' ) ) return;
		if ( ! isset( $_REQUEST['slug'] ) ) return;

		$slug = sanitize_key( wp_unslash( $_REQUEST['slug'] ) );
		$response = self::do_directory_request( array( 'byslug' => $slug, '_fields' => 'meta,title' ), 'plugins' );

		if ( ! $response['success'] || ! isset( $response['response'][0]['meta']['download_link'] ) ) {
			$this->add_notice( esc_html__( 'API error: Could not fetch download link.', 'classicpress-directory-integration' ), true );
			wp_safe_redirect( $this->get_redirect_url() );
			exit;
		}

		$installation_url = $response['response'][0]['meta']['download_link'];
		$plugin_name      = $response['response'][0]['title']['rendered'];

		$skin     = new PluginInstallSkin( array( 'type' => 'plugin' ) );
		$upgrader = new \Plugin_Upgrader( $skin );
		$install  = $upgrader->install( $installation_url );

		if ( $install !== true ) {
			$this->add_notice( sprintf( esc_html__( 'Error installing %1$s.', 'classicpress-directory-integration' ), $plugin_name ), true );
		} else {
			$this->add_notice( sprintf( esc_html__( '%1$s installed.', 'classicpress-directory-integration' ), $plugin_name ), false );
		}

		wp_safe_redirect( $this->get_redirect_url() );
		exit;
	}
}
