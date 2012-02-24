<?php
/**
 * affiliates-admin.php
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
 * Affiliates overview and summarized statistics.
 */
function affiliates_admin() {
	
	global $wpdb;

	if ( !current_user_can( AFFILIATES_ACCESS_AFFILIATES ) ) {
		wp_die( __( 'Access denied.', AFFILIATES_PLUGIN_DOMAIN ) );
	}
	
	echo '<h2>' . __( 'Affiliates Overview', AFFILIATES_PLUGIN_DOMAIN ) . '</h2>';
	
	$days_back = 14;
	$day_interval = 7;
	
	$affiliates_table = _affiliates_get_tablename( 'affiliates' );
	$hits_table = _affiliates_get_tablename( 'hits' );
	$referrals_table = _affiliates_get_tablename( 'referrals' );
	$today = date( 'Y-m-d', time() );
	$from_date = date( 'Y-m-d', time() - $days_back * 3600 * 24 );
	
	$affiliates_subquery = " affiliate_id IN (SELECT affiliate_id FROM $affiliates_table WHERE from_date <= %s AND ( thru_date IS NULL OR thru_date >= %s ) AND status = 'active') ";
	
	// hits per day
	$query = "SELECT date, sum(count) as hits FROM $hits_table WHERE date >= %s AND date <= %s AND " . $affiliates_subquery . " GROUP BY date";
	$hit_results = $wpdb->get_results( $wpdb->prepare( $query,
		$from_date, $today, $today, $today
	 ));
	$hits = array();
	foreach( $hit_results as $hit_result ) {
		$hits[$hit_result->date] = $hit_result->hits;
	}
	
	// visits per day
	$query = "SELECT count(DISTINCT IP) visits, date FROM $hits_table WHERE date >= %s AND date <= %s AND " . $affiliates_subquery . " GROUP BY date";
	$visit_results = $wpdb->get_results( $wpdb->prepare( $query,
		$from_date, $today, $today, $today
	));
	$visits = array();
	foreach( $visit_results as $visit_result ) {
		$visits[$visit_result->date] = $visit_result->visits;
	}
	
	// referrals per day
	$query = "SELECT count(referral_id) referrals, date(datetime) date FROM $referrals_table WHERE status = %s AND date(datetime) >= %s AND date(datetime) <= %s AND " . $affiliates_subquery . " GROUP BY date";
	$results = $wpdb->get_results( $wpdb->prepare( $query,
		AFFILIATES_REFERRAL_STATUS_ACCEPTED, $from_date, $today, $today, $today
	));
	$accepted = array();
	foreach( $results as $result ) {
		$accepted[$result->date] = $result->referrals;
	}
	
	$results = $wpdb->get_results( $wpdb->prepare( $query,
		AFFILIATES_REFERRAL_STATUS_CLOSED, $from_date, $today, $today, $today
	));
	$closed = array();
	foreach( $results as $result ) {
		$closed[$result->date] = $result->referrals;
	}
	
	$results = $wpdb->get_results( $wpdb->prepare( $query,
		AFFILIATES_REFERRAL_STATUS_PENDING, $from_date, $today, $today, $today
	));
	$pending = array();
	foreach( $results as $result ) {
		$pending[$result->date] = $result->referrals;
	}
	
	$results = $wpdb->get_results( $wpdb->prepare( $query,
		AFFILIATES_REFERRAL_STATUS_REJECTED, $from_date, $today, $today, $today
	));
	$rejected = array();
	foreach( $results as $result ) {
		$rejected[$result->date] = $result->referrals;
	}

	$hits_table = '<table class="affiliates-overview hits"><caption>'.__( 'Hits', AFFILIATES_PLUGIN_DOMAIN ) . '</caption><thead><tr><td></td>';
	for ( $day = -$days_back; $day <= 0; $day++ ) {
		$date = date( 'Y-m-d', time()+$day*3600*24 );
		if ( $day % $day_interval == 0 ) {
			$hits_table .= '<th scope="col">' . $date . '</th>';
		} else {
			$hits_table .= '<th scope="col"></th>';
		}
	}
	$hits_table .= '</tr></thead><tbody><tr><th scope="row">' . __( 'Hits', AFFILIATES_PLUGIN_DOMAIN ) . '</th>';
	for ( $day = -$days_back; $day <= 0; $day++ ) {
		$date = date( 'Y-m-d', time()+$day*3600*24 );
		$value = isset( $hits[$date] ) ? $hits[$date] : 0; 
		$hits_table .= '<td>' . $value . '</td>';
	}
	$hits_table .= '</tr></tbody></table>';

	$visits_table = '<table class="affiliates-overview visits"><caption>'.__( 'Visits', AFFILIATES_PLUGIN_DOMAIN ) . '</caption><thead><tr><td></td>';
	for ( $day = -$days_back; $day <= 0; $day++ ) {
		$date = date( 'Y-m-d', time()+$day*3600*24 );
		if ( $day % $day_interval == 0 ) {
			$visits_table .= '<th scope="col">' . $date . '</th>';
		} else {
			$visits_table .= '<th scope="col"></th>';
		}
	}
	$visits_table .= '</tr></thead><tbody><tr><th scope="row">' . __( 'Visits', AFFILIATES_PLUGIN_DOMAIN ) . '</th>';
	for ( $day = -$days_back; $day <= 0; $day++ ) {
		$date = date( 'Y-m-d', time()+$day*3600*24 );
		$value = isset( $visits[$date] ) ? $visits[$date] : 0;
		$visits_table .= '<td>' . $value . '</td>';
	}
	$visits_table .= '</tr></tbody></table>';
	
	$referrals_table = '<table class="affiliates-overview referrals"><caption>'.__( 'Referrals', AFFILIATES_PLUGIN_DOMAIN ) . '</caption><thead><tr><td></td>';
	for ( $day = -$days_back; $day <= 0; $day++ ) {
		$date = date( 'Y-m-d', time()+$day*3600*24 );
		if ( $day % $day_interval == 0 ) {
			$referrals_table .= '<th scope="col">' . $date . '</th>';
		} else {
			$referrals_table .= '<th scope="col"></th>';
		}
	}
	$referrals_table .= '</tr></thead><tbody><tr><th scope="row">' . __( 'Accepted', AFFILIATES_PLUGIN_DOMAIN ) . '</th>';
	for ( $day = -$days_back; $day <= 0; $day++ ) {
		$date = date( 'Y-m-d', time()+$day*3600*24 );
		$value = isset( $accepted[$date] ) ? $accepted[$date] : 0;
		$referrals_table .= '<td>' . $value . '</td>';
	}
	$referrals_table .= '</tr>';
	$referrals_table .= '</tr></thead><tbody><tr><th scope="row">' . __( 'Closed', AFFILIATES_PLUGIN_DOMAIN ) . '</th>';
	for ( $day = -$days_back; $day <= 0; $day++ ) {
		$date = date( 'Y-m-d', time()+$day*3600*24 );
		$value = isset( $closed[$date] ) ? $closed[$date] : 0;
		$referrals_table .= '<td>' . $value . '</td>';
	}
	$referrals_table .= '</tr>';
	$referrals_table .= '</tr></thead><tbody><tr><th scope="row">' . __( 'Pending', AFFILIATES_PLUGIN_DOMAIN ) . '</th>';
	for ( $day = -$days_back; $day <= 0; $day++ ) {
		$date = date( 'Y-m-d', time()+$day*3600*24 );
		$value = isset( $pending[$date] ) ? $pending[$date] : 0;
		$referrals_table .= '<td>' . $value . '</td>';
	}
	$referrals_table .= '</tr>';
	$referrals_table .= '</tr></thead><tbody><tr><th scope="row">' . __( 'Rejected', AFFILIATES_PLUGIN_DOMAIN ) . '</th>';
	for ( $day = -$days_back; $day <= 0; $day++ ) {
		$date = date( 'Y-m-d', time()+$day*3600*24 );
		$value = isset( $rejected[$date] ) ? $rejected[$date] : 0;
		$referrals_table .= '<td>' . $value . '</td>';
	}
	$referrals_table .= '</tr></tbody></table>';
	
	echo '<h3>' . sprintf( __( '%d Day Charts', AFFILIATES_PLUGIN_DOMAIN ), $days_back ) . '</h2>';
	echo '<div class="manage" style="margin-right:1em">';
	echo $referrals_table;
	echo $visits_table;
	echo $hits_table;
	echo '<br class="clear"/>';
	echo '</div>';
	
	echo '<h3>' . __( 'Statistics Summary', AFFILIATES_PLUGIN_DOMAIN ) . '</h3>';
	for ( $i = 0; $i < 3; $i++ ) {
		$add_class = "";
		switch ( $i ) {
			case 0:
				$affiliates = affiliates_get_affiliates( true, true );
				$title = __( 'From operative affiliates:', AFFILIATES_PLUGIN_DOMAIN );
				$info = sprintf( _n( 'There is 1 operative affiliate', 'There are %d operative affiliates', count( $affiliates ), AFFILIATES_PLUGIN_DOMAIN ), count( $affiliates ) );
				$add_class = "active valid";
				break;
			case 1:
				$affiliates = affiliates_get_affiliates( true, false );
				$title = __( 'From operative and non-operative affiliates:', AFFILIATES_PLUGIN_DOMAIN );
				$info = sprintf( _n( 'There is 1 affiliate in this set', 'There are %d affiliates in this set', count( $affiliates ), AFFILIATES_PLUGIN_DOMAIN ), count( $affiliates ) );
				$add_class = "active";
				break;
			case 2:
				$affiliates = affiliates_get_affiliates( false, false );
				$title = __( 'All time (includes data from deleted affiliates):', AFFILIATES_PLUGIN_DOMAIN );
				$info = sprintf( _n( 'There is 1 affiliate in this set', 'There are %d affiliates in this set', count( $affiliates ), AFFILIATES_PLUGIN_DOMAIN ), count( $affiliates ) );
				break;
		}
		$hits               = 0;
		$visits             = 0;
		$referrals_accepted = 0;
		$referrals_closed   = 0;
		$referrals_pending  = 0;
		$referrals_rejected = 0;		
		foreach ( $affiliates as $affiliate ) {
			$affiliate_id = $affiliate['affiliate_id'];
			$hits      += affiliates_get_affiliate_hits( $affiliate_id );
			$visits    += affiliates_get_affiliate_visits( $affiliate_id );
			$referrals_accepted += affiliates_get_affiliate_referrals( $affiliate_id, null, null, AFFILIATES_REFERRAL_STATUS_ACCEPTED );
			$referrals_closed   += affiliates_get_affiliate_referrals( $affiliate_id, null, null, AFFILIATES_REFERRAL_STATUS_CLOSED );
			$referrals_pending  += affiliates_get_affiliate_referrals( $affiliate_id, null, null, AFFILIATES_REFERRAL_STATUS_PENDING );
			$referrals_rejected += affiliates_get_affiliate_referrals( $affiliate_id, null, null, AFFILIATES_REFERRAL_STATUS_REJECTED );
		}
		
		$accepted_icon = "<img class='icon' alt='" . __( 'Accepted', AFFILIATES_PLUGIN_DOMAIN) . "' src='" . AFFILIATES_PLUGIN_URL . "images/accepted.png'/>";
		$closed_icon = "<img class='icon' alt='" . __( 'Closed', AFFILIATES_PLUGIN_DOMAIN) . "' src='" . AFFILIATES_PLUGIN_URL . "images/closed.png'/>";
		$pending_icon = "<img class='icon' alt='" . __( 'Pending', AFFILIATES_PLUGIN_DOMAIN) . "' src='" . AFFILIATES_PLUGIN_URL . "images/pending.png'/>";
		$rejected_icon = "<img class='icon' alt='" . __( 'Rejected', AFFILIATES_PLUGIN_DOMAIN) . "' src='" . AFFILIATES_PLUGIN_URL . "images/rejected.png'/>";
		
		echo '<div class="manage" style="margin-right:1em">';
		echo '<p>';
		echo '<strong>' . $title . '</strong>&nbsp;' . $info;
		echo '</p>';
		echo '<ul>';
		echo '<li>' . __( '<strong>Referrals:</strong>', AFFILIATES_PLUGIN_DOMAIN ) . '</li>';
		echo '<li><ul>';
		echo '<li>' . $accepted_icon . '&nbsp;' . sprintf( __( '%10d Accepted', AFFILIATES_PLUGIN_DOMAIN ), $referrals_accepted ) . '</li>';
		echo '<li>' . $closed_icon . '&nbsp;' . sprintf( __( '%10d Closed', AFFILIATES_PLUGIN_DOMAIN ), $referrals_closed ) . '</li>';
		echo '<li>' . $pending_icon . '&nbsp;' . sprintf( __( '%10d Pending', AFFILIATES_PLUGIN_DOMAIN ), $referrals_pending ) . '</li>';
		echo '<li>' . $rejected_icon . '&nbsp;' . sprintf( __( '%10d Rejected', AFFILIATES_PLUGIN_DOMAIN ), $referrals_rejected ) . '</li>';
		echo '</li></ul>';
		echo '<li>' . sprintf( __( '%10d Hits', AFFILIATES_PLUGIN_DOMAIN ), $hits ) . '</li>';
		echo '<li>' . sprintf( __( '%10d Visits', AFFILIATES_PLUGIN_DOMAIN ), $visits ) . '</li>';
		echo '</ul>';
		echo '</div>';
		
	}
	affiliates_footer();
}
?>