<?php

class FrmUsrTrkUpdate{
	var $plugin_nicename;
	var $plugin_name;
	var $pro_check_interval;
	var $pro_last_checked_store;

	function __construct(){
		if ( ! class_exists( 'FrmUpdatesController' ) ) {
			return;
		}

		// Where all the vitals are defined for this plugin
		$this->plugin_nicename      = 'formidable-user-tracking';
		$this->plugin_name          = 'formidable-user-tracking/formidable-user-tracking.php';
		$this->pro_last_checked_store = 'frmtrk_last_check';
		$this->pro_check_interval   = 60 * 60 * 24; // Checking every 24 hours

		add_filter( 'site_transient_update_plugins', array( &$this, 'queue_update' ) );
	}

	function queue_update( $transient, $force = false ) {
		$plugin = $this;

		global $frm_update;
		if ( $frm_update ) {
			$updates = $frm_update;
		} else {
			$updates = new FrmUpdatesController();
		}

		return $updates->queue_addon_update( $transient, $plugin, $force );
	}

}