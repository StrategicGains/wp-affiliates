<?php
/**
 * class-affiliates-registration.php
 * 
 * Copyright (c) 2010, 2011 "kento" Karim Rahimpur www.itthinx.com
 * 
 * This code is released under the GNU General Public License.
 * See COPYRIGHT.txt and LICENSE.txt.
 * 
 * This code is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * This header and all notices must be kept intact.
 * 
 * @author Karim Rahimpur
 * @package affiliates
 * @since affiliates 1.1.0
 * 
 * Notes
 * 
 * Deleting users vs. removing affiliates :
 * 
 * - If a user is deleted, the affiliate is marked as deleted and the
 *   association is released.
 * - Marking an affiliate as deleted (pressing Remove) marks the affiliate
 *   as deleted but does not delete the user, the association is maintained.
 * 
 */
class Affiliates_Registration {
	
	private static $defaults = array(
		'is_widget'                    => false,
		'registered_profile_link_text' => null,
		'registered_profile_link_url'  => null,
		'redirect'                     => false,
		'redirect_to'                  => null,
		'submit_button_label'          => null,
		'terms_post_id'                => null
	);
	
	private static $submit_button_label = null; 
	
	/**
	 * Class initialization.
	 */
	static function init() {
		add_shortcode( 'affiliates_registration', array( 'Affiliates_Registration', 'add_shortcode' ) );
		add_action( 'wp_print_styles', array( 'Affiliates_Registration', 'print_styles' ) );
		self::$submit_button_label = __( 'Sign Up', AFFILIATES_PLUGIN_DOMAIN );
		
		// delete affiliate when user is deleted
		add_action( 'deleted_user', array( 'Affiliates_Registration', 'deleted_user' ) );
	}
	
	/**
	 * Enqueues required stylesheets.
	 */
	static function print_styles() {
		global $affiliates_version;
		wp_enqueue_style( 'affiliates', AFFILIATES_PLUGIN_URL . 'css/affiliates.css', array(), $affiliates_version );
	}
	
