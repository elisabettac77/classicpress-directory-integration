<?php
namespace ClassicPress\Directory;

abstract class Abstract_Update {
	protected $cp_items_directory_data = false;
	protected $cp_items                = false;
	protected $type                    = ''; // 'plugins' or 'themes'

	public function __construct() {
		// e.g. update_plugins_directory.classicpress.net or update_themes_...
		$update_hook = 'update_' . $this->type . '_' . wp_parse_url( \CLASSICPRESS_DIRECTORY_INTEGRATION_URL, PHP_URL_HOST );
		add_filter( $update_hook, array( $this, 'update_uri_filter' ), 10, 4 );
		
		// Hook to clear transient cache when items are activated
		add_action( 'activated_plugin', array( $this, 'refresh_cp_directory_data' ) );
		add_action( 'switch_theme', array( $this, 'refresh_cp_directory_data' ) );
	}

	// --------------------------------------------------------
	// Abstract Methods
	// --------------------------------------------------------
	abstract protected function get_cp_items();
	abstract public function update_uri_filter( $update, $item_data, $item_file, $locales );

	// --------------------------------------------------------
	// Deduplicated Shared Logic
	// --------------------------------------------------------
	public function refresh_cp_directory_data() {
		$this->get_directory_data( true );
	}

	protected function get_directory_data( $force = false ) {
		if ( ! $force && $this->cp_items_directory_data !== false ) {
			return $this->cp_items_directory_data;
		}
		
		$transient_key = 'cpdi_directory_data_' . $this->type;
		$this->cp_items_directory_data = get_transient( $transient_key );
		
		if ( ! $force && $this->cp_items_directory_data !== false ) {
			return $this->cp_items_directory_data;
		}

		$items = $this->get_cp_items();
		if ( empty( $items ) ) {
			return array();
		}

		$endpoint = \CLASSICPRESS_DIRECTORY_INTEGRATION_URL . $this->type . '?byslug=' . implode( ',', array_keys( $items ) ) . '&_fields=meta';
		$response = wp_remote_get( $endpoint, array( 'user-agent' => classicpress_user_agent( true ) ) );

		if ( is_wp_error( $response ) || empty( $response['response'] ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return array();
		}

		$data_from_dir = json_decode( wp_remote_retrieve_body( $response ), true );
		$data          = array();

		foreach ( $data_from_dir as $single_data ) {
			$data[ $single_data['meta']['slug'] ] = array(
				'Download'        => $single_data['meta']['download_link'],
				'Version'         => $single_data['meta']['current_version'],
				'RequiresPHP'     => $single_data['meta']['requires_php'],
				'RequiresCP'      => $single_data['meta']['requires_cp'],
				'active_installs' => $single_data['meta']['active_installations'],
				// Include parent_theme for Phase 2 child theme support
				'ParentSlug'      => isset( $single_data['meta']['parent_theme'] ) ? $single_data['meta']['parent_theme'] : '', 
			);
		}

		$this->cp_items_directory_data = $data;
		set_transient( $transient_key, $this->cp_items_directory_data, 3 * \HOUR_IN_SECONDS );
		return $this->cp_items_directory_data;
	}
}
