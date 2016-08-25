<?php

class Frm_Usrtrk_Update extends FrmAddon {

	public $plugin_file;
	public $plugin_name = 'User Tracking';
	public $download_id = 170649;
	public $version = '1.0';

	public function __construct() {
		$this->plugin_file = dirname( __FILE__ ) . '/formidable-user-tracking.php';
		parent::__construct();
	}

	public static function load_hooks() {
		add_filter( 'frm_include_addon_page', '__return_true' );
		new Frm_Usrtrk_Update();
	}

}