	/**
	 * Fields:
	 * 
	 * - first_name
	 * - last_name
	 * - user_login
	 * - email
	 * - url
	 * 
	 * first name + last name => affiliate name
	 * 
	 * Form options :
	 * - terms_post_id
	 * - redirect_to
	 * - is_widget
	 * 
	 * @param array $options form options
	 * @return string rendered registration form
	 */
	static function render_form( $options = array() ) {
		
		$output = '';
		$ext = ''; // currently not relevant		
		
		if ( $is_logged_in = is_user_logged_in() ) {
			$user       = wp_get_current_user();
			// sanitize_user_object is deprecated in WP 3.3 beta3
			//$user       = sanitize_user_object( $user );
			$first_name = $user->first_name;
			$first_name = sanitize_user_field( 'first_name', $first_name, $user->ID, 'display' );
			$last_name  = $user->last_name;
			$last_name  = sanitize_user_field( 'last_name', $last_name, $user->ID, 'display' );
			$user_login = $user->user_login;
			$user_login = sanitize_user_field( 'user_login', $user_login, $user->ID, 'display' );
			$email      = $user->user_email;
			$email      = sanitize_user_field( 'email', $email, $user->ID, 'display' );
			$url        = $user->user_url;
			$url        = sanitize_user_field( 'user_url', $url, $user->ID, 'display' );
		} else {
			$user = null;
		}
		if ( $is_affiliate = affiliates_user_is_affiliate() ) {
			$output .= '<div class="affiliates-registration registered">';
			$output .= '<p>';
			$output .= __( 'You are already registered as an affiliate.', AFFILIATES_PLUGIN_DOMAIN );
			$output .= '</p>';
			if ( isset( $options['registered_profile_link_url'] ) ) {
				$output .= '<p>';
				$output .= '<a href="' . esc_url( $options['registered_profile_link_url'] ) . '">';
				if ( isset( $options['registered_profile_link_text'] ) ) {
					$output .= wp_filter_kses( $options['registered_profile_link_text'] );
				} else {
					$output .= __( 'Access your profile', AFFILIATES_PLUGIN_DOMAIN );
				}
				$output .= '</a>';
				$output .= '</p>';
			} 
			$output .= '</div>';
			return $output;
		}
		
		if ( !get_option( 'users_can_register', false ) ) {
			$output .= '<p>' . __( 'Registration is currently closed.', AFFILIATES_PLUGIN_DOMAIN ) . '</p>';
			return $output;
		}
		
		$method = 'post';
		$action = "";
		
		$submit_name        = 'affiliates-registration-submit';
		$nonce              = 'affiliates-registration-nonce';
		$nonce_action       = 'affiliates-registration';
		$send               = false;
		
		$first_name_class   = ' class="required" ';
		$last_name_class    = ' class="required" ';
		$user_login_class   = ' class="required" ';
		$email_class        = ' class="required" ';
		$url_class          = '';

		if ( isset( $options['terms_post_id'] ) ) {
			$terms_post = get_post( $options['terms_post_id'] );
			if ( $terms_post ) {
				$terms_post_link = '<a target="_blank" href="' . esc_url( get_permalink( $terms_post->ID ) ) . '">' . get_the_title( $terms_post->ID ) . '</a>';
				$terms = sprintf( __( 'By signing up, you indicate that you have read and agree to the %s.', AFFILIATES_PLUGIN_DOMAIN ), $terms_post_link );
			}
		}
		$captcha            = '';
		
		$error = false;
		
		if ( !empty( $_POST[$submit_name] ) ) {
			
			if ( !wp_verify_nonce( $_POST[$nonce], $nonce_action ) ) {
				$error = true; // fail but don't give clues
			}
			
			$captcha = $_POST[Affiliates_Utility::get_captcha_field_id()];
			if ( !Affiliates_Utility::captcha_validates( $captcha ) ) {
				$error = true; // dumbot
			}
		
			if ( !$is_logged_in ) {
				$first_name   = isset( $_POST['first_name'] ) ? Affiliates_Utility::filter( $_POST['first_name'] ) : '';
				$last_name    = isset( $_POST['last_name'] ) ? Affiliates_Utility::filter( $_POST['last_name'] ) : '';
				$user_login   = isset( $_POST['user_login'] ) ? Affiliates_Utility::filter( $_POST['user_login'] ) : '';
				$email        = isset( $_POST['email'] ) ? Affiliates_Utility::filter( $_POST['email'] ) : '';
				$url          = isset( $_POST['url'] ) ? Affiliates_Utility::filter( $_POST['url'] ) : '';
			} else {
				$first_name   = $user->first_name;
				$last_name    = $user->last_name;
				$user_login   = $user->user_login;
				$email        = $user->user_email;
				$url          = $user->user_url;
			}
			
			if ( empty( $first_name ) ) {
				$first_name_class = ' class="required missing" ';
				$error = true;
			}
			if ( empty( $last_name ) ) {
				$last_name_class = ' class="required missing" ';
				$error = true;
			}
			if ( empty( $user_login ) ) {
				$user_login_class = ' class="required missing" ';
				$error = true;
			}
			if ( empty( $email )  || !is_email( $email ) ) {
				$email_class = ' class="required missing" ';
				$error = true;
			}
			
			if ( !$error ) {
				
				$userdata = array(
					'first_name' => $first_name,
					'last_name'  => $last_name,
					'user_login' => $user_login,
					'email'      => $email,
					'user_url'   => $url
				); 
				
				if ( !$is_logged_in ) {
					$affiliate_user_id = self::register_affiliate( $userdata );
				} else {
					$affiliate_user_id = $user->ID;
				}
					
				// register as affiliate
				if ( !is_wp_error( $affiliate_user_id ) ) {
					// add affiliate entry
					$send = true;
					
					$affiliate_id = self::store_affiliate( $affiliate_user_id, $userdata );
					
					do_action( 'affiliates_stored_affiliate', $affiliate_id, $affiliate_user_id );
					
					$is_widget = isset( $options['is_widget'] ) && ( $options['is_widget'] === true || $options['is_widget'] == 'true' );
					$redirect = isset( $options['redirect'] ) && ( $options['redirect'] === true || $options['redirect'] == 'true' );
					if ( $redirect && !$is_widget && !headers_sent() ) {
						if ( empty( $_REQUEST['redirect_to'] ) ) {
							wp_safe_redirect( get_home_url( get_current_blog_id(), 'wp-login.php?checkemail=confirm' ) );
						} else {
							wp_safe_redirect( $_REQUEST['redirect_to'] );
						}
						exit();
					} else {
						$output .= '<p>' . __( 'Thanks for signing up!', AFFILIATES_PLUGIN_DOMAIN ) . '</p>';
						if ( !$is_logged_in ) {
							$output .= '<p>' . __( 'Please check your email for the confirmation link.', AFFILIATES_PLUGIN_DOMAIN ) . '</p>';
							$output .= '<p>' . sprintf( __( 'Log in <a href="%s">here</a>.', AFFILIATES_PLUGIN_DOMAIN ), get_home_url( get_current_blog_id(), 'wp-login.php?checkemail=confirm' ) ) . '</p>';
						} else {
							if ( isset( $options['registered_profile_link_url'] ) ) {
								$output .= '<p>';
								$output .= '<a href="' . esc_url( $options['registered_profile_link_url'] ) . '">';
								if ( isset( $options['registered_profile_link_text'] ) ) {
									$output .= wp_filter_kses( $options['registered_profile_link_text'] );
								} else {
									$output .= __( 'Access your profile', AFFILIATES_PLUGIN_DOMAIN );
								}
								$output .= '</a>';
								$output .= '</p>';
							}
						}
					}
					
				} else {
					$error = true;
					$wp_error = $affiliate_user_id;
					if ( $wp_error->get_error_code() ) {
						$errors = '';
						$messages = '';
						foreach ( $wp_error->get_error_codes() as $code ) {
							switch ( $code ) {
								case 'empty_username' :
								case 'invalid_username' :
								case 'username_exists' :
									$user_login_class = ' class="required missing" ';
									break;
								case 'empty_email' :
								case 'invalid_email' :
								case 'email_exists' :
									$email_class = ' class="required missing" ';
									break;
							}
							$severity = $wp_error->get_error_data( $code );
							foreach ( $wp_error->get_error_messages( $code ) as $error ) {
								if ( 'message' == $severity ) {
									$messages .= '	' . $error . "<br />\n";
								} else {
									$errors .= '	' . $error . "<br />\n";
								}
							}
						}
						if ( !empty($errors) ) {
							echo '<div id="login_error">' . apply_filters('login_errors', $errors) . "</div>\n";
						}
						if ( !empty($messages) ) {
							echo '<p class="message">' . apply_filters('login_messages', $messages) . "</p>\n";
						}
					}
				}
			}
			
		} else {
			if ( !$is_logged_in ) {
				$first_name   = '';
				$last_name    = '';
				$user_login   = '';
				$email        = '';
				$url          = '';
			}
		}
			
		if ( !$send ) {
			$output .= '<div class="affiliates-registration" id="affiliates-registration' . $ext . '">';
			$output .= '<img id="affiliates-registration-throbber' . $ext . '" src="' . AFFILIATES_PLUGIN_URL . 'images/affiliates-throbber.gif" style="display:none" />';
			$output .= '<form id="affiliates-registration-form' . $ext . '" action="' . $action . '" method="' . $method . '">';
			$output .= '<div>';

			$field_disabled = "";
			if ( $is_logged_in ) {
				$field_disabled = ' disabled="disabled" ';
				if ( empty( $first_name ) || empty( $last_name ) ) {
					$output .= '<p>';
					$output .= sprintf( __( '<p>Please fill in the required information in your <a href="%s">profile</a> first.</p>' ), esc_url( admin_url( "profile.php" ) ) );
					$output .= '</p>';
				}
			}
			
			$output .= '<label ' . $first_name_class . ' id="affiliates-registration-form' . $ext . '-first-name-label" for="first_name">' . __( 'First Name', AFFILIATES_PLUGIN_DOMAIN ) . '</label>';
			$output .= '<input ' . $field_disabled . ' id="affiliates-registration-form' . $ext . '-first-name" name="first_name" type="text" value="' . esc_attr( $first_name ) . '"/>';
			
			$output .= '<label ' . $last_name_class . ' id="affiliates-registration-form' . $ext . '-last-name-label" for="last_name">' . __( 'Last Name', AFFILIATES_PLUGIN_DOMAIN ) . '</label>';
			$output .= '<input ' . $field_disabled . ' id="affiliates-registration-form' . $ext . '-last-name" name="last_name" type="text" value="' . esc_attr( $last_name ) . '"/>';
			
			$output .= '<label ' . $user_login_class . ' id="affiliates-registration-form' . $ext . '-user-login-label" for="user_login">' . __( 'Username', AFFILIATES_PLUGIN_DOMAIN ) . '</label>';
			$output .= '<input ' . $field_disabled . ' id="affiliates-registration-form' . $ext . '-user-login" name="user_login" type="text" value="' . esc_attr( $user_login ) . '"/>';
			
			$output .= '<label ' . $email_class . ' id="affiliates-registration-form' . $ext . '-email-label" for="email">' . __( 'Email', AFFILIATES_PLUGIN_DOMAIN ) . '</label>';
			$output .= '<input ' . $field_disabled . ' id="affiliates-registration-form' . $ext . '-email" name="email" type="text" value="' . esc_attr( $email ) . '"/>';
			
			$output .= '<label ' . $url_class . ' id="affiliates-registration-form' . $ext . '-url-label" for="url">' . __( 'Website', AFFILIATES_PLUGIN_DOMAIN ) . '</label>';
			$output .= '<input ' . $field_disabled . ' id="affiliates-registration-form' . $ext . '-url" name="url" type="text" value="' . esc_attr( $url ) . '"/>';
			
			if ( isset( $terms ) ) {
				$output .= '<p class="terms">' . $terms . '</p>';
			}
			$output .= Affiliates_Utility::captcha_get( $captcha );
			
			$output .= wp_nonce_field( $nonce_action, $nonce, true, false );
			
			if ( isset( $options['redirect_to'] ) ) {
				$output .= '<input type="hidden" name="redirect_to" value="'. esc_url( $options['redirect_to'] ) . '" />';
			}
			
			$output .= '<input type="submit" name="' . $submit_name . '" value="'. self::$submit_button_label . '" />';
			
			$output .= '</div>';
			$output .= '</form>';
			$output .= '</div>';
		}
		
		return $output;
	}

