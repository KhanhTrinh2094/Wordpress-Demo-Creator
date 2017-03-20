<?php

class Slz_Demo_Restrictions {

	public function __construct() {
		add_action( 'init', array( $this, 'offline_check' ) );
		add_action( 'init', array( $this, 'main_site_check' ) );
		add_action( 'current_screen', array( $this, 'remove_pages' ), 999 );
		add_filter( 'show_password_fields', array( $this, 'disable_passwords' ) );
	    add_filter( 'allow_password_reset', array( $this, 'disable_passwords' ) );
	    add_action( 'personal_options_update', array( $this, 'disable_email_editing' ), 1 );
	    add_action( 'edit_user_profile_update', array( $this, 'disable_email_editing' ), 1 );
		add_action( 'admin_bar_menu', array( $this, 'remove_menu_bar_items' ), 999 );
		add_action( 'delete_blog', array( $this, 'prevent_delete_blog' ), 10, 2 );
	}

	public function offline_check() {
		$current_url = add_query_arg( array() );
		if ( Slz_Demo()->settings['offline'] == 1 && ! Slz_Demo()->is_admin_user() && ( ( ! Slz_Demo()->is_sandbox() && strpos ( $current_url, '/wp-admin/' ) === false && strpos ( $current_url, 'wp-login.php' ) === false ) || Slz_Demo()->is_sandbox() ) )
			wp_die( __( apply_filters( 'slz_offline_msg', 'The demo is currently offline.' ), 'slz-demo' ) );
	}

	public function main_site_check() {
		$source_id = get_current_blog_id();
		if ( isset ( $_SESSION[ 'slz_sandbox_' . $source_id ] ) && ! Slz_Demo()->is_admin_user() ) {
			if ( Slz_Demo()->sandbox->is_active( $_SESSION[ 'slz_sandbox_' . $source_id ] ) && ! Ninja_demo()->is_sandbox() ) {
				wp_redirect( get_blog_details( $_SESSION[ 'slz_sandbox_' . $source_id ] )->siteurl );
				die;
			} else {
				unset( $_SESSION[ 'slz_sandbox_' . $source_id ] );
				wp_redirect( add_query_arg( array( 'sandbox_expired' => 1 ), get_blog_details( $source_id )->siteurl ) );
				die();
			}
		
		}

		if ( is_admin() && ! Slz_Demo()->is_sandbox() && is_user_logged_in() && ! Slz_Demo()->is_admin_user() ) {
		}
	}

