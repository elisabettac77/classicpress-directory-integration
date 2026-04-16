<?php
namespace ClassicPress\Directory;

require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

abstract class Abstract_Install {
	use Helpers;

	protected $local_cp_items = false;
	protected $page           = null;
	protected $type           = ''; // Set by child class ('plugins' or 'themes')

		public function __construct() {
		// Hook late enough to ensure parent menus exist
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

		// Notice: $type no longer defaults to 'plugins'. The caller must specify.
	public static function do_directory_request( $args = array(), $type ) {
		$result = array(
			'success'  => false,
			'response' => array(),
		);

		// Strict validation to ensure only plugins or themes are requested
		if ( ! in_array( $type, array( 'plugins', 'themes' ), true ) ) {
			$result['error'] = $type . ' is not a supported type';
			return $result;
		}

		$args     = self::sanitize_args( $args );
		$endpoint = \CLASSICPRESS_DIRECTORY_INTEGRATION_URL . $type;
		$endpoint = add_query_arg( $args, $endpoint );

		$response = wp_remote_get(
			$endpoint,
			array(
				'user-agent' => classicpress_user_agent( true ),
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
			// Ensure the key itself is safe
			$safe_key = preg_replace( '/[^A-Za-z0-9_]/', '', $key );
			if ( empty( $safe_key ) ) {
				continue;
			}

			switch ( $safe_key ) {
				// Integers
				case 'per_page':
				case 'page':
					$sanitized_args[ $safe_key ] = (int) $value;
					break;

				// Alphanumeric strings with dashes and commas (for comma-separated slugs/tags)
				case 'category':
				case 'tag':
				case 'byslug':
				case '_fields':
					$sanitized_args[ $safe_key ] = preg_replace( '/[^A-Za-z0-9,_-]/', '', (string) $value );
					break;

				// Standard text strings (like search queries)
				case 'search':
				case 's':
					$sanitized_args[ $safe_key ] = sanitize_text_field( (string) $value );
					break;

				// Fallback for any other unexpected but safe keys
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

		// Handle Search Query
		$search_query = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$api_args     = array(
			'per_page' => 15,
			'_fields'  => 'title,meta,content,excerpt',
		);

		if ( ! empty( $search_query ) ) {
			$taxonomy_param = $is_theme ? 'tag' : 'category';
			$api_args[ $taxonomy_param ] = $search_query;
		}

		$response = self::do_directory_request( $api_args, $this->type );
		$items    = ( $response['success'] && ! empty( $response['response'] ) ) ? $response['response'] : array();

		echo '<div class="wrap cp-directory-wrap">';
		
		// Header & Search
		echo '<div class="cp-directory-header">';
		echo '<h1 class="wp-heading-inline">Install ClassicPress ' . esc_html( $type_label ) . '</h1>';
		echo '<form class="search-form search-plugins" method="get">';
		echo '<input type="hidden" name="page" value="' . esc_attr( $_REQUEST['page'] ) . '" />';
		echo '<label><span class="screen-reader-text">Search ' . esc_html( $type_label ) . '</span>';
		echo '<input type="search" name="s" value="' . esc_attr( $search_query ) . '" class="wp-filter-search" placeholder="Search ' . esc_attr( strtolower( $type_label ) ) . '..." />';
		echo '</label>';
		echo '<input type="submit" id="search-submit" class="button" value="Search" />';
		echo '</form>';
		echo '</div>';

		$this->display_notices();

		// The Grid
		echo '<div class="cp-directory-grid" id="cp-directory-grid">';

		foreach ( $items as $item ) {
			$slug         = sanitize_key( $item['meta']['slug'] );
			$is_installed = array_key_exists( $slug, $local_items );
			$is_active    = $is_installed && $local_items[ $slug ]['Active'];

			// Future-proofing state classes: cp-state-active, cp-state-inactive, cp-state-uninstalled
			$state_class = $is_active ? 'cp-state-active' : ( $is_installed ? 'cp-state-inactive' : 'cp-state-uninstalled' );

			echo '<div class="cp-card ' . esc_attr( $state_class ) . '" data-slug="' . esc_attr( $slug ) . '">';
			
			// Top Visual Area (Screenshot for themes, Banner + Text for plugins)
			echo '<div class="cp-card-visual">';
			
			// Shield Badge (Top Right)
			echo '<div class="cp-shield-badge" title="Passes ClassicPress Coding Standards"><span class="dashicons dashicons-shield"></span></div>';

			if ( $is_theme ) {
				$image_url = ! empty( $item['meta']['screenshot_url'] ) ? $item['meta']['screenshot_url'] : '';
				echo '<div class="cp-card-screenshot" style="background-image: url(\'' . esc_url( $image_url ) . '\');"></div>';
			} else {
				$banner_url = ! empty( $item['meta']['banner_url'] ) ? $item['meta']['banner_url'] : '';
				echo '<div class="cp-card-banner" style="background-image: url(\'' . esc_url( $banner_url ) . '\');"></div>';
				echo '<div class="cp-card-description">' . wp_kses_post( wp_trim_words( $item['excerpt']['rendered'], 20 ) ) . '</div>';
			}
			echo '</div>'; // End cp-card-visual

			// Bottom Bar (80px)
			echo '<div class="cp-card-bottom-bar">';
			
			// Left: Title & Author
			echo '<div class="cp-card-info">';
			echo '<h3 class="cp-card-title">' . esc_html( $item['title']['rendered'] ) . '</h3>';
			echo '<span class="cp-card-author">By ' . esc_html( $item['meta']['developer_name'] ) . '</span>';
			echo '</div>';

			// Center: Details Link (Triggers Dialog)
			echo '<div class="cp-card-details">';
			echo '<button type="button" class="cp-details-button" data-item-data="' . esc_attr( wp_json_encode( $item ) ) . '">Details</button>';
			echo '</div>';

			// Right: Action Button
			echo '<div class="cp-card-action">';
			if ( $is_active ) {
				echo '<button type="button" class="button button-primary cp-btn-active" disabled>Active</button>';
			} elseif ( $is_installed ) {
				$activate_url = wp_nonce_url( add_query_arg( array( 'action' => 'activate', 'slug' => $slug ) ), 'activate', '_cpdi' );
				echo '<a href="' . esc_url( $activate_url ) . '" class="button cp-btn-activate">Activate</a>';
			} else {
				$install_url = wp_nonce_url( add_query_arg( array( 'action' => 'install', 'slug' => $slug ) ), 'install', '_cpdi' );
				echo '<a href="' . esc_url( $install_url ) . '" class="button cp-btn-install">Install</a>';
			}
			echo '</div>';

			echo '</div>'; // End cp-card-bottom-bar
			echo '</div>'; // End cp-card
		}

		echo '</div>'; // End Grid

		// Infinite Scroll Trigger (Skeleton Loader Placeholder)
		echo '<div id="cp-infinite-scroll-trigger" class="cp-skeleton-loader" style="display: none;">';
		echo '<span class="spinner is-active"></span> Loading more...';
		echo '</div>';

		// Native Dialog Element for Details
		echo '<dialog id="cp-details-modal" class="cp-modal-dialog">';
		echo '<div class="cp-modal-content"></div>';
		echo '<button type="button" class="cp-modal-close"><span class="dashicons dashicons-no-alt"></span></button>';
		echo '</dialog>';

		echo '</div>'; // End Wrap
	}
}