	/**
	 * Register a new affiliate user.
	 *
	 * @param string $user_login User's username for logging in
	 * @param string $user_email User's email address to send password and add
	 * @return int|WP_Error Either user's ID or error on failure.
	 */
	static function register_affiliate( $userdata ) {
		$errors = new WP_Error();
	
		$sanitized_user_login = sanitize_user( $userdata['user_login'] );
		$user_email = apply_filters( 'user_registration_email', $userdata['email'] );
	
		// Check the username
		if ( $sanitized_user_login == '' ) {
			$errors->add( 'empty_username', __( '<strong>ERROR</strong>: Please enter a username.' ) );
		} elseif ( ! validate_username( $userdata['user_login'] ) ) {
			$errors->add( 'invalid_username', __( '<strong>ERROR</strong>: This username is invalid because it uses illegal characters. Please enter a valid username.' ) );
			$sanitized_user_login = '';
		} elseif ( username_exists( $sanitized_user_login ) ) {
			$errors->add( 'username_exists', __( '<strong>ERROR</strong>: This username is already registered, please choose another one.' ) );
		}
	
		// Check the e-mail address
		if ( $user_email == '' ) {
			$errors->add( 'empty_email', __( '<strong>ERROR</strong>: Please type your e-mail address.' ) );
		} elseif ( ! is_email( $user_email ) ) {
			$errors->add( 'invalid_email', __( '<strong>ERROR</strong>: The email address isn&#8217;t correct.' ) );
			$user_email = '';
		} elseif ( email_exists( $user_email ) ) {
			$errors->add( 'email_exists', __( '<strong>ERROR</strong>: This email is already registered, please choose another one.' ) );
		}
	
		do_action( 'register_post', $sanitized_user_login, $user_email, $errors );
	
		$errors = apply_filters( 'registration_errors', $errors, $sanitized_user_login, $user_email );
	
		if ( $errors->get_error_code() ) {
			return $errors;
		}
	
		$user_pass = wp_generate_password( AFFILIATES_REGISTRATION_PASSWORD_LENGTH, false );
		
		$userdata['first_name'] = sanitize_text_field( $userdata['first_name'] );
		$userdata['last_name']  = sanitize_text_field( $userdata['last_name'] );
		$userdata['user_login'] = $sanitized_user_login;
		$userdata['email']      = $user_email;
		$userdata['password']   = $user_pass;
		$userdata['user_url']   = esc_url_raw( $userdata['user_url'] );
		$userdata['user_url']   = preg_match( '/^(https?|ftps?|mailto|news|irc|gopher|nntp|feed|telnet):/is', $userdata['user_url'] ) ? $userdata['user_url'] : 'http://' . $userdata['user_url'];
		
		// create affiliate entry
		$user_id = self::create_affiliate( $userdata );
		
		if ( ! $user_id ) {
			$errors->add( 'registerfail', sprintf( __( '<strong>ERROR</strong>: Couldn&#8217;t register you... please contact the <a href="mailto:%s">webmaster</a> !' ), get_option( 'admin_email' ) ) );
			return $errors;
		}
	
		update_user_option( $user_id, 'default_password_nag', true, true ); //Set up the Password change nag.
	
		// notify admin & new user
//		wp_new_user_notification( $user_id, $user_pass );
		self::new_affiliate_user_notification( $user_id, $user_pass );
	
		return $user_id;
	}
	
