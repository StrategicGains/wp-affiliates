<?php
/**
 * affiliates-admin-options.php
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
 * @since affiliates 1.0.0
 */

/**
 * @var string options form nonce name
 */
define( 'AFFILIATES_ADMIN_OPTIONS_NONCE', 'affiliates-admin-nonce' );

/** 
 * @var string Default set of robots. For now ... do-it-yourself.
 * @unused
 */
define( 'AFFILIATES_DEFAULT_ROBOTS', '' );

function affiliates_admin_options() {
	
	global $wpdb, $affiliates_options, $wp_roles;
	
	if ( !current_user_can( AFFILIATES_ADMINISTER_OPTIONS ) ) {
		wp_die( __( 'Access denied.', AFFILIATES_PLUGIN_DOMAIN ) );
	}
	
	$robots_table = _affiliates_get_tablename( 'robots' );
	
	echo
		'<div>' .
			'<h2>' .
				__( 'Affiliates options', AFFILIATES_PLUGIN_DOMAIN ) .
			'</h2>' .
		'</div>';
	
	$pages_generated_info = '';

	//
	// handle options form submission
	// 
	if ( isset( $_POST['timeout'] ) ) {
		
		if ( wp_verify_nonce( $_POST[AFFILIATES_ADMIN_OPTIONS_NONCE], 'admin' ) ) {
			
			// page generation
			error_log( var_export( $_POST, true ) );
			if ( isset( $_POST['generate'] ) ) {
				require_once( AFFILIATES_CORE_LIB . '/class-affiliates-generator.php' );
				$post_ids = Affiliates_Generator::setup_pages();
				foreach ( $post_ids as $post_id ) {
					$link = '<a href="' . get_permalink( $post_id ) . '" target="_blank">' . get_the_title( $post_id ) . '</a>';
					$pages_generated_info .= '<div class="info">' . __( sprintf( 'The %s page has been created.', $link ), AFFILIATES_PLUGIN_DOMAIN ) . '</div>';
				}
			}
			
			// timeout
			$timeout = intval ( $_POST['timeout'] );
			if ( $timeout < 0 ) {
				$timeout = 0;
			}
			update_option( 'aff_cookie_timeout_days', $timeout );
			
			// robots
			$robots = wp_filter_nohtml_kses( trim ( $_POST['robots'] ) );
			$wpdb->query("DELETE FROM $robots_table;");
			if ( !empty( $robots ) ) {
				$robots = str_replace( ",", "\n", $robots );
				$robots = str_replace( "\r", "", $robots );
				$robots = explode( "\n", $robots );
				foreach ( $robots as $robot ) {
					$robot = trim( $robot );
					if (!empty($robot)) {
						$query = $wpdb->prepare( "INSERT INTO $robots_table (name) VALUES (%s);", $robot ); 
						$wpdb->query( $query );
					}
				}
			}
			
			$encoding_id = $_POST['id_encoding'];
			if ( key_exists( $encoding_id, affiliates_get_id_encodings() ) ) {
				// important: must use normal update_option/get_option otherwise we'd have a per-user encoding
				update_option( 'aff_id_encoding', $encoding_id );
			}
			
			$rolenames = $wp_roles->get_names();
			$caps = array(
				AFFILIATES_ACCESS_AFFILIATES => __( 'Access affiliates', AFFILIATES_PLUGIN_DOMAIN ),
				AFFILIATES_ADMINISTER_AFFILIATES => __( 'Administer affiliates', AFFILIATES_PLUGIN_DOMAIN ),
				AFFILIATES_ADMINISTER_OPTIONS => __( 'Administer options', AFFILIATES_PLUGIN_DOMAIN ),
			);
			foreach ( $rolenames as $rolekey => $rolename ) {
				$role = $wp_roles->get_role( $rolekey );
				foreach ( $caps as $capkey => $capname ) {
					$role_cap_id = $rolekey.'-'.$capkey;
					if ( !empty($_POST[$role_cap_id] ) ) {
						$role->add_cap( $capkey );
					} else {
						$role->remove_cap( $capkey );
					}
				}
			}
			// prevent locking out
			_affiliates_assure_capabilities();
							
			if ( !empty( $_POST['delete-data'] ) ) {
				update_option( 'aff_delete_data', true );
			} else {
				update_option( 'aff_delete_data', false );
			}
			
			if ( !empty( $_POST['use-direct'] ) ) {
				update_option( 'aff_use_direct', true );
			} else {
				update_option( 'aff_use_direct', false );
			}
			
			if ( !empty( $_POST['status'] ) && ( Affiliates_Utility::verify_referral_status_transition( $_POST['status'], $_POST['status'] ) ) ) {
				update_option( 'aff_default_referral_status', $_POST['status'] );
			} else {
				update_option( 'aff_default_referral_status', AFFILIATES_REFERRAL_STATUS_ACCEPTED );
			}
		}
	}
	
	$use_direct = get_option( 'aff_use_direct', true );
	
	$timeout = get_option( 'aff_cookie_timeout_days', AFFILIATES_COOKIE_TIMEOUT_DAYS );
	
	$default_status = get_option( 'aff_default_referral_status', AFFILIATES_REFERRAL_STATUS_ACCEPTED );
	$status_descriptions = array(
		AFFILIATES_REFERRAL_STATUS_ACCEPTED => __( 'Accepted', AFFILIATES_PLUGIN_DOMAIN ),
		AFFILIATES_REFERRAL_STATUS_CLOSED   => __( 'Closed', AFFILIATES_PLUGIN_DOMAIN ),
		AFFILIATES_REFERRAL_STATUS_PENDING  => __( 'Pending', AFFILIATES_PLUGIN_DOMAIN ),
		AFFILIATES_REFERRAL_STATUS_REJECTED => __( 'Rejected', AFFILIATES_PLUGIN_DOMAIN ),
	);
	$status_select = "<select name='status'>";
	foreach ( $status_descriptions as $status_key => $status_value ) {
		if ( $status_key == $default_status ) {
			$selected = "selected='selected'";
		} else {
			$selected = "";
		}
		$status_select .= "<option value='$status_key' $selected>$status_value</option>";
	}
	$status_select .= "</select>";

	$robots = '';
	$db_robots = $wpdb->get_results( $wpdb->prepare( "SELECT name FROM $robots_table" ), OBJECT );
	foreach ($db_robots as $db_robot ) {
		$robots .= $db_robot->name . "\n";
	}
	
	$id_encoding = get_option( 'aff_id_encoding', AFFILIATES_NO_ID_ENCODING );
	$id_encoding_select = '';
	$encodings = affiliates_get_id_encodings();
	if ( !empty( $encodings ) ) {
		$id_encoding_select .= '<label class="id-encoding" for="id_encoding">' . __('Affiliate ID Encoding', AFFILIATES_PLUGIN_DOMAIN ) . '</label>';
		$id_encoding_select .= '<select class="id-encoding" name="id_encoding">';
		foreach ( $encodings as $key => $value ) {
			if ( $id_encoding == $key ) {
				$selected = ' selected="selected" ';
			} else {
				$selected = '';
			}
			$id_encoding_select .= '<option ' . $selected . ' value="' . esc_attr( $key ) . '">' . esc_attr( $value ) . '</option>';
		}
		$id_encoding_select .= '</select>';
	}
	
	$rolenames = $wp_roles->get_names();
	$caps = array(
		AFFILIATES_ACCESS_AFFILIATES => __( 'Access affiliates', AFFILIATES_PLUGIN_DOMAIN ),
		AFFILIATES_ADMINISTER_AFFILIATES => __( 'Administer affiliates', AFFILIATES_PLUGIN_DOMAIN ),
		AFFILIATES_ADMINISTER_OPTIONS => __( 'Administer options', AFFILIATES_PLUGIN_DOMAIN ),
	);
	$caps_table = '<table class="affiliates-permissions">';
	$caps_table .= '<thead>';
	$caps_table .= '<tr>';
	$caps_table .= '<td class="role">';
	$caps_table .= __( 'Role', AFFILIATES_PLUGIN_DOMAIN );
	$caps_table .= '</td>';
	foreach ( $caps as $cap ) {
		$caps_table .= '<td class="cap">';
		$caps_table .= $cap;
		$caps_table .= '</td>';				
	}
	
	$caps_table .= '</tr>';
	$caps_table .= '</thead>';
	$caps_table .= '<tbody>';
	foreach ( $rolenames as $rolekey => $rolename ) {
		$role = $wp_roles->get_role( $rolekey );
		$caps_table .= '<tr>';
		$caps_table .= '<td>';
		$caps_table .= translate_user_role( $rolename );
		$caps_table .= '</td>';
		foreach ( $caps as $capkey => $capname ) {
						
			if ( $role->has_cap( $capkey ) ) {
				$checked = ' checked="checked" ';
			} else {
				$checked = '';
			}
			 
			$caps_table .= '<td class="checkbox">';
			$role_cap_id = $rolekey.'-'.$capkey;
			$caps_table .= '<input type="checkbox" name="' . $role_cap_id . '" id="' . $role_cap_id . '" ' . $checked . '/>';
			$caps_table .= '</td>';
		}
		$caps_table .= '</tr>';
	}
	$caps_table .= '</tbody>';
	$caps_table .= '</table>';
	
	$delete_data = get_option( 'aff_delete_data', false );
	
	//
	// print the options form
	//
	echo
		'<form action="" name="options" method="post">' .		
			'<div>' .
				'<h3>' . __( 'Page generation', AFFILIATES_PLUGIN_DOMAIN ) . '</h3>' .
				'<p>' .
					__( 'Press the button to generate an affiliate area.', AFFILIATES_PLUGIN_DOMAIN ) .
					'<input class="generate" name="generate" type="submit" value="' . __( 'Generate', AFFILIATES_PLUGIN_DOMAIN ) .'" />' .
				'</p>' .
				$pages_generated_info .
				'<h3>' . __( 'Referral timeout') . '</h3>' .
				'<p>' .
					'<input class="timeout" name="timeout" type="text" value="' . esc_attr( intval( $timeout ) ) . '" />' .
					'<label for="timeout">' . __( 'Days', AFFILIATES_PLUGIN_DOMAIN ) . '</label>' .
				'</p>' .
				'<p class="description">' .
					__( 'This is the number of days since a visitor accessed your site via an affiliate link, for which a suggested referral will be valid.', AFFILIATES_PLUGIN_DOMAIN ) .
				'</p>' .
				'<p>' .
					__( 'If you enter 0, referrals will only be valid until the visitor closes the browser (session).', AFFILIATES_PLUGIN_DOMAIN ) .
				'</p>' .
				'<p>' .
					__( 'The default value is 1. In this case, if a visitor comes to your site via an affiliate link, a suggested referral will be valid until one day after she or he clicked that affiliate link.', AFFILIATES_PLUGIN_DOMAIN ) .
				'</p>' .
					
				'<h3>' . __( 'Direct referrals', AFFILIATES_PLUGIN_DOMAIN ) . '</h3>' .
				'<p>' .
					'<input name="use-direct" type="checkbox" ' . ( $use_direct ? 'checked="checked"' : '' ) . '/>' .
					'<label for="use-direct">' . __( 'Store direct referrals', AFFILIATES_PLUGIN_DOMAIN ) . '</label>' .
				'</p>' .
				'<p class="description">' .
					__( 'If this option is enabled, whenever a referral is suggested and no affiliate is attributable to it, the referral will be attributed to Direct.', AFFILIATES_PLUGIN_DOMAIN ) .
				'</p>' .
					
				'<h3>' . __( 'Default referral status', AFFILIATES_PLUGIN_DOMAIN ) . '</h3>' .
				'<p>' .
					$status_select .
				'</p>' .
					
				'<h3>' . __( 'Robots') . '</h3>' .
				'<p>' .
					//'<label for="robots">' . __( 'Robots', AFFILIATES_PLUGIN_DOMAIN ) . '</label>' .
					'<textarea id="robots" name="robots" rows="10" cols="45">' . wp_filter_nohtml_kses( $robots ) . '</textarea>' .
				'</p>' .
				'<p>' .
					__( 'Hits on affiliate links from these robots will be marked or not recorded. Put one entry on each line.', AFFILIATES_PLUGIN_DOMAIN ) .
				'</p>' .
					
				'<h3>' . __( 'Affiliate ID encoding', AFFILIATES_PLUGIN_DOMAIN ) . '</h3>' .
				'<p>' .
					$id_encoding_select .
				'</p>' .
				'<p>' .
					sprintf( __( 'The current encoding in effect is: <b>%s</b>', AFFILIATES_PLUGIN_DOMAIN ), $encodings[$id_encoding] ) .
				'</p>' .
				'<p class="description warning">' .
					__( 'CAUTION: If you change this setting and have distributed affiliate links or permalinks, make sure that these are updated. Unless the incoming affiliate links reflect the current encoding, no affiliate hits, visits or referrals will be recorded.', AFFILIATES_PLUGIN_DOMAIN ) .
				'</p>' .
					
				'<h3>' . __( 'Permissions', AFFILIATES_PLUGIN_DOMAIN ) . '</h3>' .
					
				$caps_table .
				
				'<p class="description">' .
				__( 'A minimum set of permissions will be preserved.', AFFILIATES_PLUGIN_DOMAIN ) .
				'<br/>' .
				__( 'If you lock yourself out, please ask an administrator to help.', AFFILIATES_PLUGIN_DOMAIN ) .
				'</p>' . 
					
				'<h3>' . __( 'Deactivation and data persistence', AFFILIATES_PLUGIN_DOMAIN ) . '</h3>' .
				'<p>' .
					'<input name="delete-data" type="checkbox" ' . ( $delete_data ? 'checked="checked"' : '' ) . '/>' .
					'<label for="delete-data">' . __( 'Delete all plugin data on deactivation', AFFILIATES_PLUGIN_DOMAIN ) . '</label>' .
				'</p>' .
				'<p class="description warning">' .
						__( 'CAUTION: If this option is active while the plugin is deactivated, ALL affiliate and referral data will be DELETED. If you want to retrieve data about your affiliates and their referrals and are going to deactivate the plugin, make sure to back up your data or do not enable this option. By enabling this option you agree to be solely responsible for any loss of data or any other consequences thereof.', AFFILIATES_PLUGIN_DOMAIN ) .
				'</p>' .
				'<p>' .
					wp_nonce_field( 'admin', AFFILIATES_ADMIN_OPTIONS_NONCE, true, false ) .
					'<input type="submit" name="submit" value="' . __( 'Save', AFFILIATES_PLUGIN_DOMAIN ) . '"/>' .
				'</p>' .
			'</div>' .
		'</form>';
	affiliates_footer();
}
?>