<?php

if ( ! defined( 'ABSPATH' ) )
	exit;

class Slz_Demo_Sandbox {

	var $db_host = DB_HOST;
	var $db_name = DB_NAME;
	var $db_port = '';
	var $db_user = DB_USER;
	var $db_pass = DB_PASSWORD;

	var $target_id = '';
	var $status = '';
	var $global_tables;
	var $site_address;

	public function __construct() {
		add_action( 'wp_footer', array( $this, 'purge' ) );
		add_action( 'slz_hourly', array( $this, 'check_purge' ) );
		add_action( 'init', array( $this, 'prevent_clone_check' ) );
		add_action( 'init', array( $this, 'deleted_site_check' ) );
		add_action( 'init', array( $this, 'reset_listen' ) );
		add_action( 'init', array( $this, 'update_state' ) );

		add_action( 'admin_bar_menu', array( $this, 'add_menu_bar_reset' ), 999 );

		$this->global_tables = apply_filters( 'slz_global_tables', array(
			'blogs','blog_versions','registration_log','signups','site','sitemeta',
			'usermeta','users',
			'bp_.*',
			'3wp_broadcast_.*'
		) );

		if ( strpos( $this->db_host, ':' ) ) {
			$server = explode( ':', $this->db_host );
			$this->db_host = $server[0];
			$this->db_port = $server[1];
		}
	}

	public function prevent_clone_check() {
		$current_url = add_query_arg( array() );
		if ( Slz_Demo()->settings['offline'] == 1 && ! Slz_Demo()->is_admin_user() && ( ( ! Slz_Demo()->is_sandbox() && strpos ( $current_url, '/wp-admin/' ) === false && strpos ( $current_url, 'wp-login.php' ) === false ) || Slz_Demo()->is_sandbox() ) )
			wp_die( __( apply_filters( 'slz_offline_msg', 'The demo is currently offline.' ), 'slz-demo' ) );
	}

	public function deleted_site_check() {
		global $wpdb;
		$blogs = $wpdb->get_results("
	        SELECT blog_id
	        FROM $wpdb->blogs
	        WHERE deleted = '1'"
	    );
	    foreach ( $blogs as $blog ) {
	    	if ( get_blog_option( $blog->blog_id, 'slz_sandbox' ) != 1 ) {
	    		$wpdb->update( $wpdb->blogs, array( 'deleted' => '0' ) , array( 'blog_id' => $blog->blog_id ) );
	    	}
	    }
	}

	public function count( $source_id = '' ) {
		global $wpdb;
		$blogs = $wpdb->get_results("
	        SELECT blog_id
	        FROM $wpdb->blogs
	        WHERE blog_id != 1"
	    );
	    $count = 0;
	    foreach ( $blogs as $blog ) {
	    	if ( get_blog_option( $blog->blog_id, 'slz_sandbox' ) == 1 ) {
	    		if ( $source_id == '' ) {
	    			$count++;
	    		} else if ( $source_id == get_blog_option( $blog->blog_id, 'slz_source_id' ) ) {
	    			$count++;
	    		}

	    	}
	    }
		return $count;
	}

	public function get_key( $blog_id = '' ) {
		if ( $blog_id == '' ) {
			if ( Slz_Demo()->is_sandbox() ) {
				return get_option( 'slz_sandbox_id' );
			} else {
				return false;
			}
		} else {
			return get_blog_option( $blog_id, 'slz_sandbox_id' );
		}
	}

	public function delete_all( $source_id = '' ) {
		global $wpdb;
		$blogs = $wpdb->get_results("
	        SELECT blog_id
	        FROM $wpdb->blogs
	        WHERE blog_id != 1"
	    );
	    foreach ( $blogs as $blog ) {
	    	if ( get_blog_option( $blog->blog_id, 'slz_sandbox' ) == 1 ) {
	    		if ( $source_id != '' ) {
	    			if ( get_blog_option( $blog->blog_id, 'slz_source_id' ) == $source_id ) {
						$this->delete( $blog->blog_id );
					}
	    		} else {
	    			$this->delete( $blog->blog_id );
	    		}
	    	}
		}
	}

