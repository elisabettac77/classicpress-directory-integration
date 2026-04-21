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

	/**
	 * Use the Helpers trait for shared API and data logic.
	 */
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
	}

	/**
	 * Register the admin menu page.
	 */
	abstract public function register_menu(): void;

	/**
	 * Enqueue CSS and JS.
	 */
	public function enqueue_assets( string $hook ): void {
		// Only load on the specific integration pages.
		if ( strpos( $hook, 'cp-directory-integration' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'cpdi-admin-style',
			CPDI_URL . 'assets/css/directory-integration.css',
			array(),
			CPDI_VERSION
		);

		wp_enqueue_script(
			'cpdi-admin-script',
			CPDI_URL . 'assets/js/directory-integration.js',
			array( 'jquery', 'wp-util' ),
			CPDI_VERSION,
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
	 * Main entry point for the Admin Page UI.
	 * This fixes the missing method issue.
	 */
	public function render_menu(): void {
		?>
		<div class="wrap cpdi-container">
			<h1 class="wp-heading-inline">
				<?php echo esc_html( get_admin_page_title() ); ?>
			</h1>
			<hr class="wp-header-end">

			<div class="cpdi-toolbar">
				<div class="search-form">
					<label class="screen-reader-text" for="cpdi-search-input">
						<?php esc_html_e( 'Search Directory...', 'cp-directory-integration' ); ?>
					</label>
					<select id="cpdi-search-type">
						<option value="keyword"><?php esc_html_e( 'Keyword', 'cp-directory-integration' ); ?></option>
   						<option value="author"><?php esc_html_e( 'Author', 'cp-directory-integration' ); ?></option>
    					<option value="tag"><?php esc_html_e( 'Tag/Category', 'cp-directory-integration' ); ?></option>
    					<option value="name"><?php esc_html_e( 'Name', 'cp-directory-integration' ); ?></option>
					</select>
					<input type="search" id="cpdi-search-input" class="wp-filter-search" placeholder="<?php esc_attr_e( 'Search...', 'cp-directory-integration' ); ?>">
				</div>
			</div>

			<div id="cpdi-directory-list" class="cpdi-card-grid" data-page="1">
				<?php $this->render_content(); ?>
			</div>

			<div id="cpdi-loader" style="display:none;">
				<span class="spinner is-active"></span>
			</div>
			
			<button id="cpdi-back-to-top" title="<?php esc_attr_e( 'Back to top', 'cp-directory-integration' ); ?>">
				<span class="dashicons dashicons-arrow-up-alt2"></span>
			</button>
		</div>
		<?php
	}

	/**
	 * Renders the actual items (Plugins or Themes).
	 * Must be implemented by child classes.
	 */
	abstract protected function render_content(): void;

	/**
	 * Sanitize arguments for API requests.
	 *
	 * @param array $args Arguments to sanitize.
	 * @return array
	 */
	protected function sanitize_args( array $args ): array {
		return array_map( 'sanitize_text_field', $args );
	}
}
