<?php
/**
 * Plugin Install Class
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
 * Handles the Plugin Installation UI and logic.
 */
class Plugin_Install extends Abstract_Install {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( 'plugin' );
		
		// Filter for the "View Details" modal content in standard WP lists.
		add_filter( 'plugins_api', array( $this, 'plugin_information' ), 10, 3 );
	}

	/**
	 * Register the admin menu page under 'Plugins'.
	 */
	public function register_menu(): void {
		add_plugins_page(
			__( 'Install CP Plugins', 'classicpress-directory-integration' ),
			__( 'Install CP Plugins', 'classicpress-directory-integration' ),
			'install_plugins',
			'cp-directory-integration-plugins',
			array( $this, 'render_menu' )
		);
	}

	/**
	 * Implementation of the grid content.
	 */
	protected function render_content(): void {
		// 1. Get Search Parameters. Unslash before sanitizing.
		$search_query = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$search_type  = isset( $_GET['stype'] ) ? sanitize_text_field( wp_unslash( $_GET['stype'] ) ) : 'keyword';
		
		// 2. Build API Endpoint.
		$endpoint = \CLASSICPRESS_DIRECTORY_INTEGRATION_URL . 'plugins?per_page=20';
		
		if ( ! empty( $search_query ) ) {
			$taxonomy = $this->get_taxonomy_key( 'plugin' );
			$param    = ( 'category' === $search_type ) ? $taxonomy : $search_type;
			// Use rawurlencode for RFC 3986 compliance.
			$endpoint .= "&{$param}=" . rawurlencode( $search_query );
		}

		// 3. Fetch Data.
		$items = $this->fetch_directory_data( $endpoint );

		if ( is_wp_error( $items ) || empty( $items ) ) {
			echo '<p>' . esc_html__( 'No plugins found in the directory.', 'classicpress-directory-integration' ) . '</p>';
			return;
		}

		// 4. Sort: Active -> Inactive -> Not Installed.
		$items = $this->sort_items_by_status( $items, 'plugin' );

		// 5. Render Cards.
		foreach ( $items as $item ) {
			$this->render_plugin_card( $item );
		}

		// 6. The Side-Drawer (Dialog).
		$this->render_details_drawer();
	}

	/**
	 * Render an individual plugin card.
	 *
	 * @param array $item Plugin data from API.
	 */
	private function render_plugin_card( array $item ): void {
		$slug    = $item['slug'] ?? '';
		$status  = $this->get_item_status( $slug, 'plugin' );
		$version = $item['meta']['current_version'] ?? '0.0.0';
		$banner  = $item['meta']['banner_low'] ?? $this->get_svg_placeholder( $slug );
		
		// Check for updates.
		$has_update = false;
		if ( 'not-installed' !== $status ) {
			$local_data = $this->get_local_plugin_data( $slug );
			if ( $local_data && version_compare( $local_data['Version'], $version, '<' ) ) {
				$has_update = true;
			}
		}

		?>
		<div class="cpdi-card plugin-card-<?php echo esc_attr( $slug ); ?>" data-slug="<?php echo esc_attr( $slug ); ?>">
			<?php if ( $has_update ) : ?>
				<div class="notice notice-warning notice-alt inline">
					<p><?php esc_html_e( 'New version available!', 'classicpress-directory-integration' ); ?></p>
				</div>
			<?php endif; ?>

			<div class="cpdi-card-header">
				<img src="<?php echo esc_url( $banner ); ?>" class="cpdi-card-banner" alt="">
			</div>

			<div class="cpdi-card-body">
				<div class="cpdi-card-info">
					<h3 class="cpdi-card-title"><?php echo esc_html( $item['title']['rendered'] ?? $slug ); ?></h3>
					<p class="cpdi-card-author">
						<?php 
						/* translators: %s: Author name */
						printf( esc_html__( 'By %s', 'classicpress-directory-integration' ), esc_html( $item['meta']['author'] ?? '' ) ); 
						?>
					</p>
				</div>

				<div class="cpdi-card-actions">
					<div class="cpdi-main-action">
						<?php $this->render_action_button( $status, $slug ); ?>
					</div>
					
					<div class="cpdi-secondary-actions">
						<a href="#" class="cpdi-details-trigger" data-slug="<?php echo esc_attr( $slug ); ?>">
							<?php esc_html_e( 'Details', 'classicpress-directory-integration' ); ?>
						</a>
						
						<?php if ( 'inactive' === $status ) : ?>
							<span class="sep">|</span>
							<a href="#" class="cpdi-delete-link delete-red" data-slug="<?php echo esc_attr( $slug ); ?>">
								<?php esc_html_e( 'Delete', 'classicpress-directory-integration' ); ?>
							</a>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the correct main button based on status.
	 */
	private function render_action_button( string $status, string $slug ): void {
		switch ( $status ) {
			case 'active':
				echo '<button class="button cpdi-button-deactivate" data-slug="' . esc_attr( $slug ) . '">' . esc_html__( 'Deactivate', 'classicpress-directory-integration' ) . '</button>';
				break;
			case 'inactive':
				echo '<button class="button button-primary cpdi-button-activate" data-slug="' . esc_attr( $slug ) . '">' . esc_html__( 'Activate', 'classicpress-directory-integration' ) . '</button>';
				break;
			default:
				echo '<button class="button button-primary cpdi-button-install" data-slug="' . esc_attr( $slug ) . '">' . esc_html__( 'Install Now', 'classicpress-directory-integration' ) . '</button>';
				break;
		}
	}

	/**
	 * The Dialog Drawer shell.
	 */
	private function render_details_drawer(): void {
		?>
		<dialog id="cpdi-details-drawer" class="cpdi-drawer">
			<div class="cpdi-drawer-content">
				<button class="cpdi-drawer-close" aria-label="<?php esc_attr_e( 'Close', 'classicpress-directory-integration' ); ?>">&times;</button>
				<div id="cpdi-drawer-inner">
					<span class="spinner is-active"></span>
				</div>
			</div>
		</dialog>
		<?php
	}

	/**
	 * Helper to get local plugin data for version comparison.
	 * * @return array|bool
	 */
	private function get_local_plugin_data( string $slug ) {
		$plugins = get_plugins();
		foreach ( $plugins as $file => $data ) {
			if ( dirname( $file ) === $slug || $file === $slug . '.php' ) {
				return $data;
			}
		}
		return false;
	}

	/**
	 * Standard WP API shim for CP Directory.
	 */
	public function plugin_information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) ) {
			return $result;
		}
		return $result;
	}
}
