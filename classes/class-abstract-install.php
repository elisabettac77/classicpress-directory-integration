<?php
/**
 * Abstract Install Class
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
 * Abstract class for managing the directory installation UI.
 */
abstract class Abstract_Install {

	use Helpers;

	/**
	 * Type of installation (plugin or theme).
	 *
	 * @var string
	 */
	protected string $type;

	/**
	 * Constructor.
	 *
	 * @param string $type The type of installation ('plugin' or 'theme').
	 */
	public function __construct( string $type ) {
		$this->type = $type;
		$this->init();
	}

	/**
	 * Initialize hooks.
	 */
	protected function init(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// 🔥 Critical: action handler (install / activate).
		add_action( 'admin_init', array( $this, 'handle_actions' ) );

		// AJAX endpoint (optional but supported).
		add_action( 'wp_ajax_cpdi_fetch_items', array( $this, 'ajax_fetch_items' ) );
	}

	/**
	 * Register the admin menu page.
	 */
	abstract public function register_menu(): void;

	/**
	 * Get local installed items (plugins/themes).
	 *
	 * @return array
	 */
	abstract protected function get_local_items(): array;

	/**
	 * Install item.
	 */
	abstract protected function install_action( string $slug ): void;

	/**
	 * Activate item.
	 */
	abstract protected function activate_action( string $slug ): void;

	/**
	 * Normalize slug (plugin/theme specific).
	 */
	abstract protected function normalize_slug( string $raw ): string;

	/**
	 * Enqueue CSS and JS.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( strpos( $hook, 'cp-directory-integration' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'cpdi-admin-style',
			\CPDI_URL . 'assets/css/directory-integration.css',
			array(),
			\CPDI_VERSION
		);

		wp_enqueue_script(
			'cpdi-admin-script',
			\CPDI_URL . 'assets/js/directory-integration.js',
			array(),
			\CPDI_VERSION,
			true
		);

		wp_localize_script(
			'cpdi-admin-script',
			'cpdiData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'cpdi_ajax_nonce' ),
				'type'    => $this->type,
			)
		);
	}

	/**
	 * Handle install/activate actions.
	 */
	public function handle_actions(): void {
		if ( ! isset( $_GET['cpdi_action'], $_GET['slug'], $_GET['_wpnonce'] ) ) {
			return;
		}

		$action = sanitize_key( wp_unslash( $_GET['cpdi_action'] ) );
		$slug   = sanitize_text_field( wp_unslash( $_GET['slug'] ) );
		$nonce  = wp_unslash( $_GET['_wpnonce'] );

		if ( ! wp_verify_nonce( $nonce, 'cpdi_action' ) ) {
			return;
		}

		switch ( $action ) {
			case 'install':
				$this->install_action( $slug );
				break;

			case 'activate':
				$this->activate_action( $slug );
				break;
		}

		// Redirect to avoid resubmission.
		wp_safe_redirect( remove_query_arg( array( 'cpdi_action', 'slug', '_wpnonce' ) ) );
		exit;
	}

	/**
	 * Fetch and prepare items.
	 *
	 * @return array
	 */
	protected function get_items(): array {
		$args = array(
			'per_page' => 20,
		);

		$response = $this->do_directory_request( $args, $this->type );

		if ( empty( $response['success'] ) ) {
			return array();
		}

		$remote = $response['response'];
		$local  = $this->get_local_items();

		$items = array();

		foreach ( $remote as $item ) {
			if ( empty( $item['meta']['slug'] ) ) {
				continue;
			}

			$slug = $this->normalize_slug( $item['meta']['slug'] );

			$items[] = array(
				'slug'        => $slug,
				'title'       => $item['title']['rendered'] ?? '',
				'description' => $item['excerpt']['rendered'] ?? '',
				'installed'   => isset( $local[ $slug ] ),
			);
		}

		return $items;
	}

	/**
	 * AJAX: fetch items.
	 */
	public function ajax_fetch_items(): void {
		check_ajax_referer( 'cpdi_ajax_nonce', 'nonce' );

		wp_send_json_success( $this->get_items() );
	}

	/**
	 * Render the page.
	 */
	public function render_menu(): void {
		?>
		<div class="wrap cpdi-container">
			<h1 class="wp-heading-inline">
				<?php echo esc_html( get_admin_page_title() ); ?>
			</h1>

			<hr class="wp-header-end">

			<div id="cpdi-directory-list" class="cpdi-card-grid">
				<?php $this->render_items( $this->get_items() ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render items (can be overridden).
	 *
	 * @param array $items Items.
	 */
	protected function render_items( array $items ): void {
		foreach ( $items as $item ) {
			$this->render_card( $item );
		}
	}

	/**
	 * Render a single card.
	 *
	 * @param array $item Item data.
	 */
	protected function render_card( array $item ): void {
		$action = $item['installed'] ? 'activate' : 'install';

		$url = wp_nonce_url(
			add_query_arg(
				array(
					'cpdi_action' => $action,
					'slug'        => $item['slug'],
				)
			),
			'cpdi_action'
		);
		?>
		<div class="cpdi-card">
			<h2><?php echo esc_html( wp_strip_all_tags( $item['title'] ) ); ?></h2>

			<div class="cpdi-description">
				<?php echo wp_kses_post( $item['description'] ); ?>
			</div>

			<a href="<?php echo esc_url( $url ); ?>" class="button">
				<?php echo esc_html( ucfirst( $action ) ); ?>
			</a>
		</div>
		<?php
	}
}
