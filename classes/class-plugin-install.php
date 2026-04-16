<?php
namespace ClassicPress\Directory;

class PluginInstall extends Abstract_Install {

	public function __construct() {
		$this->type = 'plugins';
		parent::__construct();
	}

	public function create_menu() {
		if ( ! current_user_can( 'install_plugins' ) ) { return; }
		// ... add_submenu_page logic using 'plugins.php' and 'install_plugins'
	}

	public function rename_menu() {
		// ... plugin specific menu renaming
	}

	protected function get_local_cp_items() {
		if ( $this->local_cp_items !== false ) { return $this->local_cp_items; }
		// ... get_plugins() logic
		return $this->local_cp_items;
	}

	public function activate_action() {
		// ... plugin activation logic using activate_plugin()
	}

	public function install_action() {
		// ... standard plugin install logic using \Plugin_Upgrader
	}
}