	/**
	 * Create an affiliate user.
	 * @param array $userdata
	 */
	static function create_affiliate( $userdata ) {
		$_userdata = array(
			'first_name' => esc_sql( $userdata['first_name'] ),
			'last_name' => esc_sql( $userdata['last_name'] ),
			'user_login' => esc_sql( $userdata['user_login'] ),
			'user_email' => esc_sql( $userdata['email'] ),
			'user_pass' => esc_sql( $userdata['password'] )
		);
		if ( isset( $userdata['user_url'] ) ) {
			$_userdata['user_url'] = esc_sql( $userdata['user_url'] );
		}
		return wp_insert_user( $_userdata );
	}
	
	/**
	 * Creates an affiliate entry and relates it to a user.
	 * Notifies site admin of affiliate registration.
	 * 
	 * @param int $user_id user id
	 * @param array $userdata affiliate data
	 * @return if successful new affiliate's id, otherwise false
	 */
	static function store_affiliate( $user_id, $userdata ) {
		global $wpdb;
		
		$result = false;
		
		$affiliates_table = _affiliates_get_tablename( 'affiliates' );
		$today = date( 'Y-m-d', time() );
		$name = $userdata['first_name'] . " " . $userdata['last_name'];
		$email = $userdata['email'];
		$data = array(
			'name' => esc_sql( $name ),
			'email' => esc_sql( $email ),
			'from_date' => esc_sql( $today ),
		);
		$formats = array( '%s', '%s', '%s' );
		if ( $wpdb->insert( $affiliates_table, $data, $formats ) ) {			
			$affiliate_id = $wpdb->get_var( $wpdb->prepare( "SELECT LAST_INSERT_ID()" ) );
			// create association
			if ( $wpdb->insert(
				_affiliates_get_tablename( 'affiliates_users' ),
				array(
					'affiliate_id' => $affiliate_id,
					'user_id' => $user_id
				),
				array( '%d', '%d' )
			) ) {
				$result = $affiliate_id;
				self::new_affiliate_notification( $user_id );
			}
			
			// hook
			if ( !empty( $affiliate_id ) ) {
				do_action( 'affiliates_added_affiliate', intval( $affiliate_id ) );
			}
		}
		return $result;
	}
	
