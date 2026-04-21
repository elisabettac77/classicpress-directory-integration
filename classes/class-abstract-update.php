<?php
/**
 * Abstract Update Class
 *
 * @package ClassicPress\Directory\Integration
 * @since   1.1.0
 */

namespace ClassicPress\Directory\Integration;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract class for managing directory updates.
 */
abstract class Abstract_Update {

	/**
	 * Cached directory data.
	 *
	 * @var array|false
	 */
	protected array|false $cp_items_directory_data = false;

	/**
	 * Cached items list.
	 *
	 * @var array|false
	 */
	protected array|false $cp_items = false;

	/**
	 * Type of update ('plugins' or 'themes').
	 *
	 * @var string
	 */
	protected string $type = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		// e.g. update_plugins_directory.classicpress.net or update_themes_...
		$host        = wp_parse_url( \CLASSICPRESS_DIRECTORY_INTEGRATION_URL, PHP_URL_HOST );
		$update_hook = 'update_' . $this->type . '_' . $host;

		add_filter( $update_hook, array( $this, 'update_uri_filter' ), 10, 4 );

		// Hook to clear transient cache when items are activated/switched.
		add_action( 'activated_plugin', array( $this, 'refresh_cp_directory_data' ) );
		add_action( 'switch_theme', array( $this, 'refresh_cp_directory_data' ) );
	}

	/**
	 * Get installed CP items.
	 *
	 * @return array
	 */
	abstract protected function get_cp_items(): array;

	/**
	 * Filter the update URI response.
	 *
	 * @param array|false $update      Update data.
	 * @param array       $item_data   Item data.
	 * @param string      $item_file   Item file.
	 * @param array       $locales     Locales.
	 * @return array|false
	 */
	abstract public function update_uri_filter( array|false $update, array $item_data, string $item_file, array $locales ): array|false;

	/**
	 * Refresh directory data.
	 * * @return void
	 */
	public function refresh_cp_directory_data(): void {
		$this->get_directory_data( true );
	}

	/**
	 * Get directory data for current items.
	 *
	 * @param bool $force Force fresh fetch.
	 * @return array
	 */
	protected function get_directory_data( bool $force = false ): array {
		if ( ! $force && false !== $this->cp_items_directory_data ) {
			return $this->cp_items_directory_data;
		}

		$transient_key                 = 'cpdi_directory_data_' . $this->type;
		$this->cp_items_directory_data = get_transient( $transient_key );

		if ( ! $force && false !== $this->cp_items_directory_data ) {
			return $this->cp_items_directory_data;
		}

		$items = $this->get_cp_items();
		if ( empty( $items ) ) {
			return array();
		}

		$endpoint = \CLASSICPRESS_DIRECTORY_INTEGRATION_URL . $this->type . '?byslug=' . implode( ',', array_keys( $items ) ) . '&_fields=meta';

		$response = wp_remote_get(
			$endpoint,
			array(
				'user-agent' => classicpress_user_agent( true ),
			)
		);

		if ( is_wp_error( $response ) || empty( $response['response'] ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return array();
		}

		$data_from_dir = json_decode( wp_remote_retrieve_body( $response ), true );
		$data          = array();

		if ( ! is_array( $data_from_dir ) ) {
			return array();
		}

		foreach ( $data_from_dir as $single_data ) {
			if ( ! isset( $single_data['meta']['slug'] ) ) {
				continue;
			}

			$data[ $single_data['meta']['slug'] ] = array(
				'Download'        => $single_data['meta']['download_link'] ?? '',
				'Version'         => $single_data['meta']['current_version'] ?? '',
				'RequiresPHP'     => $single_data['meta']['requires_php'] ?? '',
				'RequiresCP'      => $single_data['meta']['requires_cp'] ?? '',
				'active_installs' => $single_data['meta']['active_installations'] ?? 0,
				// Include parent_theme for Phase 2 child theme support
				'ParentSlug'      => $single_data['meta']['parent_theme'] ?? '',
			);
		}

		$this->cp_items_directory_data = $data;
		set_transient( $transient_key, $this->cp_items_directory_data, 3 * HOUR_IN_SECONDS );

		return $this->cp_items_directory_data;
	}
}
