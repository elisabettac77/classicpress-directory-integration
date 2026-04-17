<?php
/**
 * Abstract class for handling directory integration installations.
 *
 * @package ClassicPress\DirectoryIntegration
 */

namespace ClassicPress\Directory;

require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

/**
 * Abstract_Install class.
 */
abstract class Abstract_Install {
	use Helpers;

	/**
	 * Local CP items array.
	 *
	 * @var bool|array
	 */
	protected $local_cp_items = false;

	/**
	 * Menu page hook suffix.
	 *
	 * @var string|null
	 */
	protected $page = null;

	/**
	 * Type of item ('plugins' or 'themes').
	 *
	 * @var string
	 */
	protected $type = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Hook late enough to ensure parent menus exist.
		if ( is_multisite() ) {
			add_action( 'network_admin_menu', array( $this, 'create_menu' ), 100 );
			add_action( 'network_admin_menu', array( $this, 'rename_menu' ), 101 );
		} else {
			add_action( 'admin_menu', array( $this, 'create_menu' ), 100 );
			add_action( 'admin_menu', array( $this, 'rename_menu' ), 101 );
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

	/**
	 * Enqueue styles for the admin page with cache-busting.
	 */
	public function styles( $hook ) {
		if ( $hook !== $this->page ) {
			return;
		}
		// Automatically cache-bust based on file modification time so you never have to hard-refresh CSS.
		$css_file = plugin_dir_path( dirname( __FILE__ ) ) . 'styles/directory-integration.css';
		$version  = file_exists( $css_file ) ? filemtime( $css_file ) : '1.0.0';
		wp_enqueue_style( 'classicpress-directory-integration-css', plugins_url( '../styles/directory-integration.css', __FILE__ ), array(), $version );
	}

	/**
	 * Enqueue scripts for the admin page with cache-busting.
	 */
	public function scripts( $hook ) {
		if ( $hook !== $this->page ) {
			return;
		}
		// Automatically cache-bust based on file modification time so you never have to hard-refresh JS.
		$js_file = plugin_dir_path( dirname( __FILE__ ) ) . 'scripts/directory-integration.js';
		$version = file_exists( $js_file ) ? filemtime( $js_file ) : '1.0.0';
		wp_enqueue_script( 'classicpress-directory-integration-js', plugins_url( '../scripts/directory-integration.js', __FILE__ ), array( 'wp-i18n' ), $version, true );
		wp_set_script_translations( 'classicpress-directory-integration-js', 'classicpress-directory-integration', plugin_dir_path( dirname( __FILE__ ) ) . 'languages' );
	}

	protected function add_notice( $message, $failure = false ) {
		$transient_key = 'cpdi_' . substr( $this->type, 0, 1 ) . 'i_notices';
		$other_notices = get_transient( $transient_key );
		$notice        = $other_notices === false ? '' : $other_notices;
		$failure_style = $failure ? 'notice-error' : 'notice-success';
		$notice       .= '<div class="notice ' . esc_attr( $failure_style ) . ' is-dismissible">';
		$notice       .= '    <p>' . esc_html( $message ) . '</p>';
		$notice       .= '</div>';
		set_transient( $transient_key, $notice, \HOUR_IN_SECONDS );
	}

	protected function display_notices() {
		$transient_key = 'cpdi_' . substr( $this->type, 0, 1 ) . 'i_notices';
		$notices       = get_transient( $transient_key );
		if ( $notices !== false ) {
			echo wp_kses_post( $notices );
			delete_transient( $transient_key );
		}
	}

	public static function do_directory_request( $args, $type ) {
		$result = array(
			'success'  => false,
			'response' => array(),
		);

		if ( ! is_array( $args ) ) {
			$args = array();
		}

		if ( ! in_array( $type, array( 'plugins', 'themes' ), true ) ) {
			$result['error'] = $type . ' is not a supported type';
			return $result;
		}

		$args     = self::sanitize_args( $args );
		$endpoint = defined( '\CLASSICPRESS_DIRECTORY_INTEGRATION_URL' ) ? \CLASSICPRESS_DIRECTORY_INTEGRATION_URL . $type : 'https://directory.classicpress.net/wp-json/wp/v2/' . $type;
		$endpoint = add_query_arg( $args, $endpoint );

		$response = wp_remote_get(
			$endpoint,
			array(
				'user-agent' => function_exists( 'classicpress_user_agent' ) ? classicpress_user_agent( true ) : 'ClassicPress',
				'timeout'    => 20,
			)
		);

		if ( is_wp_error( $response ) ) {
			$result['error'] = $response->get_error_message();
			return $result;
		}

		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			$result['error'] = 'Unexpected response code: ' . wp_remote_retrieve_response_code( $response );
			return $result;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			$result['error'] = 'Invalid API response.';
			return $result;
		}

		$result['success']  = true;
		$result['response'] = $data;

		return $result;
	}