	public function delete( $blog_id, $drop = true, $reset = false ) {
		global $wpdb;

		require_once ( ABSPATH . 'wp-admin/includes/ms.php' );

		$blog_id = intval( $blog_id );

		if ( get_blog_option( $blog_id, 'slz_sandbox' ) != 1 )
			return false;

		$source_id = get_option( 'slz_source_id' );

		$switch = false;
		if ( get_current_blog_id() != $blog_id ) {
			$switch = true;
			switch_to_blog( $blog_id );
		}

		$blog = get_blog_details( $blog_id );

		do_action( 'slz_delete_sandbox', $blog_id );

		$users = get_users( array( 'blog_id' => $blog_id, 'fields' => 'ids' ) );

		if ( ! empty( $users ) ) {
			foreach ( $users as $user_id ) {
				wpmu_delete_user( $user_id );
			}
		}

		update_blog_status( $blog_id, 'deleted', 1 );

		$current_site = get_current_site();

		if ( $drop && ( 1 == $blog_id || is_main_site( $blog_id ) || ( $blog->path == $current_site->path && $blog->domain == $current_site->domain ) ) )
			$drop = false;

		if ( $drop ) {

	   		$drop_tables = array();
	   		$tables = $wpdb->get_results( 'SHOW TABLES LIKE "' . $wpdb->prefix .'%"', ARRAY_A );
	   		foreach( $tables as $table ) {
	   			foreach( $table as $name ) {
	   				$drop_tables[] = $name;
	   			}
	   		}

			$drop_tables = apply_filters( 'wpmu_drop_tables', $drop_tables, $blog_id );

			foreach ( (array) $drop_tables as $table ) {
				$wpdb->query( "DROP TABLE IF EXISTS `$table`" );
			}

			$wpdb->delete( $wpdb->blogs, array( 'blog_id' => $blog_id ) );

			$wpdb->query( "DELETE FROM `" . $wpdb->usermeta . "` WHERE meta_key LIKE '" . $wpdb->prefix . "%'" );
			$wpdb->query( $wpdb->prepare( "DELETE FROM `" . $wpdb->registration_log . "` WHERE blog_id = %d", $blog_id ) );

			$uploads = wp_upload_dir();

			$dir = apply_filters( 'wpmu_delete_blog_upload_dir', $uploads['basedir'], $blog_id );
			$dir = rtrim( $dir, DIRECTORY_SEPARATOR );
			$top_dir = $dir;
			$stack = array($dir);
			$index = 0;

			while ( $index < count( $stack ) ) {
				$dir = $stack[$index];

				$dh = @opendir( $dir );
				if ( $dh ) {
					while ( ( $file = @readdir( $dh ) ) !== false ) {
						if ( $file == '.' || $file == '..' )
							continue;

						if ( @is_dir( $dir . DIRECTORY_SEPARATOR . $file ) )
							$stack[] = $dir . DIRECTORY_SEPARATOR . $file;
						else if ( @is_file( $dir . DIRECTORY_SEPARATOR . $file ) )
							@unlink( $dir . DIRECTORY_SEPARATOR . $file );
					}
					@closedir( $dh );
				}
				$index++;
			}

			$stack = array_reverse( $stack );
			foreach( (array) $stack as $dir ) {
				if ( $dir != $top_dir)
				@rmdir( $dir );
			}
			@rmdir( $dir );

			clean_blog_cache( $blog );
		}

		if ( isset( $_SESSION[ 'slz_sandbox_' . $source_id ] ) ) {
			unset( $_SESSION[ 'slz_sandbox_' . $source_id ] );
    	}

		if ( ! $reset && ! Slz_Demo()->is_admin_user() )
			wp_logout();

		if ( $switch )
			restore_current_blog();
	}

	public function check_purge() {
		add_action( 'wp_footer', array( $this, 'purge' ) );
	}

