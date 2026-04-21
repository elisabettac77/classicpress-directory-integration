<?php
/**
 * Theme Update Class
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
 * Handles update logic for ClassicPress Directory themes.
 */
class Theme_Update extends Abstract_Update {

	/**
	 * Set the type for the abstract parent.
	 *
	 * @var string
	 */
	protected string $type = 'themes';

	/**
	 * Identify themes installed from the CP Directory.
	 *
	 * @return array List of themes using the CP Directory Update URI.
	 */
	protected function get_cp_items(): array {
		if ( false !== $this->cp_items ) {
			return $this->cp_items;
		}

		$all_themes     = wp_get_themes();
		$this->cp_items = array();
		$directory_host = wp_parse_url( \CLASSICPRESS_DIRECTORY_INTEGRATION_URL, PHP_URL_HOST );

		foreach ( $all_themes as $slug => $theme ) {
			$update_uri = $theme->get( 'Update URI' );

			if ( ! empty( $update_uri ) && str_contains( $update_uri, $directory_host ) ) {
				$this->cp_items[ $slug ] = array(
					'Version' => $theme->get( 'Version' ),
					'Name'    => $theme->get( 'Name' ),
				);
			}
		}

		return $this->cp_items;
	}

	/**
	 * Filter the update response for CP Directory themes.
	 */
	public function update_uri_filter( array|false $update, array $item_data, string $item_file, array $locales ): array|false {
		$slug = $item_file; // In themes, $item_file is usually the slug.
		$data = $this->get_directory_data();

		if ( ! isset( $data[ $slug ] ) ) {
			return $update;
		}

		$remote = $data[ $slug ];

		if ( version_compare( $item_data['Version'], $remote['Version'], '<' ) ) {
			return array(
				'version'     => $remote['Version'],
				'package'     => $remote['Download'],
				'requires_php' => $remote['RequiresPHP'],
				'requires_cp'  => $remote['RequiresCP'],
			);
		}

		return $update;
	}
}
