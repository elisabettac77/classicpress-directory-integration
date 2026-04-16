<?php
namespace ClassicPress\Directory;

class PluginUpdate extends Abstract_Update {

	public function __construct() {
		$this->type = 'plugins';
		parent::__construct();

		// Plugin specific UI hooks
		add_filter( 'after_plugin_row', array( $this, 'after_plugin_row' ), 100, 3 );
		add_filter( 'plugins_api_result', array( $this, 'plugin_information' ), 100, 3 );
	}

	protected function get_cp_items() {
		if ( $this->cp_items !== false ) { return $this->cp_items; }

		$all_plugins = get_plugins();
		$cp_plugins  = array();
		foreach ( $all_plugins as $slug => $plugin ) {
			if ( ! array_key_exists( 'UpdateURI', $plugin ) ) { continue; }
			if ( strpos( $plugin['UpdateURI'], \CLASSICPRESS_DIRECTORY_INTEGRATION_URL ) !== 0 ) { continue; }
			
			$cp_plugins[ dirname( $slug ) ] = array(
				'WPSlug'      => $slug,
				'Version'     => $plugin['Version'],
				'RequiresPHP' => array_key_exists( 'RequiresPHP', $plugin ) ? $plugin['RequiresPHP'] : null,
				'RequiresCP'  => array_key_exists( 'RequiresCP', $plugin ) ? $plugin['RequiresCP'] : null,
				'PluginURI'   => array_key_exists( 'PluginURI', $plugin ) ? $plugin['PluginURI'] : null,
			);
		}

		$this->cp_items = $cp_plugins;
		return $this->cp_items;
	}

	public function update_uri_filter( $update, $plugin_data, $plugin_file, $locales ) {
		if ( preg_match( '/plugins\?byslug=(.*)/', $plugin_data['UpdateURI'], $matches ) !== 1 ) { return false; }
		if ( ! isset( $matches[1] ) || dirname( $plugin_file ) !== $matches[1] ) { return false; }

		$slug    = $matches[1];
		$plugins = $this->get_cp_items();
		if ( ! array_key_exists( $slug, $plugins ) ) { return false; }

		$dir_data = $this->get_directory_data();
		if ( ! array_key_exists( $slug, $dir_data ) ) { return false; }

		$plugin = $plugins[ $slug ];
		$data   = $dir_data[ $slug ];

		if ( version_compare( $plugin['Version'], $data['Version'] ) >= 0 ) { return false; }
		if ( version_compare( classicpress_version(), $data['RequiresCP'] ) === -1 ) { return false; }
		if ( version_compare( phpversion(), $data['RequiresPHP'] ) === -1 ) { return false; }

		return array(
			'slug'        => $plugin_file,
			'version'     => $data['Version'],
			'package'     => $data['Download'],
			'requiresphp' => $data['RequiresPHP'],
			'requirescp'  => $data['RequiresCP'],
			'banners'     => $this->get_plugin_images( 'banner', $slug ),
			'icons'       => $this->get_plugin_images( 'icon', $slug ),
		);
	}

	// Move get_plugin_images(), plugin_information(), and after_plugin_row() here directly from the old class
}
