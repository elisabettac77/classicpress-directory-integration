<?php
/**
 * Abstract installer for ClassicPress Directory Integration.
 *
 * @package ClassicPress\Directory
 */

namespace ClassicPress;

use ClassicPress\Directory\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

/**
 * Abstract class for install screens.
 */
abstract class AbstractInstall {

	use Helpers;

	/**
	 * Cached local items.
	 *
	 * @var array|false
	 */
	protected $local_cp_items = false;

	/**
	 * Admin page hook.
	 *
	 * @var string|null
	 */
	protected $page = null;

	/**
	 * Type (plugins|themes).
	 *
	 * @var string
	 */
	protected $type = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$menu_hook = is_multisite() ? 'network_admin_menu' : 'admin_menu';

		add_action( $menu_hook, array( $this, 'create_menu' ), 100 );
		add_action( $menu_hook, array( $this, 'rename_menu' ), 101 );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Create menu entry.
	 */
	abstract public function create_menu();

	/**
	 * Rename existing menu.
	 */
	abstract public function rename_menu();

	/**
	 * Get installed items.
	 *
	 * @return array
	 */
	abstract protected function get_local_cp_items();

	/**
	 * Handle activation.
	 */
	abstract public function activate_action();

	/**
	 * Handle installation.
	 */
	abstract public function install_action();

	/**
	 * Enqueue scripts and styles.
	 *
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( $this->page !== $hook ) {
			return;
		}

		$base_path = plugin_dir_path( dirname( __FILE__ ) );

		$css_file = $base_path . 'styles/directory-integration.css';
		$js_file  = $base_path . 'scripts/directory-integration.js';

		$css_version = file_exists( $css_file ) ? filemtime( $css_file ) : '1.0.0';
		$js_version  = file_exists( $js_file ) ? filemtime( $js_file ) : '1.0.0';

		wp_enqueue_style(
			'cpdi-style',
			plugins_url( '../styles/directory-integration.css', __FILE__ ),
			array(),
			$css_version
		);

		wp_enqueue_script(
			'cpdi-script',
			plugins_url( '../scripts/directory-integration.js', __FILE__ ),
			array( 'wp-i18n' ),
			$js_version,
			true
		);

		wp_set_script_translations(
			'cpdi-script',
			'classicpress-directory-integration',
			$base_path . 'languages'
		);
	}

	/**
	 * Add admin notice.
	 *
	 * @param string $message Message.
	 * @param bool   $is_error Error flag.
	 * @return void
	 */
	protected function add_notice( $message, $is_error = false ) {
		$key = 'cpdi_notices_' . sanitize_key( $this->type );

		$existing = get_transient( $key );
		$existing = ( false === $existing ) ? '' : $existing;

		$class = $is_error ? 'notice-error' : 'notice-success';

		$existing .= sprintf(
			'<div class="notice %1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $class ),
			esc_html( $message )
		);

		set_transient( $key, $existing, HOUR_IN_SECONDS );
	}

	/**
	 * Output notices.
	 *
	 * @return void
	 */
	protected function display_notices() {
		$key     = 'cpdi_notices_' . sanitize_key( $this->type );
		$content = get_transient( $key );

		if ( false !== $content ) {
			echo wp_kses_post( $content );
			delete_transient( $key );
		}
	}

	/**
	 * Get redirect URL.
	 *
	 * @return string
	 */
	protected function get_redirect_url() {
		$referer = wp_get_referer();

		if ( empty( $referer ) ) {
			$base = ( 'themes' === $this->type ) ? 'themes.php' : 'plugins.php';
			$referer = admin_url( $base );
		}

		return remove_query_arg(
			array( 'action', 'slug', 'cpdi', '_wpnonce' ),
			$referer
		);
	}

	/**
	 * Perform directory request.
	 *
	 * @param array  $args Query args.
	 * @param string $type Type.
	 * @return array
	 */
	public static function do_directory_request( $args, $type ) {
		$result = array(
			'success'  => false,
			'response' => array(),
		);

		if ( ! in_array( $type, array( 'plugins', 'themes' ), true ) ) {
			return $result;
		}

		$endpoint = defined( 'CLASSICPRESS_DIRECTORY_INTEGRATION_URL' )
			? CLASSICPRESS_DIRECTORY_INTEGRATION_URL . $type
			: 'https://directory.classicpress.net/wp-json/wp/v2/' . $type;

		$endpoint = add_query_arg(
			self::sanitize_args( $args ),
			$endpoint
		);

		$response = wp_remote_get(
			$endpoint,
			array(
				'timeout' => 20,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $result;
		}

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return $result;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) ) {
			return $result;
		}

		$result['success']  = true;
		$result['response'] = $data;

		return $result;
	}

	/**
	 * Sanitize API args.
	 *
	 * @param array $args Raw args.
	 * @return array
	 */
	public static function sanitize_args( $args ) {
		$clean = array();

		foreach ( (array) $args as $key => $value ) {
			$key = preg_replace( '/[^a-zA-Z0-9_]/', '', $key );

			if ( empty( $key ) ) {
				continue;
			}

			switch ( $key ) {
				case 'per_page':
				case 'page':
					$clean[ $key ] = (int) $value;
					break;

				case 'search':
				case 'category':
				case 'tag':
				case 'byslug':
				case '_fields':
					$clean[ $key ] = sanitize_text_field( (string) $value );
					break;

				default:
					$clean[ $key ] = sanitize_text_field( (string) $value );
			}
		}

		return $clean;
	}
}