	public function purge() {
		global $wpdb;

		$blogs = $wpdb->get_results("
	        SELECT blog_id
	        FROM $wpdb->blogs
	        WHERE blog_id != 1" );

	    $sites = array();
	    $redirect = false;
	    foreach ( $blogs as $blog ) {

	    	if ( get_blog_option( $blog->blog_id, 'slz_sandbox' ) == 1 ) {
		   		if ( apply_filters( 'slz_purge_sandbox', $this->has_expired( $blog->blog_id ), $blog->blog_id ) ) {
					if ( $blog->blog_id == get_current_blog_id() ) {
						$redirect = true;
						$source_id = get_blog_option( $blog->blog_id, 'slz_source_id' );
					}
					$this->delete( $blog->blog_id );
		   		}
	    	}

		}

		if ( $redirect ) {
			wp_redirect( get_blog_details( 1 )->siteurl );
			exit;
		}
	}

	public function has_expired( $blog_id = '' ) {
		if ( $blog_id == '' )
			$blog_id = get_current_blog_id();

		$idle_limit = apply_filters( 'slz_sandbox_lifespan', 900, $blog_id ); // 900 seconds = 15 minutes
		$idle_time = current_time( 'timestamp' ) - strtotime( get_blog_details( $blog_id )->last_updated );

		if ( $idle_time >= $idle_limit ) {
			return true;
		} else {
			return false;
		}
	}

	public function is_active( $blog_id = '' ) {
		if ( $blog_id == '' )
			$blog_id = get_current_blog_id();

		$details = get_blog_details( $blog_id );
		if ( $details !== false ) {
			return true;
		} else {
			return false;
		}
	}

	public function add_menu_bar_reset( $wp_admin_bar ) {
		if ( Slz_Demo()->is_sandbox() ) {
			$url = add_query_arg( array( 'reset_sandbox' => 1 ) );
			$wp_admin_bar->add_menu( array(
		        'id'   => 'reset-site',
		        'meta' => array(),
		        'title' => __( 'Reset Content', 'slz-demo' ),
		        'href' => wp_nonce_url( $url, 'slz_demo_reset_sandbox', 'slz_demo_sandbox' ) ) );
		}
	}

	public function update_state() {
		global $wpdb;
		if ( Slz_Demo()->is_sandbox() )
			$wpdb->update( $wpdb->blogs, array( 'last_updated' => current_time( 'mysql' ) ), array( 'blog_id' => get_current_blog_id() ) );
	}

	public function reset_listen() {

		if ( ! isset ( $_GET['reset_sandbox'] ) || $_GET['reset_sandbox'] != 1 )
			return false;

		if ( ! isset ( $_GET['slz_demo_sandbox'] ) )
			return false;

		if ( ! wp_verify_nonce( $_GET['slz_demo_sandbox'], 'slz_demo_reset_sandbox' ) )
			return false;

		$this->reset();
	}

	public function reset() {
		$source_id = get_option( 'slz_source_id' );
		$this->delete( get_current_blog_id(), true, true );
		switch_to_blog( $source_id );
		$this->create( $source_id );
	}

	public function create( $source_id, $target_site_name = '' ) {
		global $wpdb, $report, $count_tables_checked, $count_items_checked, $count_items_changed, $current_site, $wp_version;

		$target_id = '';
		$target_subd = '';
		$target_site = '';

		$slz_settings = get_blog_option( $source_id, 'slz_demo' );

		$stimer = explode( ' ', microtime() );
		$stimer = $stimer[1] + $stimer[0];

		$target_site = get_blog_details( $source_id )->blogname;
		if ( $target_site_name == '' ) {
			$target_site_name = $this->generate_site_name();
		}

		$login_role = isset ( $slz_settings['login_role'] ) ? $slz_settings['login_role'] : 'administrator';

	    $user_name = apply_filters( 'slz_user_name' , $login_role . '-' . $target_site_name );

	    $user_email = apply_filters( 'slz_user_email' , $login_role . '@' . $target_site_name .'.com' );

	    $random_password = wp_generate_password( $length = 12, $include_standard_special_chars = false );

		$user_id = wp_create_user( $user_name, $random_password, $user_email );

		if( is_wp_error( $user_id ) ){
			wp_redirect(
				add_query_arg(
					array(
						'error' => 'true',
						'errorcode' => urlencode( $user_id->get_error_code() ),
						'errormsg' => urlencode( $user_id->get_error_message() ),
						'updated' => false
					),
					wp_get_referer()
				)
			);
			die;
		}

		if ( $login_role == 'administrator' ) {
			$owner_user_id = $user_id;
		} else {
		    $user_name = 'administrator-' . $target_site_name;
		    $user_email = 'administrator@' . $target_site_name .'.com';
		    $random_password = wp_generate_password( $length = 12, $include_standard_special_chars = false );
			$owner_user_id = wp_create_user( $user_name, $random_password, $user_email );
			remove_user_from_blog( $owner_user_id, $source_id );
		}

		$this->create_site( $target_site_name, $target_site, $source_id, $owner_user_id );

		Slz_Demo()->logs->dlog( 'RUNNING NS Cloner version: ' . slz_PLUGIN_VERSION . ' <br /><br />' );

		$source_subd = untrailingslashit( get_blog_details($source_id)->domain . get_blog_details($source_id)->path );

		$source_site = get_blog_details($source_id)->blogname;

		$target_id = $this->target_id;
		$target_subd = get_current_site()->domain . get_current_site()->path . $target_site_name;

		if( stripos($target_subd, $source_site) !== false ) {
				wp_redirect( add_query_arg(
					array('error' => 'true',
						'errorcode' => urlencode( $user_id->get_error_code() ),
						'errormsg' => urlencode( __( "The Source Site Name ($source_site) may not appear in the Target Site Domain ($target_subd) or data corruption will occur. You might need to edit the Source Site's Name in Settings > General, or double-check / change your field input values.", 'ns_cloner' ) ),
						'updated' => false),
					wp_get_referer() ) );
				die;
		} else{
			$source_pre = $source_id==1? $wpdb->base_prefix : $wpdb->base_prefix . $source_id . '_';
			$target_pre = $wpdb->base_prefix . $target_id . '_';	// the wp id of the target database

			Slz_Demo()->logs->dlog ( 'Source Prefix: <b>' . $source_pre . '</b><br />' );
			Slz_Demo()->logs->dlog ( 'Target Prefix: <b>' . $target_pre . '</b><br />' );

			$this->run_clone( $source_pre, $target_pre );
		}

		$target_pre = $wpdb->base_prefix . $target_id . '_';	// the wp id of the target database

		$replace_array[$source_subd] = $target_subd;
		$replace_array[$source_site] = $target_site;

		$main_uploads_target = '';
		if( 1 == $source_id ){
			switch_to_blog( 1 );
			$main_uploads_info = wp_upload_dir();
			restore_current_blog();
			$main_uploads_dir = $main_uploads_info['baseurl'];
			$main_uploads_replace = '';

			$main_uploads_target = WP_CONTENT_DIR . '/uploads/sites/' . $target_id;
			$main_uploads_replace = $main_uploads_info['baseurl'] . '/sites/' . $target_id;

			$replace_array[$main_uploads_dir] = $main_uploads_replace;

			$report .= 'Search Source Dir: <b>' . $main_uploads_dir . '</b><br />';
			$report .= 'Replace Target Dir: <b>' . $main_uploads_replace . '</b><br />';
			// --------------------------------------
			$replace_array[$wpdb->base_prefix . 'user_roles'] = $wpdb->base_prefix . $target_id . '_user_roles';
		} else {
			$replace_array['/sites/' . $source_id . '/'] = '/sites/' . $target_id . '/';
			$replace_array[$wpdb->base_prefix . $source_id . '_user_roles'] = $wpdb->base_prefix . $target_id . '_user_roles';
		}

		Slz_Demo()->logs->dlog ( 'running replace on Target table prefix: ' . $target_pre . '<br />' );
		foreach( $replace_array as $search_for => $replace_with) {
			Slz_Demo()->logs->dlog ( 'Replace: <b>' . $search_for . '</b> >> With >> <b>' . $replace_with . '</b><br />' );
		}

		$this->run_replace( $target_pre, $replace_array );

		$src_blogs_dir = $this->get_upload_folder($source_id);

		if( 1 == $source_id ){
			$dst_blogs_dir = $main_uploads_target;
		} else {
			$dst_blogs_dir = $this->get_upload_folder($this->target_id);
		}

		if (strpos($src_blogs_dir,'/') !== false && strpos($src_blogs_dir,'\\') !== false ) {
			$src_blogs_dir = str_replace('/', '\\', $src_blogs_dir);
			$dst_blogs_dir = str_replace('/', '\\', $dst_blogs_dir);
		}
		if (is_dir($src_blogs_dir)) {

			$num_files = $this->recursive_file_copy($src_blogs_dir, $dst_blogs_dir, 0);
			$report .= 'Copied: <b>' . $num_files . '</b> folders and files!<br />';
			Slz_Demo()->logs->dlog ('Copied: <b>' . $num_files . '</b> folders and files!<br />');
			Slz_Demo()->logs->dlog ('From: <b>' . $src_blogs_dir . '</b><br />');
			Slz_Demo()->logs->dlog ('To: <b>' . $dst_blogs_dir . '</b><br />');
		}
		else {
			$report .= '<span class="warning-txt-title">Could not copy files</span><br />';
			$report .= 'From: <b>' . $src_blogs_dir . '</b><br />';
			$report .= 'To: <b>' . $dst_blogs_dir . '</b><br />';
		}

		switch_to_blog( $this->target_id );

		$_SESSION[ 'slz_sandbox_' . $source_id ] = $this->target_id;

	    update_blog_option( $this->target_id, 'blog_public', 0 );

	    update_blog_option( $this->target_id, 'slz_sandbox_id', $target_site_name );

	    update_blog_option( $this->target_id, 'slz_sandbox', 1 );

	    update_blog_option( $this->target_id, 'slz_source_id', $source_id );

	    update_blog_option( $this->target_id, 'slz_user', $user_name );
	    update_blog_option( $this->target_id, 'slz_password', $random_password );

		add_user_to_blog( $this->target_id, $user_id, $login_role );
		remove_user_from_blog( $user_id, $source_id );
		wp_clear_auth_cookie();
	    wp_set_auth_cookie( $user_id, true );
	    wp_set_current_user( $user_id );

	    $wpdb->update( $wpdb->blogs, array( 'last_updated' => current_time( 'mysql' ) ), array( 'blog_id' => $this->target_id ) );

	    $plugins = get_option( 'active_plugins' );

	    if ( ! empty( $plugins ) ) {
		    foreach( $plugins as $plugin ) {
			    if ( apply_filters( 'slz_activate_plugin', false, $plugin ) ) {
					deactivate_plugins( $plugin );
					activate_plugin( $plugin );
				}
		    }
	    }

		do_action( 'slz_create_sandbox', $this->target_id );

		Slz_Demo()->logs->dlog ( $report );

		$etimer = explode( ' ', microtime() );
		$etimer = $etimer[1] + $etimer[0];
		Slz_Demo()->logs->log ( $target_subd . " cloned in " . ($etimer-$stimer) . " seconds."  );
		Slz_Demo()->logs->dlog ( "Entire cloning process took: <strong>" . ($etimer-$stimer) . "</strong> seconds."  );

		update_blog_option( $this->target_id, 'siteurl', $this->site_address );
		update_blog_option( $this->target_id, 'home', $this->site_address );

		wp_redirect( apply_filters( 'slz_create_redirect', $this->site_address, $this->target_id ) );
		die();
	}

	private function generate_site_name() {
		$key = Slz_Demo()->random_string();

	    $site_id = get_id_from_blogname( $key );

	    if ( ! empty( $site_id ) ) {
	    	return $this->generate_site_name( $length );
	    } else {
	    	return $key;
	    }
	}

	private function create_site( $sitename, $sitetitle, $source_id, $user_id ) {
		global $wpdb, $current_site, $current_user;
		get_currentuserinfo();

		$base = PATH_CURRENT_SITE;

		$tmp_domain = strtolower( esc_html( $sitename ) );

		if( constant( 'VHOST' ) == 'yes' ) {
			$tmp_site_domain = $tmp_domain . '.' . $current_site->domain;
			$tmp_site_path = $base;
		} else {
			$tmp_site_domain = $current_site->domain;
			$tmp_site_path = $base . $tmp_domain . '/';
		}

		$create_site_name = $sitename;
		$create_site_title = $sitetitle;

		$site_id = get_id_from_blogname( $create_site_name );

		$meta['public'] = 1;
		$site_id = wpmu_create_blog( $tmp_site_domain, $tmp_site_path, $create_site_title, $user_id , $meta, $current_site->id );

		if( ! is_wp_error( $site_id ) ) {
			Slz_Demo()->logs->log( 'Site: ' . $tmp_site_domain . $tmp_site_path . ' created!' );
			$this->target_id = $site_id;
		} else {
			Slz_Demo()->logs->log( 'Error creating site: ' . $tmp_site_domain . $tmp_site_path . ' - ' . $site_id->get_error_message() );
		}

		if ( is_ssl() ) {
			$protocol = 'https://';
		} else {
			$protocol = 'http://';
		}

		$this->site_address = $protocol . $tmp_site_domain . $tmp_site_path;

		update_blog_option( $this->target_id, 'siteurl', $this->site_address );
		update_blog_option( $this->target_id, 'home', $this->site_address );

		$slz_settings = get_blog_option( $source_id, 'slz_demo' );
		if ( isset ( $slz_settings['auto_login'] )  && isset ( $slz_settings ) ) {
			if ( $slz_settings['auto_login'] != '' && $slz_settings['login_role'] != '' ) {
				$user = $slz_settings['auto_login'];
				$role = $slz_settings['login_role'];
				add_user_to_blog( $this->target_id, $user, $role );
			}
		}
	}

	private function run_clone( $source_prefix, $target_prefix ) {
		global $report, $wpdb;

		$sandboxes = wp_get_sites();

		if( $source_prefix == $wpdb->base_prefix ){
			$tables = $wpdb->get_results('SHOW TABLES');
			$global_table_pattern = "/^$wpdb->base_prefix(" .implode('|',$this->global_tables). ")$/";
			$table_names = array();
			foreach($tables as $table){
				$table = (array)$table;
				$table_name = array_pop( $table );
				$is_root_table = preg_match( "/$wpdb->prefix(?!\d+_)/", $table_name );
				if($is_root_table && !preg_match($global_table_pattern,$table_name)){
					array_push($table_names, $table_name);
				}
			}
			$SQL = "SHOW TABLES WHERE `Tables_in_" . $this->db_name . "` IN('" . implode( "','", $table_names ). "')";
		} else {
			$SQL = 'SHOW TABLES LIKE \'' . str_replace( '_', '\_', $source_prefix ) . '%\'';
		}

		$tables_list = $wpdb->get_results( $SQL, ARRAY_N );

		$num_tables = 0;

		if ( isset ( $tables_list[0] ) && ! empty ( $tables_list[0] ) ) {
			foreach ( $tables_list as $tables ) {
				$source_table = $tables[0];

				foreach ( $sandboxes as $s ) {
					if ( Slz_Demo()->is_sandbox( $s['blog_id'] ) && strpos( $source_table, $wpdb->base_prefix . $s['blog_id'] ) !== false ) {
						continue 2;
					}
				}

				$pos = strpos( $source_table, $source_prefix );
				if ( $pos === 0 ) {
				    $target_table = substr_replace( $source_table, $target_prefix, $pos, strlen( $source_prefix ) );
				}

				$num_tables++;
				if ($source_table != $target_table) {
					Slz_Demo()->logs->dlog ( '-----------------------------------------------------------------------------------------------------------<br />' );
					Slz_Demo()->logs->dlog ( 'Cloning source table: <b>' . $source_table . '</b> (table #' . $num_tables . ') to Target table: <b>' . $target_table . '</b><br />' );
					Slz_Demo()->logs->dlog ( '-----------------------------------------------------------------------------------------------------------<br />' );

					$this->clone_table( $source_table, $target_table );
					
				}
				else {
					Slz_Demo()->logs->dlog ( '-----------------------------------------------------------------------------------------------------------<br />');
					Slz_Demo()->logs->dlog ( 'Source table: <b>' . $source_table . '</b> (table #' . $num_tables . ') and Target table: <b>' . $target_table . ' are the same! SKIPPING!!!</b><br />');
					Slz_Demo()->logs->dlog ( '-----------------------------------------------------------------------------------------------------------<br />');
				}
			}

		}
		else {
			Slz_Demo()->logs->dlog ( 'no data for sql - ' . $SQL );
		}

		if (isset($_POST['is_debug'])) { Slz_Demo()->logs->dlog ( '-----------------------------------------------------------------------------------------------------------<br /><br />'); }
		$report .= 'Cloned: <b>' .$num_tables . '</b> tables!<br/ >';
		Slz_Demo()->logs->dlog('Cloned: <b>' .$num_tables . '</b> tables!<br/ >');
	}

	private function backquote( $a_name ) {

		if (!empty($a_name) && $a_name != '*') {
			if (is_array($a_name)) {
				$result = array();
				reset($a_name);
				while(list($key, $val) = each($a_name)) {
					$result[$key] = '`' . $val . '`';
				}
				return $result;
			} else {
				return '`' . $a_name . '`';
			}
		} else {
			return $a_name;
		}
	}

	function sql_addslashes($a_string = '', $is_like = FALSE) {

		if ($is_like) {
			$a_string = str_replace('\\', '\\\\\\\\', $a_string);
		} else {
			$a_string = str_replace('\\', '\\\\', $a_string);
		}
		$a_string = str_replace('\'', '\\\'', $a_string);

		return $a_string;
	}

	private function clone_table( $source_table, $target_table ) {
		global $wpdb;

		$query_count = Slz_Demo()->settings['query_count'];

		$sql_statements = '';

		$query = "DROP TABLE IF EXISTS " . $this->backquote( $target_table );

		if ( isset( $_POST['is_debug'] ) )
			Slz_Demo()->logs->dlog ( $query . '<br /><br />');

		$result = $wpdb->query( $query );
		if ( $result == false )
			Slz_Demo()->logs->dlog ( '<b>ERROR</b> dropping table with sql - ' . $query . '<br /><b>SQL Error</b> - ' . $wpdb->last_error . '<br />' );

		$query = "SHOW CREATE TABLE " . $this->backquote( $source_table );
		$result = $wpdb->get_row( $query, ARRAY_A );

		if ( $result == false ) {
			Slz_Demo()->logs->dlog ( '<b>ERROR</b> getting table structure with sql - ' . $query . '<br /><b>SQL Error</b> - ' . $wpdb->last_error . '<br />' );
		} else {
			if ( ! empty ( $result ) ) {
				$sql_statements .= $result[ 'Create Table' ];
			}
		}

		$query = str_replace( $source_table, $target_table, $sql_statements );
		if ( isset( $_POST['is_debug'] ) )
			Slz_Demo()->logs->dlog ( $query . '<br /><br />');

		$result = $wpdb->query( $query );
		if ( $result == false )
			Slz_Demo()->logs->dlog ( '<b>ERROR</b> creating table structure with sql - ' . $query . '<br /><b>SQL Error</b> - ' . $wpdb->last_error . '<br />' );

		$query = "SELECT * FROM " . $this->backquote( $source_table );
		$result = $wpdb->get_results( $query, ARRAY_N );

		$fields_cnt = 0;
		if ( $result == false ) {
			Slz_Demo()->logs->dlog ( '<b>ERROR</b> getting table contents with sql - ' . $query . '<br /><b>SQL Error</b> - ' . $wpdb->last_error . '<br />' );
		} else {
			$fields_cnt = count( $result[0] );
			$rows_cnt   = $wpdb->num_rows;
		}

		for ( $j = 0; $j < $fields_cnt; $j++ ) {
			$type = $wpdb->get_col_info( 'type', $j );
			if ( $type == 'tinyint' || $type == 'smallint' || $type == 'mediumint' || $type == 'int' || $type == 'bigint') {
				$field_num[ $j ] = true;
			} else {
				$field_num[ $j ] = false;
			}
		}

		$entries = 'INSERT INTO ' . $this->backquote($target_table) . ' VALUES (';
		$search	= array("\x00", "\x0a", "\x0d", "\x1a"); 	//\x08\\x09, not required
		$replace	= array('\0', '\n', '\r', '\Z');

		$table_query = '';
		$table_query_count = 0;

		foreach( $result as $row ) {

			$is_trans = false;
			for ($j = 0; $j < $fields_cnt; $j++) {
				if ( ! isset($row[ $j ] ) ) {
					$values[]     = 'NULL';
				} else if ( $row[ $j ] == '0' || $row[ $j ] != '') {
					// a number
					if ($field_num[$j]) {
						$values[] = $row[$j];
					}
					else {
						if ( !$is_trans && false === strpos($row[$j],'_transient_') ) {
							$row[$j] = str_replace( "&#039;", "'", $row[$j] );
							$values[] = "'" . str_replace( $search, $replace, $this->sql_addslashes( $row[$j] ) ) . "'";
						}
						else {
							$values[]     = "''";
							$is_trans = false;
						}
						(strpos($row[$j],'_transient_') === false && strpos($row[$j],'_transient_timeout_') === false) ? $is_trans = false : $is_trans = true;

					}
				} else {
					$values[]     = "''";
				} 
			}

			$current_query = $entries . implode(', ', $values) . ');';
			$table_query .= $current_query;
			$table_query_count++;

			unset( $values );

			if ( $table_query_count >= $query_count ) {
				$this->insert_query( $table_query );
				$table_query_count = 0;
				$table_query = '';
			}

		}
		

		if ( ! empty( $table_query ) ) {
			$this->insert_query( $table_query );
		}

	}

	private function insert_query( $query ) {

		if ( $this->db_port != '' ) {
			$insert = mysqli_connect( $this->db_host, $this->db_user, $this->db_pass, $this->db_name, $this->db_port );
		} else {
			$insert = mysqli_connect( $this->db_host, $this->db_user, $this->db_pass, $this->db_name );
		}

		mysqli_set_charset( $insert, DB_CHARSET );

		$results = mysqli_multi_query( $insert, $query );
		if ( $results == FALSE ) { Slz_Demo()->logs->dlog ( '<b>ERROR</b> inserting into table with sql - ' . $query . '<br /><b>SQL Error</b> - ' . mysqli_error( $insert ) . '<br />'); }
		mysqli_close( $insert );
	}

	private function run_replace( $target_prefix, $replace_array ) {
		global $report, $count_tables_checked, $count_items_checked, $count_items_changed, $wpdb;

		$SQL = 'SHOW TABLES LIKE \'' . str_replace('_','\_',$target_prefix) . '%\'';

		$tables_list = $wpdb->get_results( $SQL, ARRAY_N );

		$num_tables = 0;

		if ( isset ( $tables_list[0] ) && ! empty ( $tables_list[0] ) ) {
			foreach ( $tables_list as $table ) {

				$table = $table[0];

				$count_tables_checked++;
				Slz_Demo()->logs->dlog ( '-----------------------------------------------------------------------------------------------------------<br />');
				Slz_Demo()->logs->dlog ( 'Searching table: <b>' . $table . '</b><br />');  // we have tables!
				Slz_Demo()->logs->dlog ( '-----------------------------------------------------------------------------------------------------------<br />');

				$SQL = "DESCRIBE ".$table ;
				$fields_list = $wpdb->get_results( $SQL, ARRAY_A );

				$index_fields = "";
				$column_name = "";
				$table_index = "";
				$i = 0;

				foreach ( $fields_list as $field_rows ) {
					$column_name[ $i++ ] = $field_rows['Field'];
					if ( $field_rows['Key'] == 'PRI')
						$table_index[] = $field_rows['Field'] ;
				}

				if( empty( $table_index) ) continue;

				$SQL = "SELECT * FROM ".$table;
				$data = $wpdb->get_results( $SQL, ARRAY_A );

				if ( ! isset ( $data[0] ) || empty ( $data[0] ) ) {
					Slz_Demo()->logs->dlog ("<br /><b>ERROR:</b> " . $wpdb->last_error . "<br/>$SQL<br/>"); }

				foreach ( $data as $row ) {


					$need_to_update = false;
					$UPDATE_SQL = 'UPDATE '.$table. ' SET ';
					$WHERE_SQL = ' WHERE ';
					foreach($table_index as $index){
						$WHERE_SQL .= "$index = '$row[$index]' AND ";
					}

					$j = 0;

					foreach ($column_name as $current_column) {

						$data_to_fix = $edited_data = $row[$current_column];
						$j++;

						foreach( $replace_array as $search_for => $replace_with) {
							$count_items_checked++;
							if (is_serialized($data_to_fix)) {
								$unserialized = unserialize($edited_data);
								$this->recursive_array_replace($search_for, $replace_with, $unserialized);
								$edited_data = serialize($unserialized);
							}
							elseif (is_string($data_to_fix)){
								$edited_data = str_replace($search_for,$replace_with,$edited_data) ;
							}
						}


						if ($data_to_fix != $edited_data) {
							$count_items_changed++;
							if ($need_to_update != false) $UPDATE_SQL = $UPDATE_SQL.',';
							$UPDATE_SQL = $UPDATE_SQL.' '.$current_column.' = "' . esc_sql( $edited_data ). '"';
							$need_to_update = true;
						}

					}

					if ($need_to_update) {
						$count_updates_run;
						$WHERE_SQL = substr($WHERE_SQL,0,-4); // strip off the excess AND - the easiest way to code this without extra flags, etc.
						$UPDATE_SQL = $UPDATE_SQL.$WHERE_SQL;
						if (isset($_POST['is_debug'])) { Slz_Demo()->logs->dlog ( $UPDATE_SQL.'<br/><br/>'); }
						$result = $wpdb->query( $UPDATE_SQL );
						if (!$result) Slz_Demo()->logs->dlog (("<br /><b>ERROR: </b>" . $wpdb->last_error . "<br/>$UPDATE_SQL<br/>"));
					}
				}
			}
		}
	}

	public function get_upload_folder( $id ) {
		switch_to_blog( $id );
		$src_upload_dir = wp_upload_dir();
		restore_current_blog();
		Slz_Demo()->logs->dlog('Original basedir returned by wp_upload_dir() = <strong>'.$src_upload_dir['basedir'].'</strong><br />');
		$folder = str_replace('/files', '', $src_upload_dir['basedir']);
		$content_dir = '';
		if ( $id!=1 && (strpos($folder, '/'.$id) === false || !file_exists($folder)) ) {
			$content_dir = WP_CONTENT_DIR; //no trailing slash
			Slz_Demo()->logs->dlog('Non-standard result from wp_upload_dir() detected. <br />');
			Slz_Demo()->logs->dlog('Normalized content_dir = '.$content_dir.'<br />');
			$test_dir = $content_dir . '/blogs.dir/' . $id;
			if (file_exists($test_dir)) {
				Slz_Demo()->logs->dlog('Found actual uploads folder at '.$test_dir.'<br />');
				return $test_dir;
			}
			$test_dir = $content_dir . '/uploads/sites/' . $id;
			if (file_exists($test_dir)) {
				Slz_Demo()->logs->dlog('Found actual uploads folder at '.$test_dir.'<br />');
				return $test_dir;
			}
		}
		return $folder;
	}

	public function recursive_file_copy($src, $dst, $num, $root = true) {
		$num = $num + 1;
		if ( is_dir( $src ) ) {
			if ( !file_exists ( $dst ) ) {
		        global $wp_filesystem;
		        if ( empty ( $wp_filesystem ) ) {
		            require_once ( ABSPATH . '/wp-admin/includes/file.php' );
		            WP_Filesystem();
		        }
		        mkdir($dst, 0777, true);
		    }
			$files = scandir( $src );
			foreach ( $files as $file ){
				if ( $file != "." && $file != ".." ){
					if( $file != 'sites' || $root == false ){
						$num = $this->recursive_file_copy("$src/$file", "$dst/$file", $num, false);
					}
				}
			}
		}
		else if ( file_exists ( $src ) ) copy( $src, $dst );
		return $num;
	}

	public function recursive_array_replace($find, $replace, &$data) {
		if (is_array($data)) {
			foreach ($data as $key => $value) {
				if (is_array($value)) {
					$this->recursive_array_replace($find, $replace, $data[$key]);
				} else {
					if (is_string($value)) $data[$key] = str_replace($find, $replace, $value);
				}
			}
		} else {
			if (is_string($data)) $data = str_replace($find, $replace, $data);
		}
	}
}
