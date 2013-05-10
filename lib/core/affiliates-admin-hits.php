<?php
/**
 * affiliates-admin-hits.php
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
// Shows hits by date

include_once( AFFILIATES_CORE_LIB . '/class-affiliates-date-helper.php');

function affiliates_admin_hits() {
	
	global $wpdb, $affiliates_options;
	
	$output = '';
	
	if ( !current_user_can( AFFILIATES_ACCESS_AFFILIATES ) ) {
		wp_die( __( 'Access denied.', AFFILIATES_PLUGIN_DOMAIN ) );
	}
	
	if (
		isset( $_POST['from_date'] ) ||
		isset( $_POST['thru_date'] ) ||
		isset( $_POST['clear_filters'] ) ||
		isset( $_POST['affiliate_id'] ) ||
		isset( $_POST['expanded'] ) ||
		isset( $_POST['expanded_hits'] ) ||
		isset( $_POST['expanded_referrals'] ) ||
		isset( $_POST['show_inoperative'] )
	) {
		if ( !wp_verify_nonce( $_POST[AFFILIATES_ADMIN_HITS_FILTER_NONCE], 'admin' ) ) {
			wp_die( __( 'Access denied.', AFFILIATES_PLUGIN_DOMAIN ) );
		}
	}
	
	// filters
	$from_date          = $affiliates_options->get_option( 'hits_from_date', null );
	$thru_date          = $affiliates_options->get_option( 'hits_thru_date', null );
	$affiliate_id       = $affiliates_options->get_option( 'hits_affiliate_id', null );
	$expanded           = $affiliates_options->get_option( 'hits_expanded', null ); // @todo input ist not shown, eventually remove unless ...
	$expanded_referrals = $affiliates_options->get_option( 'hits_expanded_referrals', null );
	$expanded_hits      = $affiliates_options->get_option( 'hits_expanded_hits', null );
	$show_inoperative   = $affiliates_options->get_option( 'hits_show_inoperative', null );
	
	if ( isset( $_POST['clear_filters'] ) ) {
		$affiliates_options->delete_option( 'hits_from_date' );
		$affiliates_options->delete_option( 'hits_thru_date' );
		$affiliates_options->delete_option( 'hits_affiliate_id' );
		$affiliates_options->delete_option( 'hits_expanded' );
		$affiliates_options->delete_option( 'hits_expanded_referrals' );
		$affiliates_options->delete_option( 'hits_expanded_hits' );
		$affiliates_options->delete_option( 'hits_show_inoperative' );
		$from_date = null;
		$thru_date = null;
		$affiliate_id = null;
		$expanded = null;
		$expanded_hits = null;
		$expanded_referrals = null;
		$show_inoperative = null;
	} else if ( isset( $_POST['submitted'] ) ) {
		// filter by date(s)
		if ( !empty( $_POST['from_date'] ) ) {
			$from_date = date( 'Y-m-d', strtotime( $_POST['from_date'] ) );
			$affiliates_options->update_option( 'hits_from_date', $from_date );
		} else {
			$from_date = null;
			$affiliates_options->delete_option( 'hits_from_date' );
		}
		if ( !empty( $_POST['thru_date'] ) ) {
			$thru_date = date( 'Y-m-d', strtotime( $_POST['thru_date'] ) );
			$affiliates_options->update_option( 'hits_thru_date', $thru_date );
		} else {
			$thru_date = null;
			$affiliates_options->delete_option( 'hits_thru_date' );
		}
		if ( $from_date && $thru_date ) {
			if ( strtotime( $from_date ) > strtotime( $thru_date ) ) {
				$thru_date = null;
				$affiliates_options->delete_option( 'hits_thru_date' );
			}
		}

		// filter by affiliate id
		if ( !empty( $_POST['affiliate_id'] ) ) {
			$affiliate_id = affiliates_check_affiliate_id( $_POST['affiliate_id'] );
			if ( $affiliate_id ) {
				$affiliates_options->update_option( 'hits_affiliate_id', $affiliate_id );
			}
		} else if ( isset( $_POST['affiliate_id'] ) ) { // empty && isset => '' => all
			$affiliate_id = null;
			$affiliates_options->delete_option( 'hits_affiliate_id' );	
		}

		// expanded details?
		if ( !empty( $_POST['expanded'] ) ) {
			$expanded = true;
			$affiliates_options->update_option( 'hits_expanded', true );
		} else {
			$expanded = false;
			$affiliates_options->delete_option( 'hits_expanded' );
		}
		if ( !empty( $_POST['expanded_hits'] ) ) {
			$expanded_hits = true;
			$affiliates_options->update_option( 'hits_expanded_hits', true );
		} else {
			$expanded_hits = false;
			$affiliates_options->delete_option( 'hits_expanded_hits' );
		}
		if ( !empty( $_POST['expanded_referrals'] ) ) {
			$expanded_referrals = true;
			$affiliates_options->update_option( 'hits_expanded_referrals', true );
		} else {
			$expanded_referrals = false;
			$affiliates_options->delete_option( 'hits_expanded_referrals' );
		}
		if ( !empty( $_POST['show_inoperative'] ) ) {
			$show_inoperative = true;
			$affiliates_options->update_option( 'hits_show_inoperative', true );
		} else {
			$show_inoperative = false;
			$affiliates_options->delete_option( 'hits_show_inoperative' );
		}
	}
	
	if ( isset( $_POST['row_count'] ) ) {
		if ( !wp_verify_nonce( $_POST[AFFILIATES_ADMIN_HITS_NONCE_1], 'admin' ) ) {
			wp_die( __( 'Access denied.', AFFILIATES_PLUGIN_DOMAIN ) );
		}
	}
	
	if ( isset( $_POST['paged'] ) ) {
		if ( !wp_verify_nonce( $_POST[AFFILIATES_ADMIN_HITS_NONCE_2], 'admin' ) ) {
			wp_die( __( 'Access denied.', AFFILIATES_PLUGIN_DOMAIN ) );
		}
	}
	
	$current_url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	$current_url = remove_query_arg( 'paged', $current_url );
	
	$affiliates_table = _affiliates_get_tablename( 'affiliates' );
	$referrals_table = _affiliates_get_tablename( 'referrals' );
	$hits_table = _affiliates_get_tablename( 'hits' );
	
	$output .=
		'<div>' .
			'<h2>' .
				__( 'Visits & Referrals', AFFILIATES_PLUGIN_DOMAIN ) .
			'</h2>' .
		'</div>';

	$row_count = isset( $_POST['row_count'] ) ? intval( $_POST['row_count'] ) : 0;
	
	if ($row_count <= 0) {
		$row_count = $affiliates_options->get_option( 'affiliates_hits_per_page', AFFILIATES_HITS_PER_PAGE );
	} else {
		$affiliates_options->update_option('affiliates_hits_per_page', $row_count );
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
		case 'date' :
		case 'visits' :
		case 'hits' :
		case 'referrals' :
		case 'ratio' :
			break;
		case 'affiliate_id' :
			$orderby = 'name';
		default:
			$orderby = 'date';
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
			$order = 'DESC';
			$switch_order = 'ASC';
	}
	
	if ( $from_date || $thru_date || $affiliate_id ) {
		$filters = " WHERE ";
	} else {
		$filters = '';			
	}
	$filter_params = array();
	// We now have the desired dates from the user's point of view, i.e. in her timezone.
	// If supported, adjust the dates for the site's timezone:
	if ( $from_date ) {
		$from_datetime = DateHelper::u2s( $from_date );
	}
	if ( $thru_date ) {
		$thru_datetime = DateHelper::u2s( $thru_date, 24*3600 );
	}
	if ( $from_date && $thru_date ) {
		$filters .= " datetime >= %s AND datetime < %s ";
		$filter_params[] = $from_datetime;
		$filter_params[] = $thru_datetime;
	} else if ( $from_date ) {
		$filters .= " datetime >= %s ";
		$filter_params[] = $from_datetime;
	} else if ( $thru_date ) {
		$filters .= " datetime < %s ";
		$filter_params[] = $thru_datetime;
	}
	if ( $affiliate_id ) {
		if ( $from_date || $thru_date ) {
			$filters .= " AND ";
		}
		$filters .= " affiliate_id = %d ";
		$filter_params[] = $affiliate_id;
	}
	
	// how many are there ?
	$count_query = $wpdb->prepare(
		"SELECT date FROM $hits_table h
		$filters
		GROUP BY date
		",
		$filter_params
	);
	$wpdb->query( $count_query );
	$count = $wpdb->num_rows;
	
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
			
	// Get the summarized results, these are grouped by date.
	// If there were any referral on a date without a hit, it would not be included:
	// Example conditions:
	// - 2011-02-01 23:59:59 hit recorded
	// - 2011-02-02 00:10:05 referral recorded
	// - no hits recorded on 2011-02-02
	// =>
	// - the referral will not show up
	// So, for ratio calculation, only the date with actual visits and referrals will show up.
	// Referrals on dates without visits would give an infinite ratio (x referrals / 0 visits).
	// We have a separate page which shows all referrals.
	$query = $wpdb->prepare("
			SELECT
				*,
				count(distinct ip) visits,
				sum(count) hits,
				(select count(*) from $referrals_table where date(datetime) = h.date ". ( $affiliate_id ? " AND affiliate_id = " . intval( $affiliate_id ) . " " : "" )  .") referrals,
				((select count(*) from $referrals_table where date(datetime) = h.date ". ( $affiliate_id ? " AND affiliate_id = " . intval( $affiliate_id ) . " " : "" )  .")/count(distinct ip)) ratio
			FROM $hits_table h
	$filters
			GROUP BY date
			ORDER BY $orderby $order
			LIMIT $row_count OFFSET $offset
			",
	$filter_params
	);

	$results = $wpdb->get_results( $query, OBJECT );		

	$column_display_names = array(
		'date'      => __( 'Date', AFFILIATES_PLUGIN_DOMAIN ) . '*',
		'visits'    => __( 'Visits', AFFILIATES_PLUGIN_DOMAIN ),
		'hits'      => __( 'Hits', AFFILIATES_PLUGIN_DOMAIN ),
		'referrals' => __( 'Referrals', AFFILIATES_PLUGIN_DOMAIN ),
		'ratio'     => __( 'Ratio', AFFILIATES_PLUGIN_DOMAIN )
	);
	
	$output .= '<div id="" class="hits-overview">';
		
	$affiliates = affiliates_get_affiliates( true, !$show_inoperative );
	$affiliates_select = '';
	if ( !empty( $affiliates ) ) {
		$affiliates_select .= '<label class="affiliate-id-filter" for="affiliate_id">' . __('Affiliate', AFFILIATES_PLUGIN_DOMAIN ) . '</label>';
		$affiliates_select .= '<select class="affiliate-id-filter" name="affiliate_id">';
		$affiliates_select .= '<option value="">--</option>';
		foreach ( $affiliates as $affiliate ) {
			if ( $affiliate_id == $affiliate['affiliate_id']) {
				$selected = ' selected="selected" ';
			} else {
				$selected = '';
			}
			$affiliates_select .= '<option ' . $selected . ' value="' . esc_attr( $affiliate['affiliate_id'] ) . '">' . esc_attr( stripslashes( $affiliate['name'] ) ) . '</option>';
		}
		$affiliates_select .= '</select>';
	}
	
	$output .=
		'<div class="filters">' .
			'<label class="description" for="setfilters">' . __( 'Filters', AFFILIATES_PLUGIN_DOMAIN ) . '</label>' .
			'<form id="setfilters" action="" method="post">' .
				'<p>' .
				$affiliates_select .
				'</p>
				<p>' .
				'<label class="from-date-filter" for="from_date">' . __('From', AFFILIATES_PLUGIN_DOMAIN ) . '</label>' .
				'<input class="datefield from-date-filter" name="from_date" type="text" value="' . esc_attr( $from_date ) . '"/>'.
				'<label class="thru-date-filter" for="thru_date">' . __('Until', AFFILIATES_PLUGIN_DOMAIN ) . '</label>' .
				'<input class="datefield thru-date-filter" name="thru_date" type="text" class="datefield" value="' . esc_attr( $thru_date ) . '"/>'.
				'</p>
				<p>' .
				wp_nonce_field( 'admin', AFFILIATES_ADMIN_HITS_FILTER_NONCE, true, false ) .
				'<input class="button" type="submit" value="' . __( 'Apply', AFFILIATES_PLUGIN_DOMAIN ) . '"/>' .
//				'<label class="expanded-filter" for="expanded">' . __( 'Expand details', AFFILIATES_PLUGIN_DOMAIN ) . '</label>' .
//				'<input class="expanded-filter" name="expanded" type="checkbox" ' . ( $expanded ? 'checked="checked"' : '' ) . '/>' .
				'<label class="expanded-filter" for="expanded_referrals">' . __( 'Expand referrals', AFFILIATES_PLUGIN_DOMAIN ) . '</label>' .
				'<input class="expanded-filter" name="expanded_referrals" type="checkbox" ' . ( $expanded_referrals ? 'checked="checked"' : '' ) . '/>' .
				'<label class="expanded-filter" for="expanded_hits">' . __( 'Expand hits', AFFILIATES_PLUGIN_DOMAIN ) . '</label>' .
				'<input class="expanded-filter" name="expanded_hits" type="checkbox" ' . ( $expanded_hits ? 'checked="checked"' : '' ) . '/>' .
				'<label class="show-inoperative-filter" for="show_inoperative">' . __( 'Include inoperative affiliates', AFFILIATES_PLUGIN_DOMAIN ) . '</label>' .
				'<input class="show-inoperative-filter" name="show_inoperative" type="checkbox" ' . ( $show_inoperative ? 'checked="checked"' : '' ) . '/>' .
				'<input class="button" type="submit" name="clear_filters" value="' . __( 'Clear', AFFILIATES_PLUGIN_DOMAIN ) . '"/>' .
				'<input type="hidden" value="submitted" name="submitted"/>' .
				'</p>' .
			'</form>' .
		'</div>';
						
	$output .= '
		<div class="page-options">
			<form id="setrowcount" action="" method="post">
				<div>
					<label for="row_count">' . __('Results per page', AFFILIATES_PLUGIN_DOMAIN ) . '</label>' .
					//<input name="page" type="hidden" value="' . esc_attr( $page ) . '"/>
					'<input name="row_count" type="text" size="2" value="' . esc_attr( $row_count ) .'" />
					' . wp_nonce_field( 'admin', AFFILIATES_ADMIN_HITS_NONCE_1, true, false ) . '
					<input class="button" type="submit" value="' . __( 'Apply', AFFILIATES_PLUGIN_DOMAIN ) . '"/>
				</div>
			</form>
		</div>
		';
		
	if ( $paginate ) {
	  require_once( AFFILIATES_CORE_LIB . '/class-affiliates-pagination.php' );
		$pagination = new Affiliates_Pagination($count, null, $row_count);
		$output .= '<form id="posts-filter" method="post" action="">';
		$output .= '<div>';
		$output .= wp_nonce_field( 'admin', AFFILIATES_ADMIN_HITS_NONCE_2, true, false );
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
		$class = "";
		if ( strcmp( $key, $orderby ) == 0 ) {
			$lorder = strtolower( $order );
			$class = "$key manage-column sorted $lorder";
		} else {
			$class = "$key manage-column sortable";
		}
		$column_display_name = '<a href="' . esc_url( add_query_arg( $options, $current_url ) ) . '"><span>' . $column_display_name . '</span><span class="sorting-indicator"></span></a>';
		$output .= "<th scope='col' class='$class'>$column_display_name</th>";
	}
	
	$output .= '</tr>
		</thead>
		<tbody>
		';
		
	if ( count( $results ) > 0 ) {
		for ( $i = 0; $i < count( $results ); $i++ ) {
			
			$result = $results[$i];
			$output .= '<tr class=" ' . ( $i % 2 == 0 ? 'even' : 'odd' ) . '">';
			$output .= "<td class='date'>$result->date</td>";
//			$output .= '<td class="date">' . DateHelper::formatDate( DateHelper::s2u( $result->datetime ) ) . '</td>';
			$output .= "<td class='visits'>$result->visits</td>";
			$output .= "<td class='hits'>$result->hits</td>";
			$output .= "<td class='referrals'>$result->referrals</td>";
			$output .= "<td class='ratio'>$result->ratio</td>";
			$output .= '</tr>';
			
			if ( $expanded || $expanded_referrals || $expanded_hits ) {

				//
				// expanded : referrals ----------------------------------------
				//
				if ( $expanded_referrals ) {
					$referrals_filters = " WHERE date(datetime) = %s ";
					$referrals_filter_params = array( $result->date );
					if ( $affiliate_id ) {
						$referrals_filters .= " AND r.affiliate_id = %d ";
						$referrals_filter_params[] = $affiliate_id;
					}
					$referrals_orderby = "datetime $order";
					
					$referrals_query = $wpdb->prepare(
						"SELECT *
						FROM $referrals_table r
						LEFT JOIN $affiliates_table a ON r.affiliate_id = a.affiliate_id
						$referrals_filters
						ORDER BY $referrals_orderby
						",
						$referrals_filter_params
					);
					$referrals = $wpdb->get_results( $referrals_query, OBJECT );
					if ( count($referrals) > 0 ) {
						$output .= '<tr class=" ' . ( $i % 2 == 0 ? 'even' : 'odd' ) . '">';
						$output .= '<td colspan="5">';
						$output .= '<div class="details-referrals">';
						$output .= '<p class="description">' . __( 'Referrals', AFFILIATES_PLUGIN_DOMAIN ) . '</p>';
						$output .= '
							<table id="details-referrals-' . esc_attr( $result->date ) . '" class="details-referrals" cellspacing="0">
							<thead>
							<tr>
							<th scope="col" class="datetime">' . __( 'Time', AFFILIATES_PLUGIN_DOMAIN ) . '</th>
							<th scope="col" class="post-id">' . __( 'Post', AFFILIATES_PLUGIN_DOMAIN ) . '</th>
							<th scope="col" class="affiliate-id">' . __( 'Affiliate', AFFILIATES_PLUGIN_DOMAIN ) . '</th>
							</tr>
							</thead>
							<tbody>
							';
						foreach ( $referrals as $referral ) {
							$output .= '<tr class="details-referrals ' . ( $i % 2 == 0 ? 'even' : 'odd' ) . '">';
//							$output .= "<td class='datetime'>$referral->datetime</td>";
							$output .= '<td class="datetime">' . DateHelper::s2u( $referral->datetime ) . '</td>';
							$link = get_permalink( $referral->post_id );
							$title = get_the_title( $referral->post_id );
							$output .= '<td class="post-id"><a href="' . esc_attr( $link ) . '" target="_blank">' . wp_filter_nohtml_kses( $title ) . '</a></td>';
							$output .= "<td class='affiliate-id'>" . stripslashes( wp_filter_nohtml_kses( $referral->name ) ) . "</td>";
							$output .= '</tr>';
						}
						$output .= '</tbody></table>';
						$output .= '</div>'; // .details-referrals
						$output .= '</td></tr>';
					}
				} // if $expanded_referrals
				
				//
				// expanded : hits ----------------------------------------
				//
				
				if ( $expanded_hits ) {
					// get the detailed results for hits
					$details_orderby = "date $order, time $order";
					
					$details_filters = " WHERE h.date = %s ";
					$details_filter_params = array( $result->date );
					if ( $affiliate_id ) {
						$details_filters .= " AND h.affiliate_id = %d ";
						$details_filter_params[] = $affiliate_id;
					}					

					$details_query = $wpdb->prepare(
						"SELECT *
						FROM $hits_table h
						LEFT JOIN $affiliates_table a ON h.affiliate_id = a.affiliate_id
						$details_filters
						ORDER BY $details_orderby
						",
						$details_filter_params
					);
					$hits = $wpdb->get_results( $details_query, OBJECT );
					$output .= '<tr class=" ' . ( $i % 2 == 0 ? 'even' : 'odd' ) . '">';
					$output .= '<td colspan="5">';
					$output .= '<div class="details-hits">';
					$output .= '<p class="description">' . __( 'Hits', AFFILIATES_PLUGIN_DOMAIN ) . '</p>';
					$output .= '
						<table id="details-hits-' . esc_attr( $result->date ) . '" class="details-hits" cellspacing="0">
						<thead>
						<tr>
						<th scope="col" class="time">' . __( 'Time', AFFILIATES_PLUGIN_DOMAIN ) . '</th>
						<th scope="col" class="ip">' . __( 'IP', AFFILIATES_PLUGIN_DOMAIN ) . '</th>
						<th scope="col" class="affiliate-id">' . __( 'Affiliate', AFFILIATES_PLUGIN_DOMAIN ) . '</th>
						</tr>
						</thead>
						<tbody>
						';
					foreach ( $hits as $hit ) {
						$output .= '<tr class=" details ' . ( $i % 2 == 0 ? 'even' : 'odd' ) . '">';
//						$output .= "<td class='time'>$hit->time</td>";
						$output .= '<td class="time">' . DateHelper::s2u( $hit->datetime ) . '</td>';
						$output .= "<td class='ip'>" . long2ip( $hit->ip ) . "</td>";
						$output .= "<td class='affiliate-id'>" . stripslashes( wp_filter_nohtml_kses( $hit->name ) ) . "</td>";
						$output .= '</tr>';
					}
					$output .= '</tbody></table>';
					$output .= '</div>'; // .details-hits
					$output .= '</td></tr>';
				} // if $expanded_hits

			} // expanded
		}
	} else {
		$output .= '<tr><td colspan="5">' . __('There are no results.', AFFILIATES_PLUGIN_DOMAIN ) . '</td></tr>';
	}
		
	$output .= '</tbody>';
	$output .= '</table>';
					
	if ( $paginate ) {
	  require_once( AFFILIATES_CORE_LIB . '/class-affiliates-pagination.php' );
		$pagination = new Affiliates_Pagination( $count, null, $row_count );
		$output .= '<div class="tablenav bottom">';
		$output .= $pagination->pagination( 'bottom' );
		$output .= '</div>';			
	}

	$server_dtz = DateHelper::getServerDateTimeZone();
	$output .=
		'<p>' .
			sprintf(
				__( "* Date is given for the server's time zone : %s, which has an offset of %s hours with respect to GMT.", AFFILIATES_PLUGIN_DOMAIN ),
				$server_dtz->getName(),
				$server_dtz->getOffset( new DateTime() ) / 3600.0
			) .
			'</p>';
	$output .= '</div>'; // .visits-overview
	echo $output;
	affiliates_footer();
} // function affiliates_admin_hits()
?>