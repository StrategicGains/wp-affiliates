<?php
/**
 * affiliates-admin-affiliates-edit.php
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
 */

/**
 * Show edit affiliate form.
 * @param int $affiliate_id affiliate id
 */
function affiliates_admin_affiliates_edit( $affiliate_id ) {
	
	global $wpdb;
	
	if ( !current_user_can( AFFILIATES_ADMINISTER_AFFILIATES ) ) {
		wp_die( __( 'Access denied.', AFFILIATES_PLUGIN_DOMAIN ) );
	}
	
	$affiliate = affiliates_get_affiliate( intval( $affiliate_id ) );
	
	if ( empty( $affiliate ) ) {
		wp_die( __( 'No such affiliate.', AFFILIATES_PLUGIN_DOMAIN ) );
	}
	
	$affiliates_users_table = _affiliates_get_tablename( 'affiliates_users' );
	
	$affiliate_user = null;
	$affiliate_user_edit = '';
	$affiliate_user_id = $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM $affiliates_users_table WHERE affiliate_id = %d", intval( $affiliate_id ) ) );
	if ( $affiliate_user_id !== null ) {
		$affiliate_user = get_user_by( 'id', intval( $affiliate_user_id ) );
		if ( $affiliate_user ) {
			if ( current_user_can( 'edit_user',  $affiliate_user->ID ) ) {
				$affiliate_user_edit = sprintf( __( 'Edit %s', AFFILIATES_PLUGIN_DOMAIN ) , '<a target="_blank" href="' . esc_url( "user-edit.php?user_id=$affiliate_user->ID" ) . '">' . $affiliate_user->user_login . '</a>' );
			}
		}
	}
	
	$current_url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	$current_url = remove_query_arg( 'action', $current_url );
	$current_url = remove_query_arg( 'affiliate_id', $current_url );
	
	$name = isset( $_POST['name-field'] ) ? $_POST['name-field'] : $affiliate['name'];
	$email = isset( $_POST['email-field'] ) ? $_POST['email-field'] : $affiliate['email'];
	$user_login = isset( $_POST['user-field'] ) ? $_POST['user-field'] : ( $affiliate_user != null ? $affiliate_user->user_login : '' );
	$from_date = isset( $_POST['from-date-field'] ) ? $_POST['from-date-field'] : $affiliate['from_date'];
	$thru_date = isset( $_POST['thru-date-field'] ) ? $_POST['thru-date-field'] : $affiliate['thru_date'];
	
	$output =
		'<div class="manage-affiliates">' .
		'<div>' .
			'<h2>' .
				__( 'Edit an affiliate', AFFILIATES_PLUGIN_DOMAIN ) .
			'</h2>' .
		'</div>' .
	
		'<form id="edit-affiliate" action="' . $current_url . '" method="post">' .
		'<div class="affiliate edit">' .
		'<input id="affiliate-id-field" name="affiliate-id-field" type="hidden" value="' . esc_attr( intval( $affiliate_id ) ) . '"/>' .
		'<div class="field">' .
		'<label for="name-field" class="field-label first required">' .__( 'Name', AFFILIATES_PLUGIN_DOMAIN ) . '</label>' .
		'<input id="name-field" name="name-field" class="namefield" type="text" value="' . esc_attr( $name ) . '"/>' .
		'</div>' .
		'<div class="field">' .
		'<label for="email-field" class="field-label">' . __( 'Email', AFFILIATES_PLUGIN_DOMAIN ) . '</label>' .
		'<input id="email-field" name="email-field" class="emailfield" type="text" value="' . esc_attr( $email ) . '"/>' .
		'<span class="description">' .
		__( "If a valid <strong>Username</strong> is specified and no email is given, the user's email address will be used automatically.", AFFILIATES_PLUGIN_DOMAIN ) .
		'</span>' .
		'</div>' .
		'<div class="field">' .
		'<label for="user-field" class="field-label">' . __( 'Username', AFFILIATES_PLUGIN_DOMAIN ) . '</label>' .
		'<input id="user-field" name="user-field" class="userfield" type="text" value="' . esc_attr( $user_login ) . '"/>' .
		$affiliate_user_edit .
		'</div>' .
		'<div class="field">' .
		'<label for="from-date-field" class="field-label first">' . __( 'From', AFFILIATES_PLUGIN_DOMAIN ) . '</label>' .
		'<input id="from-date-field" name="from-date-field" class="datefield" type="text" value="' . esc_attr( $from_date ) . '"/>' .
		'</div>' .
		'<div class="field">' .
		'<label for="thru-date-field" class="field-label">' . __( 'Until', AFFILIATES_PLUGIN_DOMAIN ) . '</label>' .
		'<input id="thru-date-field" name="thru-date-field" class="datefield" type="text" value="' . esc_attr( $thru_date ) . '"/>' .
		'</div>' .
		'<div class="field">' .
		wp_nonce_field( 'affiliates-edit', AFFILIATES_ADMIN_AFFILIATES_NONCE, true, false ) .
		'<input class="button" type="submit" value="' . __( 'Save', AFFILIATES_PLUGIN_DOMAIN ) . '"/>' .
		'<input type="hidden" value="edit" name="action"/>' .
		'<a class="cancel" href="' . $current_url . '">' . __( 'Cancel', AFFILIATES_PLUGIN_DOMAIN ) . '</a>' .
		'</div>' .
		'</div>' . // .affiliate.edit
		'</form>' .
		'</div>'; // .manage-affiliates
	
		echo $output;

	affiliates_footer();
} // function affiliates_admin_affiliates_edit

/**
 * Handle edit form submission.
 */
