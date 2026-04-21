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
	 * Get the status of a directory item relative to the local installation.
	 *
	 * @param string $slug The plugin or theme slug.
	 * @param string $type The type ('plugin' or 'theme').
	 * @return string 'active', 'inactive', or 'not-installed'.
	 */
	protected function get_item_status( string $slug, string $type ): string {
		if ( 'plugin' === $type ) {
			$plugins = get_plugins();
			foreach ( $plugins as $file => $data ) {
				if ( dirname( $file ) === $slug || $file === $slug . '.php' ) {
					return is_plugin_active( $file ) ? 'active' : 'inactive';
				}
			}
		} else {
			$theme = wp_get_theme( $slug );
			if ( $theme->exists() ) {
				return ( get_stylesheet() === $slug ) ? 'active' : 'inactive';
			}
		}

		return 'not-installed';
	}

	/**
	 * Sort API results: Active first, then Inactive, then Not Installed.
	 */
	protected function sort_items_by_status( array $items, string $type ): array {
		usort( $items, function( $a, $b ) use ( $type ) {
			$status_a = $this->get_item_status( $a['slug'] ?? '', $type );
			$status_b = $this->get_item_status( $b['slug'] ?? '', $type );

			$priority = array(
				'active'        => 1,
				'inactive'      => 2,
				'not-installed' => 3,
			);

			return $priority[ $status_a ] <=> $priority[ $status_b ];
		});

		return $items;
	}

	/**
	 * Map search taxonomy based on item type.
	 */
	protected function get_taxonomy_key( string $type ): string {
		return ( 'plugin' === $type ) ? 'category' : 'tag';
	}

	/**
	 * Generate a placeholder SVG background based on the item slug.
	 */
	protected function get_svg_placeholder( string $slug ): string {
		$hash  = substr( md5( $slug ), 0, 6 );
		$color = '#' . $hash;
		
		$svg = sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" width="800" height="440" viewBox="0 0 800 440">
				<rect width="800" height="440" fill="%s" />
				<text x="50%%" y="50%%" dominant-baseline="middle" text-anchor="middle" font-family="sans-serif" font-size="40" fill="#fff" opacity="0.5">%s</text>
			</svg>',
			esc_attr( $color ),
			esc_html( strtoupper( substr( $slug, 0, 1 ) ) )
		);

		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
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
			return new \WP_Error( 'api_error', 'Directory returned code ' . $code );
		}

		return json_decode( wp_remote_retrieve_body( $response ), true );
	}

	/**
	 * Get all substrings within text between two specified strings.
	 */
	private function get_markdown_contents( string $str, string $startDelimiter, string $endDelimiter ): array {
		$contents             = array();
		$startDelimiterLength = strlen( $startDelimiter );
		$endDelimiterLength   = strlen( $endDelimiter );
		$startFrom            = 0;

		while ( false !== ( $contentStart = strpos( $str, $startDelimiter, $startFrom ) ) ) {
			$contentStart += $startDelimiterLength;
			$contentEnd    = strpos( $str, $endDelimiter, $contentStart );
			if ( $contentEnd === false ) {
				break;
			}
			$contents[] = substr( $str, $contentStart, $contentEnd - $contentStart );
			$startFrom  = $contentEnd + $endDelimiterLength;
		}

		return $contents;
	}

	/**
	 * Polyfill for json_validate (PHP 8.3+).
	 */
	private static function json_validate( string $json ): bool {
		if ( function_exists( 'json_validate' ) ) {
			return \json_validate( $json );
		}
		json_decode( $json );
		return json_last_error() === JSON_ERROR_NONE;
	}

	/**
	 * Register AJAX actions.
	 */
	public function register_ajax_handlers(): void {
		add_action( 'wp_ajax_cpdi_fetch_items', [ $this, 'ajax_fetch_items' ] );
		add_action( 'wp_ajax_cpdi_get_details', [ $this, 'ajax_get_details' ] );
	}

	/**
	 * AJAX Handler for fetching and searching items.
	 */
	public function ajax_fetch_items(): void {
		check_ajax_referer( 'cpdi_ajax_nonce' );

		$type   = sanitize_text_field( $_GET['type'] ?? 'plugin' );
		$page   = absint( $_GET['page'] ?? 1 );
		$search = sanitize_text_field( $_GET['s'] ?? '' );
		$stype  = sanitize_text_field( $_GET['stype'] ?? 'keyword' );

		$endpoint = \CLASSICPRESS_DIRECTORY_INTEGRATION_URL . ( 'plugin' === $type ? 'plugins' : 'themes' );
		$endpoint = add_query_arg( [
			'per_page' => 20,
			'page'     => $page,
			'_fields'  => 'id,slug,title,meta,content'
		], $endpoint );

		if ( ! empty( $search ) ) {
			$tax_key  = $this->get_taxonomy_key( $type );
			$param    = ( 'category' === $stype || 'tag' === $stype ) ? $tax_key : $stype;
			$endpoint = add_query_arg( $param, urlencode( $search ), $endpoint );
		}

		$items = $this->fetch_directory_data( $endpoint );

		if ( is_wp_error( $items ) ) {
			wp_send_json_error( [ 'message' => $items->get_error_message() ] );
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

		wp_send_json_success( [
			'html' => $html,
			'next_page' => count( $items ) === 20 ? $page + 1 : false
		] );
	}

	/**
	 * AJAX Handler for the Details Drawer.
	 */
	public function ajax_get_details(): void {
		check_ajax_referer( 'cpdi_ajax_nonce' );

		$slug = sanitize_text_field( $_GET['slug'] ?? '' );
		$type = sanitize_text_field( $_GET['type'] ?? 'plugin' );

		if ( empty( $slug ) ) {
			wp_send_json_error( [ 'message' => 'Missing slug.' ] );
		}

		$endpoint = \CLASSICPRESS_DIRECTORY_INTEGRATION_URL . ( 'plugin' === $type ? 'plugins' : 'themes' );
		$endpoint = add_query_arg( 'slug', $slug, $endpoint );

		$data = $this->fetch_directory_data( $endpoint );
		$item = ( is_array( $data ) && ! empty( $data ) ) ? $data[0] : null;

		if ( ! $item ) {
			wp_send_json_error( [ 'message' => 'Item not found.' ] );
		}

		ob_start();
		$this->render_drawer_content( $item, $type );
		$html = ob_get_clean();

		wp_send_json_success( [ 'html' => $html ] );
	}

	/**
	 * Renders the internal content of the Drawer.
	 */
	private function render_drawer_content( array $item, string $type ): void {
		$status = $this->get_item_status( $item['slug'], $type );
		?>
		<div class="cpdi-drawer-header">
			<h2><?php echo esc_html( $item['title']['rendered'] ); ?></h2>
		</div>
		<div class="cpdi-drawer-body">
			<div class="cpdi-drawer-meta">
				<strong><?php esc_html_e( 'Version:', 'cp-directory-integration' ); ?></strong> <?php echo esc_html( $item['meta']['current_version'] ?? 'n/a' ); ?><br>
				<strong><?php esc_html_e( 'Author:', 'cp-directory-integration' ); ?></strong> <?php echo esc_html( $item['meta']['author'] ?? 'n/a' ); ?>
			</div>
			<div class="cpdi-drawer-description">
				<?php echo wp_kses_post( $item['content']['rendered'] ); ?>
			</div>
		</div>
		<div class="cpdi-drawer-footer">
			<?php $this->render_action_button( $status, $item['slug'] ); ?>
		</div>
		<?php
	}
}
