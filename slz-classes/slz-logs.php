<?php

class Slz_Demo_Logs {

	var $log_file = '';
	var $log_file_url = '';
	var $detail_log_file = '';
	var $detail_log_file_url = '';

	public function __construct() {
		if ( Slz_Demo()->settings['log'] == 1 ) {
			$log_dir = trailingslashit( WP_CONTENT_DIR . '/slz-logs' );
			$log_url = trailingslashit( WP_CONTENT_URL . '/slz-logs' );

			if ( ! is_dir( $log_dir ) )
				mkdir( $log_dir );
			$this->log_file = $log_dir . 'ns-cloner.log';
			$this->log_file_url = $log_url . 'ns-cloner.log';
			$this->detail_log_file = $log_dir . 'ns-cloner-' . date("Ymd-His", time()) . '.html';
			$this->detail_log_file_url = $log_url .'ns-cloner-' . date("Ymd-His", time()) . '.html';

			add_action( 'admin_notices', array( $this, 'check_logfile' ) );
		}
	}

	public function check_logfile() {
		if( ! file_exists( $this->log_file ) && Slz_Demo()->settings['log'] == 1 ) {
			$handle = fopen( $this->log_file, 'w' ) or printf( __( '<div class="error"><p>Unable to create log file %s. Is its parent directory writable by the server?</p></div>', 'ns-cloner' ), $this->log_file );
			fclose( $handle );
		}
	}

	public function log( $message ) {
		if ( Slz_Demo()->settings['log'] ==1 )
			error_log( date_i18n( 'Y-m-d H:i:s' ) . " - $message\n", 3, $this->log_file );
	}

	function dlog( $message ) {
		if ( Slz_Demo()->settings['log'] ==1 )
			error_log( date_i18n( 'Y-m-d H:i:s' ) . " - $message\n", 3, $this->detail_log_file );
	}

}