	/**
	 * Hooked on delete_user to mark affiliate as deleted.
	 * Note that the affiliate-user association is maintained.
	 * @param int $user_id
	 */
	static function deleted_user( $user_id ) {
		
		global $wpdb;
		
		$affiliates_table = _affiliates_get_tablename( 'affiliates' );
		$affiliates_users_table = _affiliates_get_tablename( 'affiliates_users' );
		
		if ( $affiliate_user = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $affiliates_users_table WHERE user_id = %d",
				intval( $user_id ) ) ) ) {
			$affiliate_id = $affiliate_user->affiliate_id;
		
			// do not mark the pseudo-affiliate as deleted: type != ...
			$check = $wpdb->prepare(
				"SELECT affiliate_id FROM $affiliates_table WHERE affiliate_id = %d AND (type IS NULL OR type != '" . AFFILIATES_DIRECT_TYPE . "')",
				intval( $affiliate_id ) );
			if ( $wpdb->query( $check ) ) {
				$valid_affiliate = true;
			}
			
			if ( $valid_affiliate ) {
				// mark the affiliate as deleted - will go through and also
				// clean up the association even if the affiliate was already
				// marked as deleted
				$wpdb->query(
					$query = $wpdb->prepare(
						"UPDATE $affiliates_table SET status = 'deleted' WHERE affiliate_id = %d",
						intval( $affiliate_id )
					)
				);
				do_action( 'affiliates_deleted_affiliate', intval( $affiliate_id ) );
				// the user is removed from the users table, it wouldn't make sense to maintain
				// a dangling reference to a non-existent user so release the association as well 
				$wpdb->query(
					$query = $wpdb->prepare(
						"DELETE FROM $affiliates_users_table WHERE affiliate_id = %d AND user_id = %d",
						intval( $affiliate_id ), intval( $user_id )
					)
				);
			}
		}
		
	}
	
	/**
	 * Registration form shortcode handler.
	 * 
	 * @param array $atts attributes
	 * @param string $content not used
	 */
	static function add_shortcode( $atts, $content = null ) {
		$options = shortcode_atts( self::$defaults, $atts );
		return self::render_form( $options );
	}

	/**
	 * Notify the blog admin of a new affiliate.
	 *
	 * @param int $user_id User ID
	 */
	static function new_affiliate_notification( $user_id ) {
		$user = new WP_User( $user_id );
	
		$user_login = stripslashes( $user->user_login );
		$user_email = stripslashes( $user->user_email );
	
		// The blogname option is escaped with esc_html on the way into the database in sanitize_option
		// we want to reverse this for the plain text arena of emails.
		$blogname = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
	
		$message  = sprintf(__( 'New affiliate registration on your site %s:' ), $blogname ) . "\r\n\r\n";
		$message .= sprintf(__( 'Username: %s' ), $user_login ) . "\r\n\r\n";
		$message .= sprintf(__( 'E-mail: %s' ), $user_email ) . "\r\n";
	
		@wp_mail( get_option( 'admin_email' ), sprintf( __( '[%s] New Affiliate Registration' ), $blogname ), $message );
	}

	/**
	 * Notify the new affiliate of their registration details.
	 *
	 * @param int $user_id User ID
	 */
	static function new_affiliate_user_notification( $user_id, $user_pass ) {
		$user = new WP_User( $user_id );
	
		$user_login = stripslashes( $user->user_login );
		$user_email = stripslashes( $user->user_email );
		$login_url = "http://www.unstoppableyouintensive.com/affiliates";
	
		// The blogname option is escaped with esc_html on the way into the database in sanitize_option
		// we want to reverse this for the plain text arena of emails.
		$blogname = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
	
		$message = sprintf(__( 'Thank you for becoming an affiliate on %s!' ), $blogname ) . "\r\n\r\n";
		$message .= "Here are your login details for your new affiliate registration.\r\n\r\n";
		$message .= sprintf(__( 'Username: %s' ), $user_login ) . "\r\n";
		$message .= sprintf(__( 'Password: %s' ), $user_pass ) . "\r\n\r\n";
		$message .= sprintf(__( 'Login here: %s' ), $login_url ) . "\r\n";
	
		@wp_mail( $user_email, sprintf( __( '[%s] New Affiliate Registration' ), $blogname ), $message );
	}
}

Affiliates_Registration::init();