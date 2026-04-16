<?php
namespace ClassicPress\Directory;

class ThemeInstall extends Abstract_Install {

	public function __construct() {
		$this->type = 'themes';
		parent::__construct();
	}

	public function create_menu() {
		// BUGFIX: Changed from install_plugins to install_themes
		if ( ! current_user_can( 'install_themes' ) ) { return; }
		// ... add_submenu_page logic using 'themes.php' and 'install_themes'
	}

	public function rename_menu() {
		if ( ! current_user_can( 'install_themes' ) ) { return; }
		// ... theme specific menu renaming
	}

	protected function get_local_cp_items() {
		if ( $this->local_cp_items !== false ) { return $this->local_cp_items; }
		
		$all_themes = wp_get_themes();
		$cp_themes  = array();
		foreach ( $all_themes as $slug => $theme ) {
			$cp_themes[ $slug ] = array(
				'WPSlug'   => $slug,
				'Name'     => $theme->get( 'Name' ),
				'Version'  => $theme->get( 'Version' ),
				'ThemeURI' => $theme->get( 'ThemeURI' ),
				'Active'   => get_template() === $slug || get_stylesheet() === $slug,
			);
		}
		
		$this->local_cp_items = $cp_themes;
		return $this->local_cp_items;
	}

	public function activate_action() {
		// ... theme activation logic using switch_theme()
	}

	public function install_action() {
		// Security checks...
		if ( ! isset( $_GET['action'] ) || $_GET['action'] !== 'install' ) { return; }
		if ( ! check_admin_referer( 'install', '_cpdi' ) ) { return; }
		if ( ! current_user_can( 'install_themes' ) ) { return; }
		if ( ! isset( $_REQUEST['slug'] ) ) { return; }

		$slug = sanitize_key( wp_unslash( $_REQUEST['slug'] ) );

		// 1. Get Theme data from the API
		$args     = array( 'byslug' => $slug, '_fields' => 'meta,title' );
		$response = $this::do_directory_request( $args, 'themes' );

		if ( ! $response['success'] || ! isset( $response['response'][0]['meta']['download_link'] ) ) {
			$this->add_notice( esc_html__( 'API error for theme.', 'classicpress-directory-integration' ), true );
			wp_safe_redirect( remove_query_arg( array( 'action', 'slug', '_cpdi' ), wp_get_referer() ) );
			exit;
		}

		$theme_data = $response['response'][0];
		$installation_url = $theme_data['meta']['download_link'];
		$theme_name       = $theme_data['title']['rendered'];

		// 2. CHECK API DATA FOR CHILD THEME DEPENDENCY
		if ( ! empty( $theme_data['meta']['parent_slug'] ) ) {
			$parent_slug = sanitize_key( $theme_data['meta']['parent_slug'] );
			
			// 3. Check local filesystem if parent is missing
			$local_themes = wp_get_themes();
			
			if ( ! array_key_exists( $parent_slug, $local_themes ) ) {
				// Parent is missing. Query API for Parent.
				$parent_args     = array( 'byslug' => $parent_slug, '_fields' => 'meta,title' );
				$parent_response = $this::do_directory_request( $parent_args, 'themes' );
				
				if ( $parent_response['success'] && isset( $parent_response['response'][0]['meta']['download_link'] ) ) {
					// Install Parent silently
					$parent_skin     = new \Automatic_Upgrader_Skin(); // Silent skin so it doesn't interrupt flow
					$parent_upgrader = new \Theme_Upgrader( $parent_skin );
					$parent_install  = $parent_upgrader->install( $parent_response['response'][0]['meta']['download_link'] );
					
					if ( $parent_install !== true ) {
						// Translators: %s is the parent theme name.
						$this->add_notice( sprintf( esc_html__( 'Required parent theme %s failed to install.', 'classicpress-directory-integration' ), $parent_response['response'][0]['title']['rendered'] ), true );
						wp_safe_redirect( remove_query_arg( array( 'action', 'slug', '_cpdi' ), wp_get_referer() ) );
						exit;
					}
				} else {
					$this->add_notice( esc_html__( 'API error: Could not find required parent theme in the directory.', 'classicpress-directory-integration' ), true );
					wp_safe_redirect( remove_query_arg( array( 'action', 'slug', '_cpdi' ), wp_get_referer() ) );
					exit;
				}
			}
		}

		// 4. Install the actual requested theme
		$skin     = new ThemeInstallSkin( array( 'type' => 'theme' ) );
		$upgrader = new \Theme_Upgrader( $skin );
		$install  = $upgrader->install( $installation_url );

		if ( $install !== true ) {
			$this->add_notice( sprintf( esc_html__( 'Error installing %s.', 'classicpress-directory-integration' ), $theme_name ), true );
		} else {
			$this->add_notice( sprintf( esc_html__( '%s installed successfully.', 'classicpress-directory-integration' ), $theme_name ), false );
		}

		wp_safe_redirect( remove_query_arg( array( 'action', 'slug', '_cpdi' ), wp_get_referer() ) );
		exit;
	}
}
