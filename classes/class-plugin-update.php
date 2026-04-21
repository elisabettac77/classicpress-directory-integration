<?php
/**
 * Plugin Update Class
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
 * Handles update logic for ClassicPress Directory plugins.
 */
class Plugin_Update extends Abstract_Update {

	/**
	 * Set the type for the abstract parent.
	 *
	 * @var string
	 */
	protected string $type = 'plugins';

	/**
	 * Identify plugins installed from the CP Directory.
	 *
	 * @return array List of plugins using the CP Directory Update URI.
	 */
	protected function get_cp_items(): array {
		if ( false !== $this->cp_items ) {
			return $this->cp_items;
		}

		$all_plugins     = get_plugins();
		$this->cp_items = array();
		$directory_host  = wp_parse_url( \CLASSICPRESS_DIRECTORY_INTEGRATION_URL, PHP_URL_HOST );

		foreach ( $all_plugins as $file => $data ) {
			$update_uri = $data['UpdateURI'] ?? '';

			// If the UpdateURI matches our directory host, it's one of ours.
			if ( ! empty( $update_uri ) && str_contains( $update_uri, $directory_host ) ) {
				$slug                    = dirname( $file );
				$this->cp_items[ $slug ] = $data;
			}
		}

		return $this->cp_items;
	}

	/**
	 * Filter the update response for CP Directory plugins.
	 */
	public function update_uri_filter( array|false $update, array $item_data, string $item_file, array $locales ): array|false {
		$slug = dirname( $item_file );
		$data = $this->get_directory_data();

		// If we don't have directory data for this slug, skip it.
		if ( ! isset( $data[ $slug ] ) ) {
			return $update;
		}

		$remote = $data[ $slug ];

		// If a newer version exists, return the update package.
		if ( version_compare( $item_data['Version'], $remote['Version'], '<' ) ) {
			return array(
				'version'      => $remote['Version'],
				'package'      => $remote['Download'],
				'requires_php' => $remote['RequiresPHP'],
				'requires_cp'  => $remote['RequiresCP'],
			);
		}

		return $update;
	}
}