	public static function sanitize_args( $args ) {
		$sanitized_args = array();

		if ( ! is_array( $args ) ) {
			return $sanitized_args;
		}

		foreach ( $args as $key => $value ) {
			$safe_key = preg_replace( '/[^A-Za-z0-9_]/', '', $key );
			if ( empty( $safe_key ) ) {
				continue;
			}

			switch ( $safe_key ) {
				case 'per_page':
				case 'page':
					$sanitized_args[ $safe_key ] = (int) $value;
					break;
				case 'category':
				case 'tag':
				case 'byslug':
				case '_fields':
					$sanitized_args[ $safe_key ] = preg_replace( '/[^A-Za-z0-9,_-]/', '', (string) $value );
					break;
				case 'search':
				case 's':
				default:
					$sanitized_args[ $safe_key ] = sanitize_text_field( (string) $value );
					break;
			}
		}

		return $sanitized_args;
	}

	public function render_menu() {
		$local_items = $this->get_local_cp_items();
		$is_theme    = $this->type === 'themes';
		$type_label  = $is_theme ? esc_html__( 'Themes', 'classicpress-directory-integration' ) : esc_html__( 'Plugins', 'classicpress-directory-integration' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search_query = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_page_slug = isset( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : '';

		$api_args = array(
			'per_page' => 15,
			'_fields'  => 'title,meta,content,excerpt',
		);

		if ( ! empty( $search_query ) ) {
			$taxonomy_param              = $is_theme ? 'tag' : 'category';
			$api_args[ $taxonomy_param ] = $search_query;
		}

		$response = self::do_directory_request( $api_args, $this->type );
		$items    = ( $response['success'] && ! empty( $response['response'] ) ) ? $response['response'] : array();

		echo '<div class="wrap cp-directory-wrap">';

		echo '<div class="cp-directory-header">';
		echo '<h1 class="wp-heading-inline">Install ClassicPress ' . esc_html( $type_label ) . '</h1>';
		echo '<form class="search-form search-plugins" method="get">';
		echo '<input type="hidden" name="page" value="' . esc_attr( $current_page_slug ) . '" />';
		echo '<label><span class="screen-reader-text">Search ' . esc_html( $type_label ) . '</span>';
		echo '<input type="search" name="s" value="' . esc_attr( $search_query ) . '" class="wp-filter-search" placeholder="Search ' . esc_attr( strtolower( $type_label ) ) . '..." />';
		echo '</label>';
		echo '<input type="submit" id="search-submit" class="button" value="Search" />';
		echo '</form>';
		echo '</div>';

		$this->display_notices();

		echo '<div class="cp-directory-grid" id="cp-directory-grid">';

		foreach ( $items as $item ) {
			$slug         = sanitize_key( $item['meta']['slug'] );
			$is_installed = array_key_exists( $slug, $local_items );
			$is_active    = $is_installed && $local_items[ $slug ]['Active'];

			$state_class = $is_active ? 'cp-state-active' : ( $is_installed ? 'cp-state-inactive' : 'cp-state-uninstalled' );

			echo '<div class="cp-card ' . esc_attr( $state_class ) . '" data-slug="' . esc_attr( $slug ) . '">';

			echo '<div class="cp-card-visual">';
			echo '<div class="cp-shield-badge" title="Passes ClassicPress Coding Standards"><span class="dashicons dashicons-shield"></span></div>';

			if ( $is_theme ) {
				$image_url = ! empty( $item['meta']['screenshot_url'] ) ? $item['meta']['screenshot_url'] : '';
				echo '<div class="cp-card-screenshot" style="background-image: url(\'' . esc_url( $image_url ) . '\');"></div>';
			} else {
				$banner_url = ! empty( $item['meta']['banner_url'] ) ? $item['meta']['banner_url'] : '';
				echo '<div class="cp-card-banner" style="background-image: url(\'' . esc_url( $banner_url ) . '\');"></div>';
				echo '<div class="cp-card-description">' . wp_kses_post( wp_trim_words( $item['excerpt']['rendered'], 20 ) ) . '</div>';
			}
			echo '</div>';

			echo '<div class="cp-card-bottom-bar">';
			echo '<div class="cp-card-info">';
			echo '<h3 class="cp-card-title">' . esc_html( $item['title']['rendered'] ) . '</h3>';
			echo '<span class="cp-card-author">By ' . esc_html( $item['meta']['developer_name'] ) . '</span>';
			echo '</div>';

			echo '<div class="cp-card-details">';
			echo '<button type="button" class="cp-details-button" data-item-data="' . esc_attr( wp_json_encode( $item ) ) . '">Details</button>';
			echo '</div>';

			echo '<div class="cp-card-action">';
			if ( $is_active ) {
				echo '<button type="button" class="button button-primary cp-btn-active" disabled>' . esc_html__( 'Active', 'classicpress-directory-integration' ) . '</button>';
			} elseif ( $is_installed ) {
				// Reverted back to letting add_query_arg use the current URI safely
				$activate_url = wp_nonce_url( add_query_arg( array( 'action' => 'activate', 'slug' => $slug ) ), 'activate', 'cpdi' );
				$delete_url   = wp_nonce_url( add_query_arg( array( 'action' => 'delete-plugin', 'plugin' => $slug ), admin_url( 'plugins.php' ) ), 'delete-plugin_' . $slug );

				echo '<div class="cp-action-group">';
				echo '<a href="' . esc_url( $activate_url ) . '" class="button cp-btn-activate">' . esc_html__( 'Activate', 'classicpress-directory-integration' ) . '</a>';

				if ( $is_theme ) {
					$delete_url = wp_nonce_url( add_query_arg( array( 'action' => 'delete', 'stylesheet' => $slug ), admin_url( 'themes.php' ) ), 'delete-theme_' . $slug );
				}

				echo '<a href="' . esc_url( $delete_url ) . '" class="cp-delete-link aria-button-if-js" style="color: #d63638; font-size: 13px; text-decoration: none; margin-top: 4px; display: inline-block; text-align: center;">' . esc_html__( 'Delete', 'classicpress-directory-integration' ) . '</a>';
				echo '</div>';
			} else {
				// Reverted back to letting add_query_arg use the current URI safely
				$install_url = wp_nonce_url( add_query_arg( array( 'action' => 'install', 'slug' => $slug ) ), 'install', 'cpdi' );
				echo '<a href="' . esc_url( $install_url ) . '" class="button cp-btn-install">' . esc_html__( 'Install', 'classicpress-directory-integration' ) . '</a>';
			}
			echo '</div>';
			echo '</div>';
			echo '</div>';
		}

		echo '</div>';

		echo '<div id="cp-infinite-scroll-trigger" class="cp-skeleton-loader" style="display: none;">';
		echo '<span class="spinner is-active"></span> Loading more...';
		echo '</div>';

		echo '<dialog id="cp-details-modal" class="cp-modal-dialog">';
		echo '<div class="cp-modal-content"></div>';
		echo '<button type="button" class="cp-modal-close"><span class="dashicons dashicons-no-alt"></span></button>';
		echo '</dialog>';

		echo '</div>';
	}
}
