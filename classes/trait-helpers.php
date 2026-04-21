<?php
/**
 * Helpers Trait
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
 * Shared utility methods for the directory integration.
 */
trait Helpers {

	/**
	 * AJAX Handler for fetching items.
	 */
	public function ajax_fetch_items(): void {
		check_ajax_referer( 'cpdi_ajax_nonce' );

		// Security: Unslash before sanitizing
		$type   = isset( $_GET['type'] ) ? sanitize_text_field( wp_unslash( $_GET['type'] ) ) : 'plugin';
		$page   = isset( $_GET['page'] ) ? absint( wp_unslash( $_GET['page'] ) ) : 1;
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$stype  = isset( $_GET['stype'] ) ? sanitize_text_field( wp_unslash( $_GET['stype'] ) ) : 'keyword';

		$endpoint = \CLASSICPRESS_DIRECTORY_INTEGRATION_URL . ( 'plugin' === $type ? 'plugins' : 'themes' );
		$endpoint = add_query_arg( array(
			'per_page' => 20,
			'page'     => $page,
			'_fields'  => 'id,slug,title,meta,content',
		), $endpoint );

		if ( ! empty( $search ) ) {
			$tax_key  = $this->get_taxonomy_key( $type );
			$param    = ( 'category' === $stype || 'tag' === $stype ) ? $tax_key : $stype;
			// rawurlencode is preferred over urlencode
			$endpoint = add_query_arg( $param, rawurlencode( $search ), $endpoint );
		}

		$items = $this->fetch_directory_data( $endpoint );

		if ( is_wp_error( $items ) ) {
			wp_send_json_error( array( 'message' => $items->get_error_message() ) );
		}

		if ( 1 === $page ) {
			$items = $this->sort_items_by_status( $items, $type );
		}

		ob_start();
		foreach ( $items as $item ) {
			if ( 'plugin' === $type ) {
				$this->render_plugin_card( $item ); 
			} else {
				$this->render_theme_card( $item );
			}
		}
		$html = ob_get_clean();

		wp_send_json_success( array(
			'html'      => $html,
			'next_page' => count( $items ) === 20 ? $page + 1 : false,
		) );
	}

	/**
	 * AJAX Handler for the Details Drawer.
	 */
	public function ajax_get_details(): void {
		check_ajax_referer( 'cpdi_ajax_nonce' );

		$slug = isset( $_GET['slug'] ) ? sanitize_text_field( wp_unslash( $_GET['slug'] ) ) : '';
		$type = isset( $_GET['type'] ) ? sanitize_text_field( wp_unslash( $_GET['type'] ) ) : 'plugin';

		if ( empty( $slug ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing slug.', 'classicpress-directory-integration' ) ) );
		}

		$endpoint = \CLASSICPRESS_DIRECTORY_INTEGRATION_URL . ( 'plugin' === $type ? 'plugins' : 'themes' );
		$endpoint = add_query_arg( 'slug', $slug, $endpoint );

		$data = $this->fetch_directory_data( $endpoint );
		$item = ( is_array( $data ) && ! empty( $data ) ) ? $data[0] : null;

		if ( ! $item ) {
			wp_send_json_error( array( 'message' => __( 'Item not found.', 'classicpress-directory-integration' ) ) );
		}

		ob_start();
		$this->render_drawer_content( $item, $type );
		$html = ob_get_clean();

		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * Safe API fetcher with error handling.
	 */
	protected function fetch_directory_data( string $endpoint ) {
		$response = wp_remote_get( $endpoint, array( 'user-agent' => classicpress_user_agent( true ) ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			/* translators: %d: HTTP response code */
			return new \WP_Error( 'api_error', sprintf( __( 'Directory returned code %d', 'classicpress-directory-integration' ), $code ) );
		}

		return json_decode( wp_remote_retrieve_body( $response ), true );
	}

	/**
	 * Polyfill for json_validate.
	 * * @param string $json The JSON string.
	 * @return bool
	 */
	private static function json_validate( string $json ): bool {
		if ( function_exists( 'json_validate' ) ) {
			return \json_validate( $json );
		}
		json_decode( $json );
		return json_last_error() === JSON_ERROR_NONE;
	}
    
    // ... (rest of the helper methods using 'classicpress-directory-integration' text domain)
}
