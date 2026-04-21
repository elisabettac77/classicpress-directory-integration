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

		$all_plugins    = get_plugins();
		$this->cp_items = array();
		$directory_host = wp_parse_url( \CLASSICPRESS_DIRECTORY_INTEGRATION_URL, PHP_URL_HOST );

		foreach ( $all_plugins as $file => $data ) {
			$update_uri = $data['UpdateURI'] ?? '';

			// If the UpdateURI matches our directory host, it's one of ours.
			// Using strpos for PHP 7.4 compatibility instead of str_contains.
			if ( ! empty( $update_uri ) && false !== strpos( $update_uri, $directory_host ) ) {
				$slug                    = dirname( $file );
				$this->cp_items[ $slug ] = $data;
			}
		}

		return $this->cp_items;
	}

	/**
	 * Filter the update response for CP Directory plugins.
	 * * @param array|bool  $update    The update data, or false.
	 * @param array       $item_data The item data.
	 * @param string      $item_file The item file path.
	 * @param array       $locales   The locales.
	 * @return array|bool
	 */
	public function update_uri_filter( $update, array $item_data, string $item_file, array $locales ) {
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