function affiliates_admin_affiliates_edit_submit() {
	
	global $wpdb;
	$result = true;
	
	if ( !current_user_can( AFFILIATES_ADMINISTER_AFFILIATES ) ) {
		wp_die( __( 'Access denied.', AFFILIATES_PLUGIN_DOMAIN ) );
	}
	
	if ( !wp_verify_nonce( $_POST[AFFILIATES_ADMIN_AFFILIATES_NONCE],  'affiliates-edit' ) ) {
		wp_die( __( 'Access denied.', AFFILIATES_PLUGIN_DOMAIN ) );
	}
	
	$affiliates_table = _affiliates_get_tablename( 'affiliates' );
	$affiliates_users_table = _affiliates_get_tablename( 'affiliates_users' );
	
	$affiliate_id = isset( $_POST['affiliate-id-field'] ) ? $_POST['affiliate-id-field'] : null;
	$is_direct = false;
	$affiliate = null;
	if ( $affiliate = $wpdb->get_row( $wpdb->prepare(
		"SELECT affiliate_id FROM $affiliates_table WHERE affiliate_id = %d",
		intval( $affiliate_id ) ) ) ) {
		$is_direct = isset( $affiliate->type ) && ( $affiliate->type == AFFILIATES_DIRECT_TYPE );
	}
	
	if ( empty( $affiliate ) ) {
		wp_die( __( 'No such affiliate.', AFFILIATES_PLUGIN_DOMAIN ) );
	}
	
	$name = isset( $_POST['name-field'] ) ? $_POST['name-field'] : null;
	// don't change the name of the pseudo-affiliate
	if ( $is_direct ) {
		$name = AFFILIATES_DIRECT_NAME;
	}
	if ( !empty( $name ) ) {
		
		// Note the trickery (*) that has to be used because wpdb::prepare() is not
		// able to handle null values.
		// @see http://core.trac.wordpress.org/ticket/11622
		// @see http://core.trac.wordpress.org/ticket/12819
		
		$data = array(
			'name' => $name
		);
		$formats = array( '%s' );
		
		$email = trim( $_POST['email-field'] );
		if ( is_email( $email ) ) {
			$data['email'] = $email;
			$formats[] = '%s';
		} else {
			$data['email'] = null; // (*)
			$formats[] = 'NULL'; // (*)
		}
		
		$from_date = $_POST['from-date-field'];
		if ( empty( $from_date ) ) {
			$from_date = date( 'Y-m-d', time() );
		} else {
			$from_date = date( 'Y-m-d', strtotime( $from_date ) );
		}
		$data['from_date'] = $from_date;
		$formats[] = '%s';
		
		$thru_date = $_POST['thru-date-field'];
		if ( !empty( $thru_date ) && strtotime( $thru_date ) < strtotime( $from_date ) ) {
			// thru_date is before from_date => set to null
			$thru_date = null;							
		}
		if ( !empty( $thru_date ) ) {
			$thru_date = date( 'Y-m-d', strtotime( $thru_date ) );
			$data['thru_date'] = $thru_date;
			$formats[] = '%s';
		} else {
			$data['thru_date'] = null; // (*)
			$formats[] = 'NULL'; // (*)
		}
		
		$sets = array();
		$values = array();
		$j = 0;
		foreach( $data as $key => $value ) {
			$sets[] = $key . ' = ' . $formats[$j];
			if ( $value ) { // (*)
				$values[] = $value;
			}
			$j++;
		}

		if ( !empty( $sets ) ) {
			$sets = implode( ', ', $sets );
			$values[] = intval( $affiliate_id );
			$query = $wpdb->prepare(
				"UPDATE $affiliates_table SET $sets WHERE affiliate_id = %d",
				$values
			);
			$wpdb->query( $query );
		}

		// user association
		// delete old association if necessary
		$current_associated_user = $wpdb->get_row( $wpdb->prepare(" SELECT affiliate_id, user_id, user_login FROM $affiliates_users_table LEFT JOIN $wpdb->users ON $affiliates_users_table.user_id = $wpdb->users.ID WHERE affiliate_id = %d", intval( $affiliate_id ) ) );
		$new_associated_user_login = trim( $_POST['user-field'] );
		if ( ( empty( $new_associated_user_login ) && !empty( $current_associated_user ) ) || ( !empty( $current_associated_user ) && ( strcmp( $current_associated_user->user_login, $new_associated_user_login ) !== 0 ) ) ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM $affiliates_users_table WHERE affiliate_id = %d", intval( $affiliate_id ) ) );
		}
		// new association
		if ( !empty( $affiliate_id ) && !empty( $new_associated_user_login ) && ( empty( $current_associated_user ) || ( !empty( $current_associated_user ) && ( strcmp( $current_associated_user->user_login, $new_associated_user_login ) !== 0 ) ) ) ) {
			$new_associated_user = get_user_by( 'login', $new_associated_user_login );
			if ( !empty( $new_associated_user ) ) {
				if ( $wpdb->query( $wpdb->prepare( "INSERT INTO $affiliates_users_table SET affiliate_id = %d, user_id = %d", intval( $affiliate_id ), intval( $new_associated_user->ID ) ) ) ) {
					if ( empty( $email ) && !empty( $new_associated_user->user_email ) ) {
						$wpdb->query( $wpdb->prepare( "UPDATE $affiliates_table SET email = %s WHERE affiliate_id = %d", $new_associated_user->user_email, $affiliate_id ) );
					}
				}
			}
		}

		// hook
		if ( !empty( $affiliate_id ) ) {
			do_action( 'affiliates_updated_affiliate', intval( $affiliate_id ) );
		}
	} else {
		$result = false;
	}
	
	return $result;
	
} // function affiliates_admin_affiliates_edit_submit
?>