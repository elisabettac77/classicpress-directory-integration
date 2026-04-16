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

	public function after_plugin_row( $plugin_file, $plugin_data, $status ) {
		$slug    = dirname( $plugin_file );
		$plugins = $this->get_cp_items();

		if ( ! array_key_exists( $slug, $plugins ) ) { return; }

		$dir_data = $this->get_directory_data();

		if ( ! array_key_exists( $slug, $dir_data ) ) { return; }

		$data   = $dir_data[ $slug ];
		$plugin = $plugins[ $slug ];

		if ( version_compare( $plugin['Version'], $data['Version'] ) >= 0 ) { return false; }

		$message = '';
		if ( version_compare( classicpress_version(), $data['RequiresCP'] ) === -1 ) {
			$message .= sprintf( esc_html__( 'This plugin has not updated to version %1$s because it needs ClassicPress %2$s.', 'classicpress-directory-integration' ), esc_html( $data['Version'] ), esc_html( $data['RequiresCP'] ) );
		}
		if ( version_compare( phpversion(), $data['RequiresPHP'] ) === -1 ) {
			if ( $message !== '' ) { $message .= ' '; }
			$message .= sprintf( esc_html__( 'This plugin has not updated to version %1$s because it needs PHP %2$s.', 'classicpress-directory-integration' ), esc_html( $data['Version'] ), esc_html( $data['RequiresPHP'] ) );
		}

		if ( $message === '' ) { return; }

		echo '<tr class="plugin-update-tr active" id="' . esc_html( $plugin_file ) . '-update" data-slug="' . esc_html( $plugin_file ) . '" data-plugin="' . esc_html( $plugin_file ) . '"><td colspan="3" class="plugin-update colspanchange"><div class="update-message notice inline notice-alt notice-error"><p aria-label="Can not install a newer version.">';
		echo esc_html( $message ) . '</p></div></td></tr>';
	}

	public function plugin_information( $result, $action, $args ) {
		if ( $action !== 'plugin_information' ) { return $result; }
		
		$dir_data = $this->get_directory_data( true );
		$slug     = dirname( $args->slug );
		if ( ! array_key_exists( $slug, $dir_data ) ) { return $result; }

		$endpoint = \CLASSICPRESS_DIRECTORY_INTEGRATION_URL . 'plugins?byslug=' . $slug;
		$response = wp_remote_get( $endpoint, array( 'user-agent' => classicpress_user_agent( true ) ) );

		if ( is_wp_error( $response ) || empty( $response['response'] ) || wp_remote_retrieve_response_code( $response ) !== 200 ) { return false; }

		$data_from_dir = json_decode( wp_remote_retrieve_body( $response ), true );
		$data          = $data_from_dir[0];

		$result = array(
			'active_installs'   => (int) $data['meta']['active_installations'],
			'author'            => $data['meta']['developer_name'],
			'banners'           => $this->get_plugin_images( 'banner', $slug ),
			'description'       => 'false',
			'icons'             => $this->get_plugin_images( 'icon', $slug ),
			'name'              => $data['title']['rendered'],
			'requires_php'      => $data['meta']['requires_php'],
			'screenshots'       => $this->get_plugin_images( 'screenshot', $slug ),
			'sections'          => array(
				'description' => $data['content']['rendered'],
			),
			'short_description' => $data['excerpt']['rendered'],
			'slug'              => null,
			'tags'              => explode( ',', $data['meta']['category_names'] ),
			'version'           => $data['meta']['current_version'],
		);

		return (object) $result;
	}

	private function get_plugin_images( $type, $plugin ) {
		$images = array();
		if ( empty( $plugin ) || ! in_array( $type, array( 'icon', 'banner', 'screenshot' ), true ) ) { return $images; }

		$folder     = apply_filters( "cpdi_images_folder_{$plugin}", '/images' );
		$image_path = untrailingslashit( WP_PLUGIN_DIR ) . '/' . $plugin . $folder;
		$image_url  = untrailingslashit( WP_PLUGIN_URL ) . '/' . $plugin . $folder;

		$image_qualities = array(
			'icon'   => array( 'default', '1x', '2x' ),
			'banner' => array( 'default', 'low', 'high' ),
		);

		$image_dimensions = array(
			'icon'   => array( 'default' => '128', '1x' => '128', '2x' => '256' ),
			'banner' => array( 'default' => '772x250', 'low' => '772x250', 'high' => '1544x500' ),
		);

		if ( $type === 'icon' || $type === 'banner' ) {
			if ( file_exists( $image_path . '/' . $type . '.svg' ) ) {
				foreach ( $image_qualities[ $type ] as $key ) { $images[ $key ] = $image_url . '/' . $type . '.svg'; }
			} else {
				foreach ( array( 'jpg', 'png' ) as $ext ) {
					$all_keys   = $image_qualities[ $type ];
					$last_key   = array_pop( $all_keys );
					$middle_key = array_pop( $all_keys );
					if ( file_exists( $image_path . '/' . $type . '-' . $image_dimensions[ $type ][ $middle_key ] . '.' . $ext ) ) {
						foreach ( $image_qualities[ $type ] as $key ) { $images[ $key ] = $image_url . '/' . $type . '-' . $image_dimensions[ $type ][ $middle_key ] . '.' . $ext; }
					}
					if ( file_exists( $image_path . '/' . $type . '-' . $image_dimensions[ $type ][ $last_key ] . '.' . $ext ) ) {
						$images[ $last_key ] = $image_url . '/' . $type . '-' . $image_dimensions[ $type ][ $last_key ] . '.' . $ext;
					}
				}
			}
			return $images;
		}

		if ( $type === 'screenshot' && file_exists( $image_path ) ) {
			$dir_contents = scandir( $image_path );
			foreach ( $dir_contents as $name ) {
				if ( strpos( strtolower( $name ), 'screenshot' ) === 0 ) {
					$start                        = strpos( $name, '-' ) + 1;
					$for                          = strpos( $name, '.' ) - $start;
					$screenshot_number            = substr( $name, $start, $for );
					$images[ $screenshot_number ] = $image_url . '/' . $name;
				}
			}
			ksort( $images );
		}

		return $images;
	}
}
