<?php
namespace ClassicPress\Directory;

class ThemeUpdate extends Abstract_Update {

	public function __construct() {
		$this->type = 'themes';
		parent::__construct();
	}

	protected function get_cp_items() {
		if ( $this->cp_items !== false ) { return $this->cp_items; }

		$all_themes = wp_get_themes();
		$cp_themes  = array();
		foreach ( $all_themes as $slug => $theme ) {
			if ( ! $theme->display( 'UpdateURI' ) ) { continue; }
			if ( strpos( $theme->display( 'UpdateURI' ), \CLASSICPRESS_DIRECTORY_INTEGRATION_URL ) !== 0 ) { continue; }

			$cp_themes[ $slug ] = array(
				'WPSlug'      => $slug,
				'Version'     => $theme->display( 'Version' ), // BUGFIX: Was previously UpdateURI
				'RequiresPHP' => $theme->display( 'RequiresPHP' ),
				'RequiresCP'  => $theme->display( 'RequiresCP' ),
				'PluginURI'   => $theme->display( 'ThemeURI' ), // Map ThemeURI to PluginURI format internally
			);
		}

		$this->cp_items = $cp_themes;
		return $this->cp_items;
	}

	public function update_uri_filter( $update, $theme_data, $theme_stylesheet, $locales ) {
		if ( preg_match( '/themes\?byslug=(.*)/', $theme_data['UpdateURI'], $matches ) !== 1 ) { return false; }
		if ( ! isset( $matches[1] ) || $theme_stylesheet !== $matches[1] ) { return false; }

		$slug   = $matches[1];
		$themes = $this->get_cp_items();
		if ( ! array_key_exists( $slug, $themes ) ) { return false; }

		$dir_data = $this->get_directory_data();
		if ( ! array_key_exists( $slug, $dir_data ) ) { return false; }

		$theme = $themes[ $slug ];
		$data  = $dir_data[ $slug ];

		if ( version_compare( $theme['Version'], $data['Version'] ) >= 0 ) { return false; }
		if ( version_compare( classicpress_version(), $data['RequiresCP'] ) === -1 ) { return false; }
		if ( version_compare( phpversion(), $data['RequiresPHP'] ) === -1 ) { return false; }

		// NEW: Child Theme Update API Check
		if ( ! empty( $data['ParentSlug'] ) ) {
			$parent_slug  = $data['ParentSlug'];
			$local_themes = wp_get_themes();
			
			// If it's a child theme, ensure the parent theme actually exists before offering the update
			if ( ! array_key_exists( $parent_slug, $local_themes ) ) {
				return false; // Prevent update if parent dependency is missing
			}
		}

		return array(
			'slug'        => $theme_stylesheet,
			'version'     => $data['Version'],
			'package'     => $data['Download'],
			'requiresphp' => $data['RequiresPHP'],
			'requirescp'  => $data['RequiresCP'],
			'url'         => 'https://' . wp_parse_url( \CLASSICPRESS_DIRECTORY_INTEGRATION_URL, PHP_URL_HOST ) . '/themes/' . $theme_stylesheet,
		);
	}
}
