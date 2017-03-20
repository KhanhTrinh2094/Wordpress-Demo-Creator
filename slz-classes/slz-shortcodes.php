<?php

class Slz_Demo_Shortcodes {

	private $errors = false;

	public function __construct() {
		add_shortcode( 'slz_try_demo', array( $this, 'try_button' ) );
		add_shortcode( 'slz_demo_login', array( $this, 'login_button' ) );
		add_action( 'init', array( $this, 'create_listen' ) );
		add_action( 'init', array( $this, 'logout_listen' ) );
		add_action( 'init', array( $this, 'login_listen' ) );

		add_shortcode( 'slz_is_sandbox', array( $this, 'is_sandbox' ) );
		add_shortcode( 'slz_is_not_sandbox', array( $this, 'is_not_sandbox' ) );

		add_shortcode( 'slz_is_sandbox_expired', array( $this, 'is_sandbox_expired' ) );
	}

	public function try_button( $atts ) {

		if ( isset ( $atts['source_id'] ) ) {
			$source_id = $atts['source_id'];
		} else {
			$source_id = get_current_blog_id();
		}

		$tid = $this->set_transient( $spam_a );

		$output = '';

		if ( ! Slz_Demo()->is_sandbox() ) {

			ob_start();

			?>
			<a id="slz-demo"></a>
			<div class="slz-start-demo">
				<form action="<?php echo the_permalink(); ?>#slz-demo" method="post" enctype="multipart/form-data" class="slz-start-demo-form">
					<?php wp_nonce_field( 'slz_demo_create_sandbox','slz_demo_sandbox' ); ?>
					<input name="slz_create_sandbox" type="hidden" value="1">
					<input name="tid" type="hidden" value="<?php echo $tid; ?>">
					<input name="source_id" type="hidden" value="<?php echo $source_id; ?>">

					<?php $this->output_errors(); ?>

					<p class="submit no-border">
						<input name="submit" value="<?php _e( 'Try the demo!', 'slz-demo' ) ?>" type="submit" /><br /><br />
					</p>
				</form>
			</div>
			<?php

			$output = ob_get_clean();
		} else {
			if ( is_user_logged_in() ) {
				$logout_url = add_query_arg( array( 'slz_logout' => 1 ) );
				$output = '<a href="' . $logout_url	. '">' . __( 'Logout', 'slz-demo' ) . '</a>';
			} else {
				$login_url = add_query_arg( array( 'slz_login' => 1 ) );
				$output = '<a href="' . $login_url . '">' . __( 'Login', 'slz-demo' ) . '</a>';
			}

		}

		return $output;
	}

	function output_errors(){

		$error_output = '';

		if( FALSE != $this->errors && is_array( $this->errors ) ){
			foreach ( $this->errors as $error ) {
				$error_output .= '<div class="slz-error-message slz-'. $error['code'] . '">' . $error['message'] . '</div>';
			}
		}

		echo $error_output;

	}

	function add_error( $error_code = FALSE, $error_message = FALSE ){

		if( FALSE == $error_message || FALSE == $error_code) return;

		$this->errors[] = array(
				'code' => $error_code,
				'message' => $error_message
			);

	}

	public function login_button( $atts ) {
		if ( is_user_logged_in() ) {
			$logout_url = add_query_arg( array( 'slz_logout' => 1 ) );
			$output = '<a href="' . $logout_url	. '">' . __( 'Logout', 'slz-demo' ) . '</a>';
		} else {
			$login_url = add_query_arg( array( 'slz_login' => 1 ) );
			$output = '<a href="' . $login_url . '">' . __( 'Login', 'slz-demo' ) . '</a>';
		}

		return $output;
	}

	public function create_listen() {

		if( false == apply_filters( 'slz_create_listen', true ) ){
			return false;
		}

		if ( isset ( $_GET['errormsg'] ) ) {
			$this->add_error( ( isset( $_GET['errormsg'] ) ? $_GET['errorcode'] : 'error' ) , $_GET['errormsg'] );
			return false;
		}

		if ( Slz_Demo()->settings['prevent_clones'] == 1 )
			return false;

		if ( ! isset ( $_POST['slz_create_sandbox'] ) || $_POST['slz_create_sandbox'] != 1 )
			return false;

		if ( ! isset ( $_POST['slz_demo_sandbox'] ) )
			return false;

		if ( ! wp_verify_nonce( $_POST['slz_demo_sandbox'], 'slz_demo_create_sandbox' ) )
			return false;

		delete_transient( $_POST['tid'] );

		Slz_Demo()->sandbox->create( $_POST['source_id'] );
	}

	public function logout_listen() {
		if ( ! Slz_Demo()->is_sandbox() )
			return false;
		if ( ! isset ( $_GET['slz_logout'] ) || $_GET['slz_logout'] != 1 )
			return false;
		wp_logout();
		wp_redirect( remove_query_arg( array( 'slz_logout' ) ) );
		die();
	}

	public function login_listen() {
		if ( ! Slz_Demo()->is_sandbox() )
			return false;
		if ( ! isset ( $_GET['slz_login'] ) || $_GET['slz_login'] != 1 )
			return false;

		$user = get_option( 'slz_user' );
		$password = get_option( 'slz_password' );
		wp_signon( array( 'user_login' => $user, 'user_password' => $password ) );
		wp_redirect( remove_query_arg( array( 'slz_login' ) ) );
		die();
	}

	private function set_transient( $value ) {
		$key = Slz_Demo()->random_string();
		if ( get_transient( $key ) !== false ) {
			return $this->set_transient( $value );
		} else {
			set_transient( $key, $value, apply_filters( 'slz_question_timeout', 300 ) );
		}
		return $key;
	}

	public function reset_button() {
		if ( ! Slz_Demo()->is_sandbox() || ! Slz_Demo()->sandbox->is_active() )
			return false;

		ob_start();
		?>
		<form action="" method="post" enctype="multipart/form-data" class="slz-reset-demo-form">
			<input type="hidden" name="reset_sandbox" value="1">
			<?php wp_nonce_field( 'slz_demo_reset_sandbox','slz_demo_sandbox' ); ?>
			<input type="submit" name="reset_sandbox_submit" value="<?php _e( 'Reset Sandbox Content', 'slz-demo' ); ?>">
		</form>
		<?php
		$output = ob_get_clean();
		return $output;
	}

	public function is_sandbox( $atts, $content = null ) {
		if ( Slz_Demo()->is_sandbox() ) {
			return do_shortcode( $content );
		} else {
			return false;
		}
	}

	public function is_not_sandbox( $atts, $content = null ) {
		if ( ! Slz_Demo()->is_sandbox() ) {
			return do_shortcode( $content );
		} else {
			return false;
		}
	}

	public function is_sandbox_expired( $atts, $content = null ) {
		if ( isset ( $_REQUEST['sandbox_expired'] ) && $_REQUEST['sandbox_expired'] == 1 ) {
			return do_shortcode( $content );
		} else {
			return false;
		}
	}
}
