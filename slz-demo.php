<?php
/*
Plugin Name: Slz Demo
Plugin URI: http://swlabs.co
Description: Easy for create demo page
Version: 1.0.0
Author: swlabs
Author URI: http://swlabs.co
Text Domain: slz-demo
Domain Path: /lang/
*/

if ( ! defined( 'ABSPATH' ) )
	exit;

class Slz_Demo {

	private static $instance;

	var $settings;
	var $version = '1.0.13';
	var $cached_source_id = '';

	public static function instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Slz_Demo ) ) {
			self::$instance = new Slz_Demo;
			self::$instance->setup_constants();
			self::$instance->includes();

			register_activation_hook( __FILE__, array( self::$instance, 'activation' ) );
			add_action( 'init', array( self::$instance, 'init' ), 5 );
			add_action( 'wp_enqueue_scripts', array( self::$instance, 'display_css' ), 999 );
			add_action( 'wp_enqueue_scripts', array( self::$instance, 'display_js' ) );
					
			add_filter( 'widget_text', 'do_shortcode' );

			add_action( 'plugins_loaded', array( self::$instance, 'load_lang' ) );
		}
		return self::$instance;
	}

	public function init() {
		self::$instance->get_settings();
		self::$instance->admin_settings = new Slz_Demo_Admin();
		self::$instance->sandbox = new Slz_Demo_Sandbox();
		self::$instance->restrictions = new Slz_Demo_Restrictions();
		self::$instance->logs = new Slz_Demo_Logs();
		self::$instance->shortcodes = new Slz_Demo_Shortcodes();
	}

	public function load_lang() {

		$textdomain = 'slz-demo';
		$locale = apply_filters( 'plugin_locale', get_locale(), $textdomain );
		$wp_lang_dir = apply_filters(
			'slz_demo_wp_lang_dir',
			WP_LANG_DIR . '/slz-demo/' . $textdomain . '-' . $locale . '.mo'
		);
		load_textdomain( $textdomain, $wp_lang_dir );
		$plugin_dir = basename( dirname( __FILE__ ) );
		$lang_dir = apply_filters( 'slz_demo_lang_dir', $plugin_dir . '/lang/' );
		load_plugin_textdomain( $textdomain, FALSE, $lang_dir );
	}

	public function __clone() {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'slz-demo' ), '1.6' );
	}

	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'slz-demo' ), '1.6' );
	}

	private function setup_constants() {
		global $wpdb;

		if ( ! defined( 'SLZ_PLUGIN_VERSION' ) ) {
			define( 'SLZ_PLUGIN_VERSION', '1.0' );
		}

		if ( ! defined( 'SLZ_PLUGIN_DIR' ) ) {
			define( 'SLZ_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
		}

		if ( ! defined( 'SLZ_PLUGIN_URL' ) ) {
			define( 'SLZ_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
		}

		if ( ! defined( 'SLZ_PLUGIN_FILE' ) ) {
			define( 'SLZ_PLUGIN_FILE', __FILE__ );
		}

		switch_to_blog( 1 );
		restore_current_blog();
	}

	private function get_settings() {
		if ( self::$instance->source_id() ) {
			$settings = get_blog_option( self::$instance->source_id(), 'slz_demo' );
		} else {
			$settings = get_option( 'slz_demo' );
		}

		$plugin_settings = get_site_option( 'slz_demo' );

		$settings['parent_pages'] = isset ( $settings['parent_pages'] ) ? $settings['parent_pages'] : array();
		$settings['child_pages'] = isset ( $settings['child_pages'] ) ? $settings['child_pages'] : array();
		$settings['log'] = isset ( $settings['log'] ) ? $settings['log'] : 0;
		$settings['prevent_clones'] = isset ( $settings['prevent_clones'] ) ? $settings['prevent_clones'] : '';
		$settings['offline'] = isset ( $settings['offline'] ) ? $settings['offline'] : '';
		$settings['show_toolbar'] = isset ( $settings['show_toolbar'] ) ? $settings['show_toolbar'] : 1;
		$settings['auto_login'] = isset ( $settings['auto_login'] ) ? $settings['auto_login'] : '';
		$settings['login_role'] = isset ( $settings['login_role'] ) ? $settings['login_role'] : '';
		$settings['theme_site'] = isset ( $settings['theme_site'] ) ? $settings['theme_site'] : '';
		$settings['query_count'] = isset ( $settings['query_count'] ) ? $settings['query_count'] : 4;
		
		if ( isset ( $settings['admin_id'] ) ) {
			$plugin_settings['admin_id'] = $settings['admin_id'];
			unset( $settings['admin_id'] );
			self::$instance->update_settings( $settings );
			self::$instance->update_plugin_settings( $plugin_settings );
		}

	    self::$instance->settings = $settings;

		$plugin_settings['theme_sites'] = isset ( $plugin_settings['theme_sites'] ) ? $plugin_settings['theme_sites'] : array();

	    self::$instance->plugin_settings = $plugin_settings;
	}

	private function includes() {
		require_once( SLZ_PLUGIN_DIR . 'slz-classes/slz-admin.php' );
		require_once( SLZ_PLUGIN_DIR . 'slz-classes/slz-sandbox.php' );
		require_once( SLZ_PLUGIN_DIR . 'slz-classes/slz-restrictions.php' );
		require_once( SLZ_PLUGIN_DIR . 'slz-classes/slz-logs.php' );
		require_once( SLZ_PLUGIN_DIR . 'slz-classes/slz-shortcodes.php' );
	}

	public function update_settings( $args ) {
		self::$instance->settings = $args;
		update_option( 'slz_demo', $args );
	}	

	public function update_plugin_settings( $args ) {
		self::$instance->plugin_settings = $args;
		update_site_option( 'slz_demo', $args );
	}

	public function display_js() {

	}

	public function display_css() {
		wp_enqueue_style( 'slz-demo-admin', SLZ_PLUGIN_URL .'slz-css/slz-display.css' );
	}

	public function random_string( $length = 15 ) {
		$string = '';
	    $keys = array_merge( range(0, 9), range('a', 'z') );

	    for ( $i = 0; $i < $length; $i++ ) {
	        $string .= $keys[ array_rand( $keys ) ];
	    }

	    $string = sanitize_title_with_dashes( $string );
	    return $string;
	}

	public function activation() {
		global $wpdb;
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		
		if ( get_site_option( 'slz_demo' ) == false ) {
			$args = array(
				'admin_id' 			=> get_current_user_id(),
				'theme_sites'		=> array()
			);
			update_site_option( 'slz_demo', $args );
		}

		if ( get_option( 'slz_demo' ) == false ) {
			$args = array(
				'offline' 			=> 1,
				'prevent_clones' 	=> 0,
				'log'				=> 0,
				'auto_login'		=> '',
				'parent_pages'		=> array(),
				'child_pages'		=> array(),
				'show_toolbar'		=> 0
			);
			update_option( 'slz_demo', $args );
		}


		wp_schedule_event( current_time( 'timestamp' ), 'hourly', 'slz_hourly' );
	}

	public function is_admin_user() {
		return current_user_can( 'manage_network_options' );
	}

	public function purge_wpengine_cache() {
		if ( class_exists( 'WpeCommon' ) ) {
			ob_start();
			WpeCommon::purge_memcached();
			WpeCommon::clear_maxcdn_cache();
			WpeCommon::purge_varnish_cache();
			WpeCommon::empty_all_caches();
			$errors = ob_get_contents();
			ob_eslz_clean();
		}
	}

	public function recursive_array_search( $needle, $haystack ) {
	    foreach( $haystack as $key => $value ) {
	        $current_key = $key;
	        if( $needle === $value OR ( is_array( $value ) && self::$instance->recursive_array_search( $needle, $value ) !== false ) ) {
	            return $current_key;
	        }
	    }
	    return false;
	}

	public function is_sandbox( $blog_id = '' ) {
		if ( $blog_id != '' ) {
			if ( get_blog_option( $blog_id, 'slz_sandbox' ) == 1 ) {
				return true;
			} else {
				return false;
			}
		} else {
			if ( get_option( 'slz_sandbox' ) == 1 ) {
				return true;
			} else {
				return false;
			}			
		}

	}

	public function source_id() {
		if ( self::$instance->is_sandbox() ) {
			return get_option( 'slz_source_id' );
		} else {
			return false;
		}
	}

	public function html_entity_decode_deep( $value ) {
    	$value = is_array($value) ?
	        array_map( array( self::$instance, 'html_entity_decode_deep' ), $value ) :
	        html_entity_decode( $value );
    	return $value;
	}

}

function Slz_Demo() {
	return Slz_Demo::instance();
}

Slz_Demo();
