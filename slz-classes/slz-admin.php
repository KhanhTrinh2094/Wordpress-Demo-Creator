<?php

if ( ! defined( 'ABSPATH' ) )
	exit;

class Slz_Demo_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ), 999 );
		add_action( 'network_admin_menu', array( $this, 'add_network_menu_page' ), 999 );

		add_action( 'admin_init', array( $this, 'save_admin_page' ) );
	}

	public function add_menu_page() {
		$page = add_menu_page( __( 'Solazu Demo', 'slz-demo' ) , __( 'Solazu Demo', 'slz-demo' ), apply_filters( 'slz_admin_menu_capabilities', 'manage_network_options' ), 'slz-demo', array( $this, 'output_admin_page' ), '', '32.1337' );
		add_action( 'admin_print_styles-' . $page, array( $this, 'admin_css' ) );

		$sub_page = add_submenu_page( 'slz-demo', __( 'Solazu Demo', 'slz-demo' ) , __( 'Settings', 'slz-demo' ), apply_filters( 'slz_admin_menu_capabilities', 'manage_network_options' ), 'slz-demo' );
		add_action( 'admin_print_styles-' . $sub_page, array( $this, 'admin_css' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_js' ) );

	}

	public function add_network_menu_page() {
		$page = add_menu_page( __( 'Solazu Demo', 'slz-demo' ) , __( 'Solazu Demo', 'slz-demo' ), 'manage_network_options', 'slz-demo', array( $this, 'output_network_admin_page' ), '', '32.1337' );
		add_action( 'admin_print_styles-' . $page, array( $this, 'admin_css' ) );
	}

	public function admin_css() {
		wp_enqueue_style( 'slz-demo-admin', SLZ_PLUGIN_URL .'slz-css/slz-admin.css');
	}

	public function admin_js() {

		wp_enqueue_script( 'jquery-masonry' );
	}

	public function output_admin_page() {
		global $menu, $submenu;
		$sub_menu = Slz_Demo()->html_entity_decode_deep( $submenu );
		$tabs = apply_filters( 'slz_settings_tabs', array( 
			'general' => __( 'General', 'slz-demo' )
			) );
		
		if ( isset ( $_REQUEST['tab'] ) ) {
			$current_tab = $_REQUEST['tab'];
		} else {
			$current_tab = 'general';
		}

		?>
		<form id="slz_demo_admin" enctype="multipart/form-data" method="post" name="" action="">
			<input type="hidden" name="slz_demo_submit" value="1">
			<?php wp_nonce_field( 'slz_demo_save','slz_demo_admin_submit' ); ?>
			<div class="wrap">
				<h2 class="nav-tab-wrapper">
					<?php
					foreach ( $tabs as $slug => $nicename ) {
						if ( $slug == $current_tab ) {
							?>
							<span class="nav-tab nav-tab-active"><?php echo $nicename; ?></span>
							<?php
						} else {
							?>
							<a href="<?php echo add_query_arg( array( 'tab' => $slug ) ); ?>" class="nav-tab"><?php echo $nicename; ?></a>
							<?php
						}
					}
					?>
				</h2>
				<div id="poststuff">
					<div id="post-body">
						<div id="post-body-content">
							<?php

							if ( $current_tab == 'general' ) {
								$count = Slz_Demo()->sandbox->count( get_current_blog_id() );
								if ( $count == 1 ) {
									$count_msg = __( 'Live Sandbox', 'slz-demo' );
								} else {
									$count_msg = __( 'Live Sandboxes', 'slz-demo' );
								}
								?>

								<h2><?php _e( 'Sandbox Settings', 'slz-demo' ); ?> <span>( <?php echo $count . ' ' . $count_msg; ?> )</span> <input type="submit" class="button-secondary" id="delete_all_sandboxes" name="delete_all_sandboxes" value="<?php _e( 'Delete Sandboxes', 'slz-demo' ); ?>"></h2>
								<table class="form-table">
								<tbody>
									<tr>
										<th scope="row">
											<label for="offline"><?php _e( 'Offline Mode', 'slz-demo' ); ?></label>
										</th>
										<td>
											<fieldset>
												<input type="hidden" name="offline" value="0">
												<label><input type="checkbox" id="offline" name="offline" value="1" <?php checked( 1, Slz_Demo()->settings['offline'] ); ?>> <?php _e( 'Delete current sandboxes and take demo completely offline', 'slz-demo' ); ?></label>
											</fieldset>
										</td>
									</tr>
									<tr>
										<th scope="row">
											<label for="prevent_clones"><?php _e( 'Prevent New Sandboxes', 'slz-demo' ); ?></label>
										</th>
										<td>
											<fieldset>
												<input type="hidden" name="prevent_clones" value="0">
												<label><input type="checkbox" id="prevent_clones" name="prevent_clones" value="1" <?php checked( 1, Slz_Demo()->settings['prevent_clones'] ); ?>> <?php _e( 'Keep current sandboxes, but prevent new sandboxes from being created', 'slz-demo' ); ?></label>
											</fieldset>
										</td>
									</tr>
									<tr>
										<th scope="row">
											<label for="auto_login"><?php _e( 'Auto-Login Users As', 'slz-demo' ); ?></label>
										</th>
										<td>
											<fieldset>
												<select name="login_role">
													<?php
													$roles = get_editable_roles();
													foreach ( $roles as $slug => $role ) {
														?>
														<option value="<?php echo $slug; ?>" <?php selected( $slug, Slz_Demo()->settings['login_role'] ); ?>><?php echo $role['name']; ?></option>
														<?php
													}
													?>
												</select>
											</fieldset>
											<span class="howto"></span>
										</td>
									</tr>
								</tbody>
								</table>

								<h2><?php _e( 'Restriction Settings', 'slz-demo' ); ?></h2>
								<h3><?php _e( 'Whitelist: allow users to access these pages', 'slz-demo' ); ?></h3>
								<div class="slz-admin-restrict">
									<input type="hidden" name="slz_demo_parent_pages[]" value="">
									<input type="hidden" name="slz_demo_child_pages[]" value="">
								<?php
								foreach( $menu as $page ) {
									if ( isset ( $page[0] ) && $page[0] != '' && $page[2] != 'slz-demo' && $page[2] != 'plugins.php' ) {
										$parent_slug = $page[2];
										$class_name = str_replace( '.', '', $parent_slug );
										$parent_pages = isset ( Slz_Demo()->settings['parent_pages'] ) ? Slz_Demo()->settings['parent_pages'] : array();
										$child_pages = isset ( Slz_Demo()->settings['child_pages'] ) ? Slz_Demo()->settings['child_pages'] : array();
										?>
										<div class="slz-parent-div box">
											<h4><label><input type="checkbox" name="slz_demo_parent_pages[]" value="<?php echo $page[2];?>" class="slz-demo-parent" <?php checked( in_array( $page[2], $parent_pages ) ); if ( $parent_slug == 'index.php' ) { echo 'disabled="disabled" checked="checked"'; } ?> > <?php echo $page[0]; ?></label></h4>
										<?php
										if ( isset ( $sub_menu[ $parent_slug ] ) ) {
											?>
											<ul style="margin-left:30px;">
											<?php
											foreach( $sub_menu[ $parent_slug ] as $subpage ) {
												$found = false;
												foreach ( $child_pages as $child_page ) {
													if ( $child_page['child'] == $subpage[2] ) {
														$found = true;
														break;
													}
												}

												if ( $found !== false ) {
													$checked = 'checked="checked"';
												} else {
													$checked = '';
												}
												?>
												<li><label><input type="checkbox" name="slz_demo_child_pages[]" value="<?php echo $subpage[2]; ?>" <?php echo $checked; if ( $subpage[2] == 'index.php' ) { echo 'disabled="disabled" checked="checked"'; } ?>> <?php echo $subpage[0]; ?></label></li>
												<?php
											}
											?>
											</ul>
											<?php
										}
										?>
										</div>
										<?php
									}
								}
								?>
								</div>

								<h2><?php _e( 'Debug Settings', 'slz-demo' ); ?></h2>
								<table class="form-table">
								<tbody>
									<tr>
										<th scope="row">
											<?php _e( 'Enable Logging', 'slz-demo' ); ?>
										</th>
										<td>
											<fieldset>
												<input type="hidden" name="log" value="0">
												<label><input type="checkbox" name="log" value="1" <?php checked( 1, Slz_Demo()->settings['log'] ); ?>> <?php _e( 'Create a log file every time a sandbox is created. (Useful for debugging, but can generate lots of files.)', 'slz-demo' ); ?></label>
											</fieldset>
										</td>
									</tr>									
									<tr>
										<th scope="row">
											<?php _e( 'Table Rows Inserted At Once', 'slz-demo' ); ?>
										</th>
										<td>
											<fieldset>
												<label><input type="number" name="query_count" value="<?php echo Slz_Demo()->settings['query_count']; ?>"></label> <span class="howto"><?php _e( 'Ninja Demo will attempt to insert this many database rows at once when cloning a source. Higher numbers will result in faster sandbox creation, but lower numbers are less prone to failure. 4 is a good starting point.', 'slz-demo' ); ?></span>
											</fieldset>
										</td>
									</tr>
								</tbody>
								</table>
								
								<div>
									<input class="button-primary" name="slz_demo_settings" type="submit" value="<?php _e( 'Save', 'slz-demo' ); ?>" />
								</div>
								<?php
							} else if ( $current_tab == 'theme' ) {
								?>
								<h2><?php _e( 'Toolbar Settings', 'slz-demo' ); ?></h2>
								<table class="form-table">
								<tbody>
									<tr>
										<th scope="row">
											<?php _e( 'Show Theme Toolbar', 'slz-demo' ); ?>
										</th>
										<td>
											<fieldset>
												<input type="hidden" name="show_toolbar" value="0">
												<label><input type="checkbox" name="show_toolbar" value="1" <?php checked( 1, Slz_Demo()->settings['show_toolbar'] ); ?>> <?php _e( 'Show theme switcher toolbar on the front-end of your demo.', 'slz-demo' ); ?></label>
											</fieldset>
										</td>
									</tr>
									<tr>
										<th scope="row">
											<?php _e( 'Load this site whenever a user switches to the theme', 'slz-demo' ); ?>
										</th>
										<td>
											<fieldset>						
												<select name="theme_site">
													<option value=""><?php _e( '- None', 'slz-demo' ); ?></option>
													<?php
													
													$themes = wp_get_themes( array( 'errors' => null , 'allowed' => null ) );
													foreach( $themes as $slug => $theme ) {
														if ( ! isset ( Slz_Demo()->plugin_settings['theme_sites'][ $slug ] ) || Slz_Demo()->plugin_settings['theme_sites'][ $slug ] == get_current_blog_id() ) {
															?>
															<option value="<?php echo $slug; ?>" <?php selected( $slug, Slz_Demo()->settings['theme_site'] ); ?>><?php echo $theme->get( 'Name' ); ?></option>
															<?php										
														}
													}
													?>	
												</select>
												<span class="howto"><?php _e( 'This setting allows you to create different content, widgets, and settings for each of your theme demos. When a user switches to the selected theme, rather than a simple theme switch, this subsite will be shown.', 'slz-demo' ); ?></span>
											</fieldset>
										</td>
									</tr>
								</tbody>
								</table>
								<div>
									<input class="button-primary" name="slz_demo_settings" type="submit" value="<?php _e( 'Save', 'slz-demo' ); ?>" />
								</div>
								<?php
							}
							?>
						</div><!-- /#post-body-content -->
					</div><!-- /#post-body -->
				</div>
			</div>
		<!-- </div>/.wrap-->
		</form>
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				$( document ).on( 'click', '#delete_all_sandboxes', function( e ) {
					var answer = confirm( '<?php _e( 'Really delete all sandboxes?', 'slz-demo' ); ?>' );
					return answer;
				});
				$( document ).on( 'change', '.slz-demo-parent', function() {
					$( this ).parent().parent().parent().find( 'ul input' ).attr( 'checked', this.checked );
				});
				$('.slz-admin-restrict').masonry({
				  itemSelector: '.box',
				  columnWidth: 1,
				  gutterWidth: 5
				});
			});
		</script>
		<?php
	}

	public function output_network_admin_page() {
		?>
		<form id="slz_demo_admin" enctype="multipart/form-data" method="post" name="" action="">
			<input type="hidden" name="slz_demo_network_submit" value="1">
			<?php wp_nonce_field( 'slz_demo_save','slz_demo_admin_submit' ); ?>
			<div class="wrap">
				<h2 class="nav-tab-wrapper">
					<span class="nav-tab nav-tab-active"><?php _e( 'Settings', 'slz-demo' ); ?></span>
				</h2>
				<div id="poststuff">
					<div id="post-body">
						<div id="post-body-content">
							<?php
								$count = Slz_Demo()->sandbox->count();
								if ( $count == 1 ) {
									$count_msg = __( 'Live Sandbox', 'slz-demo' );
								} else {
									$count_msg = __( 'Live Sandboxes', 'slz-demo' );
								}
								?>

							<h2><?php _e( 'Sandbox Settings', 'slz-demo' ); ?> <span>( <?php echo $count . ' ' . $count_msg; ?> )</span> <input type="submit" class="button-secondary" id="delete_all_sandboxes" name="delete_all_sandboxes" value="<?php _e( 'Delete All Sandboxes', 'slz-demo' ); ?>"></h2>
							<table class="form-table">
							<tbody>
								<tr>
									<th scope="row">
									</th>
									<td>
										<fieldset>
											<?php
											$sites = wp_get_sites();
											foreach ( $sites as $site ) {
												if ( ! Slz_Demo()->is_sandbox( $site['blog_id'] ) ) {
													echo "<pre>";
													echo $site['path'];
													echo ": ";
													echo Slz_Demo()->sandbox->count( $site['blog_id'] );
													echo "</pre>";													
												}

											}

											?>
										</fieldset>
									</td>
								</tr>
							</tbody>
							</table>
							<div>
								<input class="button-primary" name="slz_demo_settings" type="submit" value="<?php _e( 'Save', 'slz-demo' ); ?>" />
							</div>

						</div>
					</div>
				</div>
			</div>
		</form>

		<?php
	}

	public function save_admin_page() {
		global $menu, $submenu;
		$sub_menu = Slz_Demo()->html_entity_decode_deep( $submenu );
		if ( Slz_Demo()->is_admin_user() ) {
			if ( isset ( $_POST['slz_demo_admin_submit'] ) ) {
				$nonce = $_POST['slz_demo_admin_submit'];
			} else {
				$nonce = '';
			}

			if ( isset ( $_REQUEST['tab'] ) ) {
				$current_tab = $_REQUEST['tab'];
			} else {
				$current_tab = 'general';
			}

			if ( isset ( $_POST['slz_demo_submit'] ) && $_POST['slz_demo_submit'] == 1 && wp_verify_nonce( $nonce, 'slz_demo_save' ) ) {
				if ( isset ( $_POST['slz_demo_settings'] ) ) {
					if ( $current_tab == 'general' ) {
						if ( isset ( $_POST['slz_demo_parent_pages'] ) ) {
							Slz_Demo()->settings['parent_pages'] = $_POST['slz_demo_parent_pages'];
						}

						if ( isset ( $_POST['slz_demo_child_pages'] ) ) {
							$child_pages = array();

							foreach( $_POST['slz_demo_child_pages'] as $page ) {
								$key = Slz_Demo()->recursive_array_search( $page, $sub_menu );
								$child_pages[] = array( 'parent' => $key, 'child' => $page );
							}
							Slz_Demo()->settings['child_pages'] = $child_pages;
						}

						if ( isset ( $_POST['offline'] ) && $_POST['offline'] == 1 )
							Slz_Demo()->sandbox->delete_all( get_current_blog_id() );

						Slz_Demo()->settings['offline'] = $_POST['offline'];
						Slz_Demo()->settings['prevent_clones'] = $_POST['prevent_clones'];
						Slz_Demo()->settings['log'] = $_POST['log'];
						Slz_Demo()->settings['login_role'] = $_POST['login_role'];
						Slz_Demo()->settings['query_count'] = $_POST['query_count'];
						
					} else if ( $current_tab == 'theme' ) {
						Slz_Demo()->settings['show_toolbar'] = $_POST['show_toolbar'];
						
						if ( $_POST['theme_site'] == '' ) {
							$current_theme = Slz_Demo()->settings['theme_site'];

							if ( $current_theme != '' ) {
								unset( Slz_Demo()->plugin_settings['theme_sites'][ $current_theme ] );
								Slz_Demo()->update_plugin_settings( Slz_Demo()->plugin_settings );
							}
						} else {
							Slz_Demo()->plugin_settings['theme_sites'][ $_POST['theme_site'] ] = get_current_blog_id();
							Slz_Demo()->update_plugin_settings( Slz_Demo()->plugin_settings );
						}
						Slz_Demo()->settings['theme_site'] = $_POST['theme_site'];
					}

					Slz_Demo()->update_settings( Slz_Demo()->settings );
				} else if ( isset ( $_POST['delete_all_sandboxes'] ) ) {
					Slz_Demo()->sandbox->delete_all( get_current_blog_id() );
				}
				Slz_Demo()->purge_wpengine_cache();
			} else if ( isset ( $_POST['slz_demo_network_submit'] ) && $_POST['slz_demo_network_submit'] == 1 && wp_verify_nonce( $nonce, 'slz_demo_save' ) ) {
				if ( isset ( $_POST['delete_all_sandboxes'] ) ) {
					Slz_Demo()->sandbox->delete_all();
				}
		 		Slz_Demo()->update_plugin_settings( Slz_Demo()->plugin_settings );
			}
		}
	}
}