	public function remove_pages() {
		global $menu, $pagenow, $submenu;

		if ( ! Slz_Demo()->is_admin_user() && is_admin() ) {
			$sub_menu = Slz_Demo()->html_entity_decode_deep( $submenu );
			$allowed_pages = apply_filters( 'slz_allowed_pages', array( 'options.php', 'index.php' ) );
			$allowed_cpts = array();
			$allowed_cts = array();
			Slz_Demo()->settings['parent_pages'][] = 'index.php';

			$allowed_menu_links = apply_filters( 'slz_show_menu_pages', Slz_Demo()->settings['parent_pages'] );
			$allowed_submenu_links = apply_filters( 'slz_show_submenu_pages', Slz_Demo()->settings['child_pages'] );

			foreach ( $menu as $item ) {
				$parent_slug = $item[2];

				if ( ! in_array( $parent_slug, $allowed_menu_links ) ) {
					remove_menu_page( $parent_slug );
				} else {
					$allowed_pages[] = $parent_slug;
				}

				if ( isset ( $sub_menu[ $parent_slug ] ) ) {
					foreach( $sub_menu[ $parent_slug ] as $sub_item ) {
						$child_slug = $sub_item[2];
						$found = false;
						foreach ( $allowed_submenu_links as $allowed_submenu ) {

							if ( $allowed_submenu['parent'] == $parent_slug && $allowed_submenu['child'] == $child_slug ) {
								if ( strpos( $allowed_submenu['child'], 'post_type=' ) !== false ) {
									// Get our post type from our string.
									$start = strpos( $allowed_submenu['child'], 'post_type=' ) + 10;
									$end = strpos( $allowed_submenu['child'], '&', $start );
									$length = $end - $start;
									if ( $end !== false ) {
										$substr = substr( $allowed_submenu['child'], $start, $length );
									} else {
										$substr = substr( $allowed_submenu['child'], $start );
									}
									
									$post_type = $substr;

								} else {
									$post_type = 'post';
								}

								if ( strpos( $allowed_submenu['child'], 'taxonomy=' ) !== false ) {
									// Get our custom taxonomy from our string.
									$start = strpos( $allowed_submenu['child'], 'taxonomy=' ) + 9;
									$end = strpos( $allowed_submenu['child'], '&', $start );
									$length = $end - $start;
									if ( $end !== false ) {
										$substr = substr( $allowed_submenu['child'], $start, $length );
									} else {
										$substr = substr( $allowed_submenu['child'], $start );
									}
									
									$taxonomy = $substr;
								}

								if ( strpos( $allowed_submenu['child'], 'edit.php' ) !== false ) {
									$allowed_cpts[ $post_type ]['edit'] = 1;
								} else if ( strpos( $allowed_submenu['child'], 'post-new.php' ) !== false ) {
									$allowed_cpts[ $post_type ]['new'] = 1;
								} else if ( strpos( $allowed_submenu['child'], 'edit-tags.php' ) !== false ) {
									$allowed_cts[ $post_type ][ $taxonomy ]['edit'] = 1;
								}

								$found = true;
							}

							if ( $allowed_submenu['parent'] == 'themes.php' && $allowed_submenu['child'] == 'themes.php' ) {
								$allowed_pages[] = 'themes';
							}
						}

						if ( $found ) {
							$allowed_pages[] = $child_slug;							
						} else {
							remove_submenu_page( htmlentities( $parent_slug ), htmlentities( $child_slug ) );
							foreach ( $submenu[ $parent_slug ] as $priority => $sub ) {
								if ( $sub[2] == $child_slug ) {
									unset( $submenu[ $parent_slug ][ $priority ] );
								}
							}
						}				
					}
				}
			}

			$allowed_cpts = apply_filters( 'slz_allowed_cpts', $allowed_cpts );			

			$allowed_cts = apply_filters( 'slz_allowed_cts', $allowed_cts );

			if ( ! isset ( $_REQUEST['post_type'] ) ) {
				if ( isset ( $_REQUEST['post'] ) ) {
					$post_type = get_post_type( $_REQUEST['post'] );
				} else {
					$post_type = 'post';
				}
			} else {
				$post_type = $_REQUEST['post_type'];
			}			

			if ( isset ( $_REQUEST['taxonomy'] ) ) {
				$taxonomy = $_REQUEST['taxonomy'];
			}

			$page_now = basename( remove_query_arg( '_wpnonce' ) );

			foreach ( $allowed_pages as $page ) {
				if ( $page == $page_now ) {
					return false;
				}
			}
			
			if ( $pagenow == 'edit.php' || $pagenow == 'post.php' ) {
				
				if ( ! isset ( $allowed_cpts[ $post_type ]['edit'] ) || $allowed_cpts[ $post_type ]['edit'] != 1 ) {
					wp_die( __( apply_filters( 'slz_block_msg', 'You do not have sufficient permissions to access this page.' ), 'slz-demo' ) );
				}

				if ( isset ( $_REQUEST['page'] ) ) {
					$screen = get_current_screen();
					$found = false;

					foreach ( $allowed_submenu_links as $allowed_submenu ) {
						if ( $_REQUEST['page'] == $allowed_submenu['child'] ) {
							$found = true;
							break;
						}
					}

					if ( ! $found )
	  					wp_die( __( apply_filters( 'slz_block_msg', 'You do not have sufficient permissions to access this page.' ), 'slz-demo' ) );
				}

			} else if ( $pagenow == 'post-new.php' ) {

				if ( ! isset ( $allowed_cpts[ $post_type ]['new'] ) || $allowed_cpts[ $post_type ]['new'] != 1 ) {
					wp_die( __( apply_filters( 'slz_block_msg', 'You do not have sufficient permissions to access this page.' ), 'slz-demo' ) );
				}

			} else if ( $pagenow == 'edit-tags.php' ) {

				if ( ! isset ( $allowed_cts[ $post_type ][ $taxonomy ]['edit'] ) || $allowed_cts[ $post_type ][ $taxonomy ]['edit'] != 1 ) {
					wp_die( __( apply_filters( 'slz_block_msg', 'You do not have sufficient permissions to access this page.' ), 'slz-demo' ) );
				}

			} else if ( $pagenow == 'admin.php' || $pagenow == 'index.php' || $pagenow == 'options-general.php' || $pagenow == 'themes.php' ) {
				$screen = get_current_screen();
				if ( $screen->id == 'dashboard' ) {
					$found = true;
				} else {
					$found = false;					
				}

				if ( 'customize' == $screen->id ) {
					foreach ( $allowed_submenu_links as $link ) {
						if ( $link['parent'] == 'themes.php' && strpos( $link['child'], 'customize.php' ) === 0 ) {
							$found = true;
						}
					}
				} else if ( 'nav-menus' == $screen->id ) {
					foreach ( $allowed_submenu_links as $link ) {
						if ( $link['parent'] == 'themes.php' && strpos( $link['child'], 'nav-menus.php' ) === 0 ) {
							$found = true;
						}
					}
				}

				foreach ( $allowed_pages as $page ) {
					$page = urlencode( $page );
					if ( preg_match( "/". $page . "$/", $screen->id ) !== 0 ) {
						$found = true;
						break;
					}
				}

				if ( ! $found )
	  				wp_die( __( apply_filters( 'slz_block_msg', 'You do not have sufficient permissions to access this page.' ), 'slz-demo' ) );
			} else {
	  			if ( ! in_array( $page_now, $allowed_pages ) && $page_now != 'wp-admin' )
	  				wp_die( __( apply_filters( 'slz_block_msg', 'You do not have sufficient permissions to access this page.' ), 'slz-demo' ) );		
			}
			
		}
	}

