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
			$endpoint = add_query_arg( $param, rawurlencode( $search ), $endpoint );
		}

		$items = $this->fetch_directory_data( $endpoint );

		if ( is_wp_error( $items ) ) {
			wp_send_json_error( array( 'message' => $items->get_error_message() ) );
		}

		// Sort only on the first page of results.
		if ( 1 === $page && is_array( $items ) ) {
			$items = $this->sort_items_by_status( $items, $type );
		}

		ob_start();
		foreach ( (array) $items as $item ) {
			if ( 'plugin' === $type ) {
				$this->render_plugin_card( $item ); 
			} else {
				$this->render_theme_card( $item );
			}
		}
		$html = ob_get_clean();

		wp_send_json_success( array(
			'html'      => $html,
			'next_page' => is_array( $items ) && count( $items ) === 20 ? $page + 1 : false,
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
	 * Safe API fetcher.
	 */
	protected function fetch_directory_data( string $endpoint ) {
		$response = wp_remote_get( $endpoint, array( 'user-agent' => classicpress_user_agent( true ) ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new \WP_Error( 'api_error', sprintf( __( 'Directory returned code %d', 'classicpress-directory-integration' ), $code ) );
		}

		return json_decode( wp_remote_retrieve_body( $response ), true );
	}

	/**
	 * FIX FOR FATAL ERROR: Sort items by status.
	 */
	protected function sort_items_by_status( array $items, string $type ): array {
		usort( $items, function( $a, $b ) use ( $type ) {
			$status_a = $this->get_item_status( $a['slug'] ?? '', $type );
			$status_b = $this->get_item_status( $b['slug'] ?? '', $type );

			$priority = [
				'active'        => 1,
				'inactive'      => 2,
				'not-installed' => 3,
			];

			return ($priority[$status_a] ?? 4) <=> ($priority[$status_b] ?? 4);
		});
		return $items;
	}

	/**
	 * Get the current status of a plugin or theme.
	 */
	protected function get_item_status( string $slug, string $type ): string {
		if ( 'plugin' === $type ) {
			$path = $this->get_plugin_main_file( $slug );
			if ( ! $path ) return 'not-installed';
			return is_plugin_active( $path ) ? 'active' : 'inactive';
		} else {
			$theme = wp_get_theme( $slug );
			if ( ! $theme->exists() ) return 'not-installed';
			return ( get_stylesheet() === $slug ) ? 'active' : 'inactive';
		}
	}

	/**
	 * Determine the taxonomy key based on type.
	 */
	protected function get_taxonomy_key( string $type ): string {
		return 'plugin' === $type ? 'plugin_category' : 'theme_category';
	}

	/**
	 * SVG Placeholder for items without banners.
	 */
	protected function get_svg_placeholder( string $slug ): string {
		return 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="250" viewBox="0 0 400 250"><rect width="400" height="250" fill="#eee"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-family="sans-serif" font-size="20" fill="#999">' . esc_html( $slug ) . '</text></svg>' );
	}

	/**
	 * Helper to find the main plugin file by slug.
	 */
	private function get_plugin_main_file( string $slug ): ?string {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins = get_plugins();
		foreach ( array_keys( $plugins ) as $file ) {
			if ( dirname( $file ) === $slug || $file === $slug . '.php' ) {
				return $file;
			}
		}
		return null;
	}

	/**
	 * Renders the content for the details side-drawer.
	 */
	protected function render_drawer_content( array $item, string $type ): void {
		$title   = $item['title']['rendered'] ?? '';
		$content = $item['content']['rendered'] ?? '';
		$version = $item['meta']['current_version'] ?? '0.0.0';
		$author  = $item['meta']['author'] ?? '';
		
		echo '<h2>' . esc_html( $title ) . '</h2>';
		echo '<p class="cpdi-drawer-meta"><strong>' . esc_html__( 'Version:', 'classicpress-directory-integration' ) . '</strong> ' . esc_html( $version ) . ' | <strong>' . esc_html__( 'Author:', 'classicpress-directory-integration' ) . '</strong> ' . esc_html( $author ) . '</p>';
		echo '<div class="cpdi-drawer-body">' . wp_kses_post( $content ) . '</div>';
	}
}
