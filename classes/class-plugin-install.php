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

		// Optional: compatibility with WP modal (can be extended later).
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
	 * Get local plugins mapped by normalized slug.
	 *
	 * @return array
	 */
	protected function get_local_items(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins   = get_plugins();
		$normalized = array();

		foreach ( $plugins as $file => $data ) {
			$slug = $this->normalize_slug( $file );
			$normalized[ $slug ] = array(
				'file' => $file,
				'data' => $data,
			);
		}

		return $normalized;
	}

	/**
	 * Normalize plugin slug.
	 *
	 * @param string $raw Plugin file or slug.
	 * @return string
	 */
	protected function normalize_slug( string $raw ): string {
		if ( strpos( $raw, '/' ) !== false ) {
			return dirname( $raw );
		}

		return str_replace( '.php', '', $raw );
	}

	/**
	 * Install plugin from CP Directory.
	 *
	 * @param string $slug Plugin slug.
	 */
	protected function install_action( string $slug ): void {
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$response = $this->do_directory_request(
			array(
				'byslug' => $slug,
			),
			'plugins'
		);

		if ( empty( $response['success'] ) || empty( $response['response'][0]['meta']['download_link'] ) ) {
			return;
		}

		$download_url = esc_url_raw( $response['response'][0]['meta']['download_link'] );

		$upgrader = new \Plugin_Upgrader();
		$upgrader->install( $download_url );
	}

	/**
	 * Activate plugin.
	 *
	 * @param string $slug Plugin slug.
	 */
	protected function activate_action( string $slug ): void {
		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = $this->get_local_items();

		if ( empty( $plugins[ $slug ]['file'] ) ) {
			return;
		}

		activate_plugin( $plugins[ $slug ]['file'] );
	}

	/**
	 * Override item rendering to keep custom UI.
	 *
	 * @param array $items Items.
	 */
	protected function render_items( array $items ): void {
		if ( empty( $items ) ) {
			echo '<p>' . esc_html__( 'No plugins found in the directory.', 'classicpress-directory-integration' ) . '</p>';
			return;
		}

		foreach ( $items as $item ) {
			$this->render_plugin_card( $item );
		}

		$this->render_details_drawer();
	}

	/**
	 * Render a single plugin card.
	 *
	 * @param array $item Item data.
	 */
	private function render_plugin_card( array $item ): void {
		$slug        = $item['slug'];
		$title       = $item['title'];
		$description = $item['description'];
		$installed   = $item['installed'];

		$action = $installed ? 'activate' : 'install';

		$url = wp_nonce_url(
			add_query_arg(
				array(
					'cpdi_action' => $action,
					'slug'        => $slug,
				)
			),
			'cpdi_action'
		);
		?>
		<div class="cpdi-card plugin-card-<?php echo esc_attr( $slug ); ?>">
			<div class="cpdi-card-body">
				<h3 class="cpdi-card-title">
					<?php echo esc_html( wp_strip_all_tags( $title ) ); ?>
				</h3>

				<div class="cpdi-card-description">
					<?php echo wp_kses_post( $description ); ?>
				</div>

				<div class="cpdi-card-actions">
					<a href="<?php echo esc_url( $url ); ?>" class="button button-primary">
						<?php echo esc_html( ucfirst( $action ) ); ?>
					</a>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Drawer UI for plugin details.
	 */
	private function render_details_drawer(): void {
		?>
		<dialog id="cpdi-details-drawer" class="cpdi-drawer">
			<div class="cpdi-drawer-content">
				<button class="cpdi-drawer-close" aria-label="<?php esc_attr_e( 'Close', 'classicpress-directory-integration' ); ?>">
					&times;
				</button>
				<div id="cpdi-drawer-inner">
					<span class="spinner is-active"></span>
				</div>
			</div>
		</dialog>
		<?php
	}

	/**
	 * Compatibility hook for plugin info modal.
	 */
	public function plugin_information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) ) {
			return $result;
		}

		// Future: map CP Directory data to WP modal.
		return $result;
	}
}