	public function disable_passwords() {
		if ( ! Slz_Demo()->is_admin_user() )
			return false;
		return true;
	}

	public function disable_email_editing( $user_id ) {
		$user_info = get_userdata( $user_id );

		if ( ! Slz_Demo()->is_admin_user() )
			$_POST['first_name'] = $user_info->user_firstname;
			$_POST['last_name'] = $user_info->user_lastname;
			$_POST['nickname'] = $user_info->nickname;
			$_POST['display_name'] = $user_info->display_name;
			$_POST['email'] = $user_info->user_email;
	}

	public function remove_menu_bar_items( $wp_admin_bar ) {
		if ( ! Slz_Demo()->is_admin_user() ) {
			$wp_admin_bar->remove_node( 'appearance' );
			$wp_admin_bar->remove_node( 'my-sites' );
			$wp_admin_bar->remove_node( 'new-content' );
			$wp_admin_bar->remove_node( 'comments' );
		} else {
			$elements = $wp_admin_bar->get_nodes();
			if ( is_array ( $elements ) ) {
				foreach( $elements as $element ) {

			        if ( $element->parent == 'my-sites-list' ) {
			        	$blog_id = str_replace( 'blog-', '', $element->id );
			        	if ( Slz_Demo()->is_sandbox( $blog_id ) ) {
			        		$wp_admin_bar->remove_node( $element->id );
			        	}
			        }
				}				
			}
		}
	}

	public function prevent_delete_blog( $blog_id, $drop ) {
		if ( $blog_id == 1 && ! Slz_Demo()->is_admin_user() )
			wp_die( __( apply_filters( 'slz_block_msg', 'You do not have sufficient permissions to access this page.' ), 'slz-demo' ) );
	} 
}