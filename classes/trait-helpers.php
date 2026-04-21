<?php

namespace ClassicPress\Directory;

trait Helpers {

	/**
	 * Get all substrings within text that are found between two other, specified strings
	 *
	 * Avoids parsing HTML with regex
	 *
	 * Returns an array<?php
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
			// Find the main file by slug (e.g., 'my-plugin/my-plugin.php').
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
	 *
	 * @param array  $items The items from the API.
	 * @param string $type  The type ('plugin' or 'theme').
	 * @return array Sorted items.
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
	 *
	 * @param string $type The type ('plugin' or 'theme').
	 * @return string 'category' for plugins, 'tag' for themes.
	 */
	protected function get_taxonomy_key( string $type ): string {
		return ( 'plugin' === $type ) ? 'category' : 'tag';
	}

	/**
	 * Generate a placeholder SVG background based on the item slug.
	 * This prevents layout shift when images are missing.
	 *
	 * @param string $slug The item slug.
	 * @return string Data URI of the SVG.
	 */
	protected function get_svg_placeholder( string $slug ): string {
		// Use the first characters of the slug to generate a consistent "random" color.
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
	 *
	 * @param string $endpoint API endpoint.
	 * @return array|\WP_Error
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
}
	 *
	 * See https://stackoverflow.com/a/27078384
	 */
	private function get_markdown_contents( $str, $startDelimiter, $endDelimiter ) {
		$contents             = array();
		$startDelimiterLength = strlen( $startDelimiter );
		$endDelimiterLength   = strlen( $endDelimiter );
		$startFrom            = $contentStart = $contentEnd = 0;

		while ( $contentStart = strpos( $str, $startDelimiter, $startFrom ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
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
	 * Polyfill for json_validate
	 * The function is defined only in PHP 8 >= 8.3.0
	 */
	private static function json_validate( $json ) {
		if ( function_exists( 'json_validate' ) ) {
			return json_validate( $json );
		}
		return json_decode( $json ) !== null;
	}

}
