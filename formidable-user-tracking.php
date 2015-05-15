<?php
/*
Plugin Name: Formidable User Tracking
Description: Track the steps a user takes before submitting a form
Version: 1.0b
Plugin URI: http://formidablepro.com/
Author URI: http://strategy11.com
Author: Strategy11
*/

class FrmUserTracking {
	// keep the page history below 100
	protected static $page_max = 100;

	protected static $keywords = array();
	protected static $referrer_info = '';

	public function __construct() {
		add_filter( 'frm_load_controllers', 'FrmUserTracking::include_tracking_hooks' );
	}

	public static function include_tracking_hooks( $classes ) {
		self::load_hooks();
		return $classes;
	}

	public static function load_hooks() {
		if ( ! FrmAppHelper::is_admin() ) {
			// Update the session data
			add_action( 'init', 'FrmUserTracking::compile_referer_session', 1 );
		}
		add_action( 'frm_after_create_entry', 'FrmUserTracking::insert_tracking_into_entry' );
		add_action( 'admin_init', 'FrmUserTracking::include_auto_updater', 1 );
	}

    public static function include_auto_updater(){
        include_once( dirname( __FILE__ ) .'/FrmUsrTrkUpdate.php');
        new FrmUsrTrkUpdate();
    }

	public static function compile_referer_session() {
		if ( defined( 'WP_IMPORTING' ) ) {
			return;
		}

		self::maybe_start_session();

		self::add_referer_to_session();
		self::add_current_page_to_session();
		self::remove_visited_above_max();
	}

	private static function maybe_start_session() {
		if ( ! isset( $_SESSION ) ) {
			session_start();
		}
	}

	private static function add_referer_to_session() {
		if ( self::is_excluded_from_session( 'frm_http_referer' ) ) {
			$_SESSION['frm_http_referer'] = array();
		}

		if ( ! isset( $_SERVER['HTTP_REFERER'] ) ) {
			$direct = __( 'Type-in or bookmark', 'frmtrk' );
			if ( ! in_array( $direct, $_SESSION['frm_http_referer'] ) ) {
				$_SESSION['frm_http_referer'][] = $direct;
			}
		} else if ( strpos( $_SERVER['HTTP_REFERER'], FrmAppHelper::site_url() ) === false && ! in_array( $_SERVER['HTTP_REFERER'], $_SESSION['frm_http_referer'] ) ) {
			$_SESSION['frm_http_referer'][] = $_SERVER['HTTP_REFERER'];
		}
	}

	private static function add_current_page_to_session() {
		if ( self::is_excluded_from_session( 'frm_http_pages' ) ) {
			$_SESSION['frm_http_pages'] = array( 'http://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI']);
		}

		if ( $_SESSION['frm_http_pages'] && ! empty( $_SESSION['frm_http_pages'] ) && ( end( $_SESSION['frm_http_pages'] ) != 'http://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'] ) ) {
			$ext = substr( strrchr( substr( $_SERVER['REQUEST_URI'], 0, strrpos( $_SERVER['REQUEST_URI'], '?' ) ), '.' ), 1 );
			if ( ! in_array( $ext, array( 'css', 'js' ) ) ) {
				$_SESSION['frm_http_pages'][] = 'http://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
			}
		}
	}

	private static function remove_visited_above_max() {
		$total_pages_visited = count( $_SESSION['frm_http_pages'] );
		if ( $total_pages_visited > self::$page_max ) {
			$number_to_remove = $total_pages_visited - self::$page_max;
			$_SESSION['frm_http_pages'] = array_slice( $_SESSION['frm_http_pages'], $number_to_remove );
		}
	}

	private static function is_excluded_from_session( $key ) {
		return ( ! isset( $_SESSION[ $key ] ) || ! is_array( $_SESSION[ $key ] ) );
	}

	public static function insert_tracking_into_entry( $entry_id ) {
		self::get_referer_info();

		$entry = FrmEntry::getOne( $entry_id );
		$entry_description = maybe_unserialize( $entry->description );
		$entry_description['referrer'] = self::$referrer_info;

		global $wpdb;
		$wpdb->update( $wpdb->prefix .'frm_items', array( 'description' => serialize( $entry_description ) ), array( 'id' => $entry_id ) );
	}

	public static function get_referer_info(){
		self::add_referer_to_string();
		self::add_pages_to_string();
		self::add_keywords_to_string();
	}

	private static function add_referer_to_string() {
		$i = 1;
		if ( isset( $_SESSION ) && isset( $_SESSION['frm_http_referer'] ) && $_SESSION['frm_http_referer'] ) {
			foreach ( $_SESSION['frm_http_referer'] as $referer ) {
				self::$referrer_info .= str_pad( "Referer $i: ", 20 ) . $referer . "\r\n";
				$keywords_used = self::get_referer_query( $referer );
				if ( $keywords_used !== false ) {
					self::$keywords[] = $keywords_used;
				}

				$i++;
			}

			self::$referrer_info .= "\r\n";
		} else {
			self::$referrer_info = FrmAppHelper::get_server_value( 'HTTP_REFERER' );
		}
	}

	private static function add_pages_to_string() {
		$i = 1;
		if ( isset( $_SESSION ) && isset( $_SESSION['frm_http_pages'] ) && $_SESSION['frm_http_pages'] ) {
			foreach ( $_SESSION['frm_http_pages'] as $page ) {
				self::$referrer_info .= str_pad( "Page visited $i: ", 20 ) . $page . "\r\n";
				$i++;
			}

			self::$referrer_info .= "\r\n";
		}
	}

	private static function add_keywords_to_string() {
		$i = 1;
		foreach ( self::$keywords as $keyword ) {
			self::$referrer_info .= str_pad( "Keyword $i: ", 20 ) . $keyword . "\r\n";
			$i++;
		}
		self::$referrer_info .= "\r\n";
	}

	private static function get_referer_query( $query ) {
		if ( strpos( $query, 'google.' ) ) {
			//$pattern = '/^.*\/search.*[\?&]q=(.*)$/';
			$pattern = '/^.*[\?&]q=(.*)$/';
		} else if ( strpos( $query, 'bing.com' ) ) {
			$pattern = '/^.*q=(.*)$/';
		} else if ( strpos( $query, 'yahoo.' ) ) {
			$pattern = '/^.*[\?&]p=(.*)$/';
		} else if ( strpos( $query, 'ask.' ) ) {
			$pattern = '/^.*[\?&]q=(.*)$/';
		} else {
			return false;
		}

		preg_match( $pattern, $query, $matches );
		$querystr = substr( $matches[1], 0, strpos( $matches[1], '&' ) );
		return urldecode( $querystr );
	}
}

new FrmUserTracking();
