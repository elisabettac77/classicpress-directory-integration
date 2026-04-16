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
		// ... get_plugins() logic
		return $this->local_cp_items;
	}

	public function activate_action() {
		// ... plugin activation logic using activate_plugin()
	}

	public function install_action() {
		// ... standard plugin install logic using \Plugin_Upgrader
	}
}
