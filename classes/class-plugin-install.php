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

	public function __construct() {
		parent::__construct( 'plugin' );

		add_filter( 'plugins_api', array( $this, 'plugin_information' ), 10, 3 );
	}

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
	 * 🔥 REQUIRED: local items mapping
	 */
	protected function get_local_items(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = get_plugins();
		$normalized = array();

		foreach ( $plugins as $file => $data ) {
			$slug = $this->normalize_slug( $file );
			$normalized[ $slug ] = $data;
		}

		return $normalized;
	}

	/**
	 * 🔥 REQUIRED: slug normalization
	 */
	protected function normalize_slug( string $raw ): string {
		if ( strpos( $raw, '/' ) !== false ) {
			return dirname( $raw );
		}

		return str_replace( '.php', '', $raw );
	}

	/**
	 * 🔥 REQUIRED: install logic
	 */
	protected function install_action( string $slug ): void {
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$api = $this->do_directory_request(
			array( 'byslug' => $slug ),
			'plugins'
		);

		if ( empty( $api['response'][0]['meta']['download_link'] ) ) {
			return;
		}

		$upgrader = new \Plugin_Upgrader();
		$upgrader->install( $api['response'][0]['meta']['download_link'] );
	}

	/**
	 * 🔥 REQUIRED: activation logic
	 */
	protected function activate_action( string $slug ): void {
		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = get_plugins();

		foreach ( $plugins as $file => $data ) {
			if ( $this->normalize_slug( $file ) === $slug ) {
				activate_plugin( $file );
				break;
			}
		}
	}

	/**
	 * 🔧 OVERRIDE rendering (keep your UI)
	 */
	protected function render_items( array $items ): void {
		foreach ( $items as $item ) {
			$this->render_plugin_card( $item );
		}

		$this->render_details_drawer();
	}

	/**
	 * Keep your existing card renderer,
	 * but REMOVE old status system
	 */
	private function render_plugin_card( array $item ): void {
		$slug = $item['slug'];
		$status = $item['installed'] ? 'inactive' : 'not-installed';

		$action = $item['installed'] ? 'activate' : 'install';

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
		<div class="cpdi-card">
			<h3><?php echo esc_html( $item['title'] ); ?></h3>

			<div>
				<?php echo wp_kses_post( $item['description'] ); ?>
			</div>

			<a href="<?php echo esc_url( $url ); ?>" class="button button-primary">
				<?php echo esc_html( ucfirst( $action ) ); ?>
			</a>
		</div>
		<?php
	}

	/* keep drawer + other helpers unchanged */
}
