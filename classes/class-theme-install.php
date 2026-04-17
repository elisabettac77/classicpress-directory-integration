<?php
namespace ClassicPress;

class ThemeInstall extends AbstractInstall {

	public function __construct() {
		$this->type = 'themes';
		parent::__construct();
	}

	public function create_menu() {
		if ( ! current_user_can( 'install_themes' ) ) return;
		$this->page = add_submenu_page( 'themes.php', esc_html__( 'ClassicPress Themes', 'classicpress-directory-integration' ), esc_html__( 'Install CP Themes', 'classicpress-directory-integration' ), 'install_themes', 'classicpress-directory-integration-theme-install', array( $this, 'render_menu' ), 2 );
		add_action( 'load-' . $this->page, array( $this, 'activate_action' ) );
		add_action( 'load-' . $this->page, array( $this, 'install_action' ) );
	}

	public function rename_menu() {
		if ( ! current_user_can( 'install_themes' ) ) return;
		global $submenu;
		if ( isset( $submenu['themes.php'] ) ) {
			foreach ( $submenu['themes.php'] as $key => $value ) {
				if ( $value[2] !== 'theme-install.php' ) continue;
				$submenu['themes.php'][ $key ][0] = esc_html__( 'Install WP Themes', 'classicpress-directory-integration' ); // phpcs:ignore
			}
		}
	}

	protected function get_local_cp_items() {
		if ( $this->local_cp_items !== false ) return $this->local_cp_items;
		$all_themes = wp_get_themes();
		$cp_themes  = array();
		foreach ( $all_themes as $slug => $theme ) {
			$cp_themes[ $slug ] = array(
				'WP_Slug'  => $slug,
				'Name'     => $theme->get( 'Name' ),
				'Version'  => $theme->get( 'Version' ),
				'ThemeURI' => $theme->get( 'ThemeURI' ),
				'Active'   => ( get_template() === $slug || get_stylesheet() === $slug ),
			);
		}
		$this->local_cp_items = $cp_themes;
		return $this->local_cp_items;
	}

	public function activate_action() {
		if ( ! isset( $_GET['action'] ) || $_GET['action'] !== 'activate' ) return;
		if ( ! check_admin_referer( 'activate', 'cpdi' ) || ! current_user_can( 'switch_themes' ) ) return;
		if ( ! isset( $_REQUEST['slug'] ) ) return;

		$local_cp_themes = $this->get_local_cp_items();
		$slug = sanitize_key( wp_unslash( $_REQUEST['slug'] ) );

		if ( array_key_exists( $slug, $local_cp_themes ) ) {
			switch_theme( $local_cp_themes[ $slug ]['WP_Slug'] );
			$this->add_notice( sprintf( esc_html__( '%1$s activated.', 'classicpress-directory-integration' ), $local_cp_themes[ $slug ]['Name'] ), false );
		}

		wp_safe_redirect( $this->get_redirect_url() );
		exit;
	}

	public function install_action() {
		if ( ! isset( $_GET['action'] ) || $_GET['action'] !== 'install' ) return;
		if ( ! check_admin_referer( 'install', 'cpdi' ) || ! current_user_can( 'install_themes' ) ) return;
		if ( ! isset( $_REQUEST['slug'] ) ) return;

		$slug = sanitize_key( wp_unslash( $_REQUEST['slug'] ) );
		$response = self::do_directory_request( array( 'byslug' => $slug, '_fields' => 'meta,title' ), 'themes' );

		if ( ! $response['success'] || ! isset( $response['response'][0]['meta']['download_link'] ) ) {
			$this->add_notice( esc_html__( 'API error for theme.', 'classicpress-directory-integration' ), true );
			wp_safe_redirect( $this->get_redirect_url() );
			exit;
		}

		$theme_data = $response['response'][0];
		$installation_url = $theme_data['meta']['download_link'];
		$theme_name       = $theme_data['title']['rendered'];

		if ( ! empty( $theme_data['meta']['parent_theme'] ) ) {
			$parent_slug = sanitize_key( $theme_data['meta']['parent_theme'] );
			$local_themes = wp_get_themes();

			if ( ! array_key_exists( $parent_slug, $local_themes ) ) {
				$parent_response = self::do_directory_request( array( 'byslug' => $parent_slug, '_fields' => 'meta,title' ), 'themes' );
				if ( $parent_response['success'] && isset( $parent_response['response'][0]['meta']['download_link'] ) ) {
					$parent_skin     = new \Automatic_Upgrader_Skin(); // Silent skin
					$parent_upgrader = new \Theme_Upgrader( $parent_skin );
					$parent_install  = $parent_upgrader->install( $parent_response['response'][0]['meta']['download_link'] );
					if ( $parent_install !== true ) {
						$this->add_notice( sprintf( esc_html__( 'Required parent theme %s failed to install.', 'classicpress-directory-integration' ), $parent_response['response'][0]['title']['rendered'] ), true );
						wp_safe_redirect( $this->get_redirect_url() );
						exit;
					}
				} else {
					$this->add_notice( esc_html__( 'API error: Could not find required parent theme in the directory.', 'classicpress-directory-integration' ), true );
					wp_safe_redirect( $this->get_redirect_url() );
					exit;
				}
			}
		}

		$skin     = new ThemeInstallSkin( array( 'type' => 'theme' ) );
		$upgrader = new \Theme_Upgrader( $skin );
		$install  = $upgrader->install( $installation_url );

		if ( $install !== true ) {
			$this->add_notice( sprintf( esc_html__( 'Error installing %s.', 'classicpress-directory-integration' ), $theme_name ), true );
		} else {
			$this->add_notice( sprintf( esc_html__( '%s installed successfully.', 'classicpress-directory-integration' ), $theme_name ), false );
		}

		wp_safe_redirect( $this->get_redirect_url() );
		exit;
	}
}
