<?php
/**
 * Theme Install Class
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
 * Handles the Theme Installation UI and Child Theme logic.
 */
class Theme_Install extends Abstract_Install {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( 'theme' );
	}

	/**
	 * Register the admin menu page under 'Appearance'.
	 */
	public function register_menu(): void {
		add_theme_page(
			__( 'Install CP Themes', 'classicpress-directory-integration' ),
			__( 'Install CP Themes', 'classicpress-directory-integration' ),
			'install_themes',
			'cp-directory-integration-themes',
			array( $this, 'render_menu' )
		);
	}

	/**
	 * Implementation of the grid content.
	 */
	protected function render_content(): void {
		// 1. Get Search Parameters.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search_query = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search_type  = isset( $_GET['stype'] ) ? sanitize_text_field( wp_unslash( $_GET['stype'] ) ) : 'keyword';
		
		$endpoint = \CLASSICPRESS_DIRECTORY_INTEGRATION_URL . 'themes?per_page=20';
		
		if ( ! empty( $search_query ) ) {
			$taxonomy = $this->get_taxonomy_key( 'theme' ); // Returns 'tag'
			$param    = ( 'tag' === $search_type ) ? $taxonomy : $search_type;
			// rawurlencode is preferred over urlencode for modern PHP/APIs.
			$endpoint .= "&{$param}=" . rawurlencode( $search_query );
		}

		$items = $this->fetch_directory_data( $endpoint );

		if ( is_wp_error( $items ) || empty( $items ) ) {
			echo '<p>' . esc_html__( 'No themes found.', 'classicpress-directory-integration' ) . '</p>';
			return;
		}

		// 2. Sort: Active -> Inactive -> Not Installed.
		$items = $this->sort_items_by_status( $items, 'theme' );

		// 3. Render Cards.
		foreach ( $items as $item ) {
			$this->render_theme_card( $item );
		}

		$this->render_details_drawer();
	}

	/**
	 * Render an individual theme card.
	 *
	 * @param array $item Theme data.
	 */
	private function render_theme_card( array $item ): void {
		$slug        = $item['slug'] ?? '';
		$status      = $this->get_item_status( $slug, 'theme' );
		$screenshot  = $item['meta']['screenshot_url'] ?? $this->get_svg_placeholder( $slug );
		$parent_slug = $item['parent_theme'] ?? $item['meta']['parent_theme'] ?? '';

		?>
		<div class="cpdi-card theme-card-<?php echo esc_attr( $slug ); ?>" 
			 data-slug="<?php echo esc_attr( $slug ); ?>"
			 data-parent="<?php echo esc_attr( $parent_slug ); ?>">
			
			<div class="cpdi-card-header">
				<img src="<?php echo esc_url( $screenshot ); ?>" class="cpdi-card-banner" alt="">
			</div>

			<div class="cpdi-card-body">
				<div class="cpdi-card-info">
					<h3 class="cpdi-card-title"><?php echo esc_html( $item['title']['rendered'] ?? $slug ); ?></h3>
					<?php if ( ! empty( $parent_slug ) ) : ?>
						<p class="cpdi-child-theme-tag">
							<span class="dashicons dashicons-media-code"></span> 
							<?php 
							/* translators: %s: theme slug */
							printf( esc_html__( 'Child of %s', 'classicpress-directory-integration' ), esc_html( $parent_slug ) ); 
							?>
						</p>
					<?php endif; ?>
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
	 * Render buttons based on theme status.
	 */
	private function render_action_button( string $status, string $slug ): void {
		switch ( $status ) {
			case 'active':
				$customize_url = admin_url( 'customize.php?theme=' . rawurlencode( $slug ) );
				echo '<a href="' . esc_url( $customize_url ) . '" class="button">' . esc_html__( 'Customize', 'classicpress-directory-integration' ) . '</a>';
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
	 * Render the details drawer.
	 */
	private function render_details_drawer(): void {
		?>
		<dialog id="cpdi-details-drawer" class="cpdi-drawer">
			<div class="cpdi-drawer-content">
				<button class="cpdi-drawer-close" aria-label="<?php esc_attr_e( 'Close', 'classicpress-directory-integration' ); ?>">&times;</button>
				<div id="cpdi-drawer-inner"></div>
			</div>
		</dialog>
		<?php
	}
}
