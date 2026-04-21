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
 * Handles checking and injecting theme updates from the CP Directory.
 */
class Theme_Update {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'site_transient_update_themes', array( $this, 'check_for_updates' ) );
		add_filter( 'pre_set_site_transient_update_themes', array( $this, 'check_for_updates' ) );
	}

	/**
	 * Check CP Directory for theme updates.
	 * * @param object $transient The update transient.
	 * @return object
	 */
	public function check_for_updates( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		foreach ( $transient->checked as $slug => $version ) {
			// Only check themes that we know belong to the CP Directory.
			// (You might want to add a check here to skip core themes like twentyten).
			
			$remote = $this->get_remote_theme_data( $slug );

			if ( $remote && version_compare( $version, $remote['new_version'], '<' ) ) {
				$transient->response[ $slug ] = $remote;
			}
		}

		return $transient;
	}

	/**
	 * Fetch remote theme data.
	 * * @param string $slug Theme slug.
	 * @return array|bool Array of data or false on failure.
	 */
	private function get_remote_theme_data( $slug ) {
		$endpoint = \CLASSICPRESS_DIRECTORY_INTEGRATION_URL . 'themes?slug=' . rawurlencode( $slug );
		
		$response = wp_remote_get( $endpoint, array(
			'user-agent' => classicpress_user_agent( true ),
		) );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $data ) || ! is_array( $data ) ) {
			return false;
		}

		$theme = $data[0];

		return array(
			'theme'       => $slug,
			'new_version' => $theme['meta']['current_version'] ?? '0.0.0',
			'url'         => $theme['link'] ?? '',
			'package'     => $theme['meta']['download_link'] ?? '',
			'requires'    => $theme['meta']['requires_cp'] ?? '',
			'requires_php' => $theme['meta']['requires_php'] ?? '',
		);
	}
}
