<?php
namespace ClassicPress\Directory;

require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

abstract class Abstract_Install {
	use Helpers;

	protected $local_cp_items = false;
	protected $page           = null;
	protected $type           = ''; // Set by child class ('plugins' or 'themes')

	public function __construct() {
		if ( is_multisite() ) {
			add_action( 'network_admin_menu', array( $this, 'create_menu' ), 100 );
			add_action( 'network_admin_menu', array( $this, 'rename_menu' ) );
		} else {
			add_action( 'admin_menu', array( $this, 'create_menu' ), 100 );
			add_action( 'admin_menu', array( $this, 'rename_menu' ) );
		}
		add_action( 'admin_enqueue_scripts', array( $this, 'styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'scripts' ) );
	}

	// --------------------------------------------------------
	// Abstract methods to be implemented by child classes
	// --------------------------------------------------------
	abstract public function create_menu();
	abstract public function rename_menu();
	abstract protected function get_local_cp_items();
	abstract public function activate_action();
	abstract public function install_action();

	// --------------------------------------------------------
	// Deduplicated Shared Logic
	// --------------------------------------------------------
	public function styles( $hook ) {
		if ( $hook !== $this->page ) { return; }
		wp_enqueue_style( 'classicpress-directory-integration-css', plugins_url( '../styles/directory-integration.css', __FILE__ ), array() );
	}

	public function scripts( $hook ) {
		if ( $hook !== $this->page ) { return; }
		wp_enqueue_script( 'classicpress-directory-integration-js', plugins_url( '../scripts/directory-integration.js', __FILE__ ), array( 'wp-i18n' ), false, true );
		wp_set_script_translations( 'classicpress-directory-integration-js', 'classicpress-directory-integration', plugin_dir_path( dirname( __FILE__ ) ) . 'languages' );
	}

	// Deduplicated notice logic. Transient key depends on type.
	protected function add_notice( $message, $failure = false ) {
		$transient_key = 'cpdi_' . substr( $this->type, 0, 1 ) . 'i_notices'; // 'cpdi_pi_notices' or 'cpdi_ti_notices'
		$other_notices = get_transient( $transient_key );
		$notice        = $other_notices === false ? '' : $other_notices;
		$failure_style = $failure ? 'notice-error' : 'notice-success';
		$notice       .= '<div class="notice ' . $failure_style . ' is-dismissible">';
		$notice       .= '    <p>' . esc_html( $message ) . '</p>';
		$notice       .= '</div>';
		set_transient( $transient_key, $notice, \HOUR_IN_SECONDS );
	}

	protected function display_notices() {
		$transient_key = 'cpdi_' . substr( $this->type, 0, 1 ) . 'i_notices';
		$notices       = get_transient( $transient_key );
		if ( $notices !== false ) {
			echo $notices; //phpcs:ignore
			delete_transient( $transient_key );
		}
	}

	// Deduplicated API request. Note the bugfix for the 'themes' array check.
	public static function do_directory_request( $args = array(), $type = 'plugins' ) {
		$result['success'] = false;
		if ( ! in_array( $type, array( 'plugins', 'themes' ) ) ) {
			$result['error'] = $type . ' is not a supported type';
			return $result;
		}
		$args     = self::sanitize_args( $args );
		$endpoint = \CLASSICPRESS_DIRECTORY_INTEGRATION_URL . $type;
		$endpoint = add_query_arg( $args, $endpoint );
		// ... existing API parsing logic ...
		return $result;
	}

	public static function sanitize_args( $args ) {
		// ... existing sanitize logic, plus new taxonomy fields ...
		foreach ( $args as $key => $value ) {
			$sanitized = false;
			switch ( $key ) {
				case 'per_page':
				case 'page':
					$args[ $key ] = (int) $value;
					$sanitized    = true;
					break;
				case 'category': // <-- New for taxonomy filter
				case 'tag':      // <-- New for taxonomy filter
				case 'byslug':
					$args[ $key ] = preg_replace( '[^A-Za-z0-9\-_]', '', $value );
					$sanitized    = true;
					break;
				// ... rest of sanitize logic
			}
		}
		return $args;
	}

	// Move render_menu() here, and use $this->type to conditionally output 
	// specific labels, aria tags, and CSS classes (Phase 3 and 4 UI improvements).
	public function render_menu() {
		// ... standard pagination, request handling, and grid output logic
		// using $this->type to render strings dynamically.
	}
}
