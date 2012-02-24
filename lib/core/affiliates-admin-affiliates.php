<?php
/**
 * affiliates-admin-affiliates.php
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
	
// Shows hits by affiliate

define( 'AFFILIATES_AFFILIATES_PER_PAGE', 10 );

define( 'AFFILIATES_ADMIN_AFFILIATES_NONCE_1', 'affiliates-nonce-1');
define( 'AFFILIATES_ADMIN_AFFILIATES_NONCE_2', 'affiliates-nonce-2');
define( 'AFFILIATES_ADMIN_AFFILIATES_FILTER_NONCE', 'affiliates-filter-nonce' );

require_once( AFFILIATES_CORE_LIB . '/class-affiliates-date-helper.php');
require_once( AFFILIATES_CORE_LIB . '/affiliates-admin-affiliates-add.php');
require_once( AFFILIATES_CORE_LIB . '/affiliates-admin-affiliates-edit.php');
require_once( AFFILIATES_CORE_LIB . '/affiliates-admin-affiliates-remove.php');

/**
 * Affiliate table and action handling.
 */
function affiliates_admin_affiliates() {
	
	global $wpdb, $wp_rewrite, $affiliates_options;
	
	$output = '';
	$today = date( 'Y-m-d', time() );
	
	if ( !current_user_can( AFFILIATES_ADMINISTER_AFFILIATES ) ) {
		wp_die( __( 'Access denied.', AFFILIATES_PLUGIN_DOMAIN ) );
	}
				
	if ( !$wp_rewrite->using_permalinks() ) {			
		$output .= '<p class="warning">' .
			sprintf( __( 'Your site is not using pretty <a href="%s">permalinks</a>. You will only be able to use URL parameter based <span class="affiliate-link">affiliate links</span> but not pretty <span class="affiliate-permalink">affiliate permalinks</span>, unless you change your permalink settings.', AFFILIATES_PLUGIN_DOMAIN ), get_admin_url( null, 'options-permalink.php') ) .
			'</p>';
	}
	
	//
	// handle actions
	//
	if ( isset( $_POST['action'] ) ) {
		//  handle action submit - do it
		switch( $_POST['action'] ) {
			case 'add' :
				if ( !affiliates_admin_affiliates_add_submit() ) {
					return affiliates_admin_affiliates_add();
				}
				break;
			case 'edit' :
				if ( !affiliates_admin_affiliates_edit_submit() ) {
					return affiliates_admin_affiliates_edit( $_POST['affiliate-id-field'] );
				}
				break;
			case 'remove' :
				affiliates_admin_affiliates_remove_submit();
				break;
		}
	} else if ( isset ( $_GET['action'] ) ) {
		// handle action request - show form
		switch( $_GET['action'] ) {
			case 'add' :
				return affiliates_admin_affiliates_add();
				break;
			case 'edit' :
				if ( isset( $_GET['affiliate_id'] ) ) {
					return affiliates_admin_affiliates_edit( $_GET['affiliate_id'] );
				}
				break;
			case 'remove' :
				if ( isset( $_GET['affiliate_id'] ) ) {
					return affiliates_admin_affiliates_remove( $_GET['affiliate_id'] );
				}
				break;
		}
	}
	
	//
	// affiliate table
	//
	if (
		isset( $_POST['from_date'] ) ||
		isset( $_POST['thru_date'] ) ||
		isset( $_POST['clear_filters'] ) ||
		isset( $_POST['affiliate_id'] ) ||
		isset( $_POST['affiliate_name'] ) ||
		isset( $_POST['affiliate_user_login'] ) ||
		isset( $_POST['show_deleted'] ) ||
		isset( $_POST['show_inoperative'] )
	) {
		if ( !wp_verify_nonce( $_POST[AFFILIATES_ADMIN_AFFILIATES_FILTER_NONCE], 'admin' ) ) {
			wp_die( __( 'Access denied.', AFFILIATES_PLUGIN_DOMAIN ) );
		}
	}
	
	// filters
	$from_date            = $affiliates_options->get_option( 'affiliates_from_date', null );
	$from_datetime = null;
	$thru_date            = $affiliates_options->get_option( 'affiliates_thru_date', null );
	$thru_datetime = null;
	$affiliate_id         = $affiliates_options->get_option( 'affiliates_affiliate_id', null );
	$affiliate_name       = $affiliates_options->get_option( 'affiliates_affiliate_name', null );
	$affiliate_user_login = $affiliates_options->get_option( 'affiliates_affiliate_user_login', null );
	$show_deleted         = $affiliates_options->get_option( 'affiliates_show_deleted', false );
	$show_inoperative     = $affiliates_options->get_option( 'affiliates_show_inoperative', false );
	
	if ( isset( $_POST['clear_filters'] ) ) {
		$affiliates_options->delete_option( 'affiliates_from_date' );
		$affiliates_options->delete_option( 'affiliates_thru_date' );
		$affiliates_options->delete_option( 'affiliates_affiliate_id' );
		$affiliates_options->delete_option( 'affiliates_affiliate_name' );
		$affiliates_options->delete_option( 'affiliates_affiliate_user_login' );
		$affiliates_options->delete_option( 'affiliates_show_deleted' );
		$affiliates_options->delete_option( 'affiliates_show_inoperative' );
		$from_date = null;
		$from_datetime = null;
		$thru_date = null;
		$thru_datetime = null;
		$affiliate_id = null;
		$affiliate_name = null;
		$affiliate_user_login = null;
		$show_deleted = false;
		$show_inoperative = false;
	} else if ( isset( $_POST['submitted'] ) ) {
		if ( !empty( $_POST['affiliate_name'] ) ) {
			$affiliate_name = $_POST['affiliate_name'];
			$affiliates_options->update_option( 'affiliates_affiliate_name', $affiliate_name );
		}
		if ( !empty( $_POST['affiliate_user_login'] ) ) {
			$affiliate_user_login = $_POST['affiliate_user_login'];
			$affiliates_options->update_option( 'affiliates_affiliate_user_login', $affiliate_user_login );
		}
		$show_deleted = isset( $_POST['show_deleted'] );
		$affiliates_options->update_option( 'affiliates_show_deleted', $show_deleted );
		$show_inoperative = isset( $_POST['show_inoperative'] );
		$affiliates_options->update_option( 'affiliates_show_inoperative', $show_inoperative );
		// filter by date(s)
		if ( !empty( $_POST['from_date'] ) ) {
			$from_date = date( 'Y-m-d', strtotime( $_POST['from_date'] ) );
			$affiliates_options->update_option( 'affiliates_from_date', $from_date );
		} else {
			$affiliates_options->delete_option( 'affiliates_from_date' );
		}
		if ( !empty( $_POST['thru_date'] ) ) {
			$thru_date = date( 'Y-m-d', strtotime( $_POST['thru_date'] ) );
			$affiliates_options->update_option( 'affiliates_thru_date', $thru_date );
		} else {
			$affiliates_options->delete_option( 'affiliates_thru_date' );
		}
		if ( $from_date && $thru_date ) {
			if ( strtotime( $from_date ) > strtotime( $thru_date ) ) {
				$thru_date = null;
				$affiliates_options->delete_option( 'affiliates_thru_date' );
			}
		}
		// We now have the desired dates from the user's point of view, i.e. in her timezone.
		// If supported, adjust the dates for the site's timezone:
		if ( $from_date ) {
			$from_datetime = DateHelper::u2s( $from_date );
		}
		if ( $thru_date ) {
			$thru_datetime = DateHelper::u2s( $thru_date, 24*3600 );
		}
		// filter by affiliate id
		if ( !empty( $_POST['affiliate_id'] ) ) {
			$affiliate_id = affiliates_check_affiliate_id( $_POST['affiliate_id'] );
			if ( $affiliate_id ) {
				$affiliates_options->update_option( 'affiliates_affiliate_id', $affiliate_id );
			}
		} else if ( isset( $_POST['affiliate_id'] ) ) { // empty && isset => '' => all
			$affiliate_id = null;
			$affiliates_options->delete_option( 'affiliates_affiliate_id' );	
		}
	}
	
	if ( isset( $_POST['row_count'] ) ) {
		if ( !wp_verify_nonce( $_POST[AFFILIATES_ADMIN_AFFILIATES_NONCE_1], 'admin' ) ) {
			wp_die( __( 'Access denied.', AFFILIATES_PLUGIN_DOMAIN ) );
		}
	}
	
	if ( isset( $_POST['paged'] ) ) {
		if ( !wp_verify_nonce( $_POST[AFFILIATES_ADMIN_AFFILIATES_NONCE_2], 'admin' ) ) {
			wp_die( __( 'Access denied.', AFFILIATES_PLUGIN_DOMAIN ) );
		}
	}
	
	$current_url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	$current_url = remove_query_arg( 'paged', $current_url );
	$current_url = remove_query_arg( 'action', $current_url );
	$current_url = remove_query_arg( 'affiliate_id', $current_url );
	
	$affiliates_table = _affiliates_get_tablename( 'affiliates' );
	$affiliates_users_table = _affiliates_get_tablename( 'affiliates_users' );
	
	$output .=
		'<div class="manage-affiliates">' .
		'<div>' .
			'<h2>' .
				__( 'Manage Affiliates', AFFILIATES_PLUGIN_DOMAIN ) .
			'</h2>' .
		'</div>';
				
	$output .=
		'<div class="manage">' .
			"<a title='" . __( 'Click to add a new affiliate', AFFILIATES_PLUGIN_DOMAIN ) . "' class='add' href='" . esc_url( $current_url ) . "&action=add'><img class='icon' alt='" . __( 'Add', AFFILIATES_PLUGIN_DOMAIN) . "' src='". AFFILIATES_PLUGIN_URL ."images/add.png'/><span class='label'>" . __( 'New Affiliate', AFFILIATES_PLUGIN_DOMAIN) . "</span></a>" .
		'</div>';

	$row_count = isset( $_POST['row_count'] ) ? intval( $_POST['row_count'] ) : 0;
	
	if ($row_count <= 0) {
		$row_count = $affiliates_options->get_option( 'affiliates_per_page', AFFILIATES_AFFILIATES_PER_PAGE );
	} else {
		$affiliates_options->update_option('affiliates_per_page', $row_count );
	}
	$offset = isset( $_GET['offset'] ) ? intval( $_GET['offset'] ) : 0;
	if ( $offset < 0 ) {
		$offset = 0;
	}
	$paged = isset( $_GET['paged'] ) ? intval( $_GET['paged'] ) : 0;
	if ( $paged < 0 ) {
		$paged = 0;
	} 
	
	$orderby = isset( $_GET['orderby'] ) ? $_GET['orderby'] : null;
	switch ( $orderby ) {
		case 'from_date' :
		case 'thru_date' :
		case 'email' :
		case 'affiliate_id' :
		case 'name' :
		case 'user_login' :
			break;
		default:
			$orderby = 'name';
	}
	
	$order = isset( $_GET['order'] ) ? $_GET['order'] : null;
	switch ( $order ) {
		case 'asc' :
		case 'ASC' :
			$switch_order = 'DESC';
			break;
		case 'desc' :
		case 'DESC' :
			$switch_order = 'ASC';
			break;
		default:
			$order = 'ASC';
			$switch_order = 'DESC';
	}
	
//	if ( $from_date || $thru_date || $affiliate_id ) {
//		$filters = " WHERE ";
//	} else {
//		$filters = '';			
//	}
	$filters = array();
	$filter_params = array();
	if ( $affiliate_id ) {
		$filters[] = " $affiliates_table.affiliate_id = %d ";
		$filter_params[] = $affiliate_id;
	}
	if ( $affiliate_name ) {
		$filters[] = " $affiliates_table.name LIKE '%%%s%%' ";
		$filter_params[] = $affiliate_name;
	}
	if ( $affiliate_user_login ) {
		$filters[] = " $wpdb->users.user_login LIKE '%%%s%%' ";
		$filter_params[] = $affiliate_user_login;
	}
	if ( !$show_deleted ) {
		$filters[] = " $affiliates_table.status = %s ";
		$filter_params[] = 'active';
	}
	if ( !$show_inoperative ) {
		$filters[] = " $affiliates_table.from_date <= %s AND ( $affiliates_table.thru_date IS NULL OR $affiliates_table.thru_date >= %s ) ";
		$filter_params[] = $today;
		$filter_params[] = $today;
	}
	if ( $from_datetime && $thru_datetime ) {
		$filters[] = " $affiliates_table.from_date >= %s AND ( $affiliates_table.thru_date IS NULL OR $affiliates_table.thru_date < %s ) ";
		$filter_params[] = $from_datetime;
		$filter_params[] = $thru_datetime;
	} else if ( $from_datetime ) {
		$filters[] = " $affiliates_table.from_date >= %s ";
		$filter_params[] = $from_datetime;
	} else if ( $thru_datetime ) {
		$filters[] = " $affiliates_table.thru_date < %s ";
		$filter_params[] = $thru_datetime;
	}
	
	if ( !empty( $filters ) ) {
		$filters = " WHERE " . implode( " AND ", $filters );
	} else {
		$filters = '';
	}
	
	$count_query = $wpdb->prepare( "SELECT COUNT(*) FROM $affiliates_table LEFT JOIN $affiliates_users_table ON $affiliates_table.affiliate_id = $affiliates_users_table.affiliate_id LEFT JOIN $wpdb->users on $affiliates_users_table.user_id = $wpdb->users.ID $filters", $filter_params );
	$count  = $wpdb->get_var( $count_query );
	if ( $count > $row_count ) {
		$paginate = true;
	} else {
		$paginate = false;
	}
	$pages = ceil ( $count / $row_count );
	if ( $paged > $pages ) {
		$paged = $pages;
	}
	if ( $paged != 0 ) {
		$offset = ( $paged - 1 ) * $row_count;
	}
	
	$query = $wpdb->prepare( "SELECT $affiliates_table.*, $wpdb->users.* FROM $affiliates_table LEFT JOIN $affiliates_users_table ON $affiliates_table.affiliate_id = $affiliates_users_table.affiliate_id LEFT JOIN $wpdb->users on $affiliates_users_table.user_id = $wpdb->users.ID $filters ORDER BY $orderby $order LIMIT $row_count OFFSET $offset", $filter_params );
	$results = $wpdb->get_results( $query, OBJECT );		

	$column_display_names = array(
		'affiliate_id' => __( 'Id', AFFILIATES_PLUGIN_DOMAIN ),
		'name'         => __( 'Affiliate', AFFILIATES_PLUGIN_DOMAIN ),
		'email'        => __( 'Email', AFFILIATES_PLUGIN_DOMAIN ),
		'user_login'   => __( 'Username', AFFILIATES_PLUGIN_DOMAIN ),
		'from_date'    => __( 'From', AFFILIATES_PLUGIN_DOMAIN ),
		'thru_date'    => __( 'Until', AFFILIATES_PLUGIN_DOMAIN ),
		'edit'         => __( 'Edit', AFFILIATES_PLUGIN_DOMAIN ),
		'remove'       => __( 'Remove', AFFILIATES_PLUGIN_DOMAIN ),
		'links'        => __( 'Links', AFFILIATES_PLUGIN_DOMAIN ),
	);
	
	$output .= '<div class="affiliates-overview">';
	
	$output .=
		'<div class="filters">' .
			'<label class="description" for="setfilters">' . __( 'Filters', AFFILIATES_PLUGIN_DOMAIN ) . '</label>' .
			'<form id="setfilters" action="" method="post">' .
				'<p>' .
				'<label class="affiliate-id-filter" for="affiliate_id">' . __( 'Affiliate Id', AFFILIATES_PLUGIN_DOMAIN ) . '</label>' .
				'<input class="affiliate-id-filter" name="affiliate_id" type="text" value="' . esc_attr( $affiliate_id ) . '"/>' .
				'<label class="affiliate-name-filter" for="affiliate_name">' . __( 'Affiliate Name', AFFILIATES_PLUGIN_DOMAIN ) . '</label>' .
				'<input class="affiliate-name-filter" name="affiliate_name" type="text" value="' . $affiliate_name . '"/>' .
				'<label class="affiliate-user-login-filter" for="affiliate_user_login">' . __( 'Affiliate Username', AFFILIATES_PLUGIN_DOMAIN ) . '</label>' .
				'<input class="affiliate-user-login-filter" name="affiliate_user_login" type="text" value="' . $affiliate_user_login . '" />' .
				'</p>' .
				'<p>' .
				'<label class="from-date-filter" for="from_date">' . __( 'From', AFFILIATES_PLUGIN_DOMAIN ) . '</label>' .
				'<input class="datefield from-date-filter" name="from_date" type="text" value="' . esc_attr( $from_date ) . '"/>'.
				'<label class="thru-date-filter" for="thru_date">' . __( 'Until', AFFILIATES_PLUGIN_DOMAIN ) . '</label>' .
				'<input class="datefield thru-date-filter" name="thru_date" type="text" class="datefield" value="' . esc_attr( $thru_date ) . '"/>'.
				'<label class="show-inoperative-filter" for="show_inoperative">' . __( 'Include inoperative affiliates', AFFILIATES_PLUGIN_DOMAIN ) . '</label>' .
				'<input class="show-inoperative-filter" name="show_inoperative" type="checkbox" ' . ( $show_inoperative ? 'checked="checked"' : '' ) . '/>' .
				'<label class="show-deleted-filter" for="show_deleted">' . __( 'Include removed affiliates', AFFILIATES_PLUGIN_DOMAIN ) . '</label>' .
				'<input class="show-deleted-filter" name="show_deleted" type="checkbox" ' . ( $show_deleted ? 'checked="checked"' : '' ) . '/>' .
				'</p>
				<p>' .
				wp_nonce_field( 'admin', AFFILIATES_ADMIN_AFFILIATES_FILTER_NONCE, true, false ) .
				'<input type="submit" value="' . __( 'Apply', AFFILIATES_PLUGIN_DOMAIN ) . '"/>' .
				'<input type="submit" name="clear_filters" value="' . __( 'Clear', AFFILIATES_PLUGIN_DOMAIN ) . '"/>' .
				'<input type="hidden" value="submitted" name="submitted"/>' .
				'</p>' .
			'</form>' .
		'</div>';
							
	$output .= '
		<div class="page-options">
			<form id="setrowcount" action="" method="post">
				<div>
					<label for="row_count">' . __('Results per page', AFFILIATES_PLUGIN_DOMAIN ) . '</label>' .
					'<input name="row_count" type="text" size="2" value="' . esc_attr( $row_count ) .'" />
					' . wp_nonce_field( 'admin', AFFILIATES_ADMIN_AFFILIATES_NONCE_1, true, false ) . '
					<input type="submit" value="' . __( 'Apply', AFFILIATES_PLUGIN_DOMAIN ) . '"/>
				</div>
			</form>
		</div>
		';
		
	if ( $paginate ) {
	  require_once( AFFILIATES_CORE_LIB . '/class-affiliates-pagination.php' );
		$pagination = new Affiliates_Pagination($count, null, $row_count);
		$output .= '<form id="posts-filter" method="post" action="">';
		$output .= '<div>';
		$output .= wp_nonce_field( 'admin', AFFILIATES_ADMIN_AFFILIATES_NONCE_2, true, false );
		$output .= '</div>';
		$output .= '<div class="tablenav top">';
		$output .= $pagination->pagination( 'top' );
		$output .= '</div>';
		$output .= '</form>';
	}
					
	$output .= '
		<table id="" class="wp-list-table widefat fixed" cellspacing="0">
		<thead>
			<tr>
			';
	
	foreach ( $column_display_names as $key => $column_display_name ) {
		$options = array(
			'orderby' => $key,
			'order' => $switch_order
		);
		$class = $key;
		if ( !in_array($key, array( 'edit', 'remove', 'links' ) ) ) {
			if ( strcmp( $key, $orderby ) == 0 ) {
				$lorder = strtolower( $order );
				$class = "$key manage-column sorted $lorder";
			} else {
				$class = "$key manage-column sortable";
			}
			$column_display_name = '<a href="' . esc_url( add_query_arg( $options, $current_url ) ) . '"><span>' . $column_display_name . '</span><span class="sorting-indicator"></span></a>';
		}
		$output .= "<th scope='col' class='$class'>$column_display_name</th>";
	}
	
	$output .= '</tr>
		</thead>
		<tbody>
		';
		
	if ( count( $results ) > 0 ) {
		for ( $i = 0; $i < count( $results ); $i++ ) {
			
			$result = $results[$i];
			
			$name_suffix = '';
			$class_deleted = '';
			if ( $is_deleted = ( strcmp( $result->status, 'deleted' ) == 0 ) ) {
				$class_deleted = ' deleted ';
				$name_suffix .= " " . __( '(removed)', AFFILIATES_PLUGIN_DOMAIN );
			}
			
			$class_inoperative = '';
			if ( $is_inoperative = ! ( ( $result->from_date <= $today ) && ( $result->thru_date == null || $result->thru_date >= $today ) ) ) {
				$class_inoperative = ' inoperative ';
				$name_suffix .= " " . __( '(inoperative)', AFFILIATES_PLUGIN_DOMAIN );
			}
			
			
			$output .= '<tr class="' . $class_deleted . $class_inoperative . ( $i % 2 == 0 ? 'even' : 'odd' ) . '">';
			$output .= "<td class='affiliate-id'>";
			if ( affiliates_encode_affiliate_id( $result->affiliate_id ) != $result->affiliate_id ) {
				$output .= '<span class="encoded-hint" title="' . affiliates_encode_affiliate_id( $result->affiliate_id ) . '">' . $result->affiliate_id . '</span>';
			} else {
				$output .= $result->affiliate_id;
			}
			$output .= "</td>";
			$output .= "<td class='affiliate-name'>" . stripslashes( wp_filter_nohtml_kses( $result->name ) ) . $name_suffix . "</td>";
			$output .= "<td class='affiliate-email'>" . $result->email;
			if ( isset( $result->email ) && isset( $result->user_email ) && strcmp( $result->email, $result->user_email ) !== 0 ) {
				$output .= '<span title="' . sprintf( __( 'There are different email addresses on record for the affiliate and the associated user. This might be ok, but if in doubt please check. The email address on file for the user is %s'), $result->user_email ) . '" class="warning"> [&nbsp;!&nbsp]</span>';
			}
			$output .= "</td>";
			$output .= "<td class='affiliate-user-login'>";
			if ( !empty( $result->ID ) ) {			
				if ( current_user_can( 'edit_user',  $result->ID ) ) {
					$output .= '<a target="_blank" href="' . esc_url( "user-edit.php?user_id=$result->ID" ) . '">' . $result->user_login . '</a>';
				} else {
					$output .= $result->user_login;
				}
			}
			
			$output .= "</td>";
			$output .= "<td class='from-date'>$result->from_date</td>";
			$output .= "<td class='thru-date'>$result->thru_date</td>";
			
			$output .= "<td class='edit'><a href='" . esc_url( add_query_arg( 'paged', $paged, $current_url ) ) . "&action=edit&affiliate_id=" . $result->affiliate_id . "' alt='" . __( 'Edit', AFFILIATES_PLUGIN_DOMAIN) . "'><img src='". AFFILIATES_PLUGIN_URL ."images/edit.png'/></a></td>";
			$output .= "<td class='remove'>" .
				( !$is_deleted && ( !isset( $result->type ) || ( $result->type != AFFILIATES_DIRECT_TYPE )  ) ?
				"<a href='" . esc_url( $current_url ) . "&action=remove&affiliate_id=" . $result->affiliate_id . "' alt='" . __( 'Remove', AFFILIATES_PLUGIN_DOMAIN) . "'><img src='". AFFILIATES_PLUGIN_URL ."images/remove.png'/></a>"
				: "" ) .
				"</td>";
			$output .= "<td class='links'>";
			$encoded_id = affiliates_encode_affiliate_id( $result->affiliate_id );
			$output .= 
				__( 'Link', AFFILIATES_PLUGIN_DOMAIN ) .
				': ' .
				'<span class="affiliate-link">' . get_bloginfo('url') . '?affiliates=' . $encoded_id . '</span>' .
				' &nbsp; ' .
				__( 'URL Parameter', AFFILIATES_PLUGIN_DOMAIN ) .
				': ' .
				 '<span class="affiliate-link-param">' . '?affiliates=' . $encoded_id . '</span>' .
				'<br/>' .
				__( 'Pretty', AFFILIATES_PLUGIN_DOMAIN ) .
				': ' .
				'<span class="affiliate-permalink">' . get_bloginfo('url') . '/affiliates/' . $encoded_id . '</span>' .
				( $wp_rewrite->using_permalinks() ? '' :
					'<span class="warning">' .
					'&nbsp;' .
					sprintf( __( 'you need to adjust your <a href="%s">permalink settings</a>.', AFFILIATES_PLUGIN_DOMAIN ), get_admin_url( null, 'options-permalink.php') ) .
					'</span>'
					) ;
			$output .= "</td>";
			
			$output .= '</tr>';
		}
	} else {
		$output .= '<tr><td colspan="10">' . __('There are no results.', AFFILIATES_PLUGIN_DOMAIN ) . '</td></tr>';
	}
		
	$output .= '</tbody>';
	$output .= '</table>';
					
	if ( $paginate ) {
	  require_once( AFFILIATES_CORE_LIB . '/class-affiliates-pagination.php' );
		$pagination = new Affiliates_Pagination($count, null, $row_count);
		$output .= '<div class="tablenav bottom">';
		$output .= $pagination->pagination( 'bottom' );
		$output .= '</div>';			
	}

	$output .= '</div>'; // .affiliates-overview
	$output .= '</div>'; // .manage-affiliates
	echo $output;
	affiliates_footer();
} // function affiliates_admin_affiliates()
?>