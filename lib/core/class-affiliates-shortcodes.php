<?php
/**
* class-affiliates-shortcodes.php
*
* Copyright (c) 2010-2012 "kento" Karim Rahimpur www.itthinx.com
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
* @since affiliates 1.3.0
*/
class Affiliates_Shortcodes {
	
	// var $url_options = array();
	
	public static function init() {
		add_shortcode( 'affiliates_is_affiliate', array( __CLASS__, 'affiliates_is_affiliate' ) );
		add_shortcode( 'affiliates_is_not_affiliate', array( __CLASS__, 'affiliates_is_not_affiliate' ) );
		add_shortcode( 'affiliates_referrals', array( __CLASS__, 'affiliates_referrals' ) );
		add_shortcode( 'affiliates_url', array( __CLASS__, 'affiliates_url' ) );
		add_shortcode( 'affiliates_login_redirect', array( __CLASS__, 'affiliates_login_redirect' ) );
		add_shortcode( 'affiliates_logout', array( __CLASS__, 'affiliates_logout' ) );
	}
	
	/**
	 * Affiliate content shortcode.
	 * Renders the content if the current user is an affiliate.
	 *
	 * @param array $atts attributes (none used)
	 * @param string $content this is rendered for affiliates
	 */
	public static function affiliates_is_affiliate( $atts, $content = null ) {
		
		remove_shortcode( 'affiliates_is_affiliate' );
		$content = do_shortcode( $content );
		add_shortcode( 'affiliates_is_affiliate', array( __CLASS__, 'affiliates_is_affiliate' ) );
		
		$output = "";
		if ( affiliates_user_is_affiliate( get_current_user_id() ) ) {
			$output .= $content;
		}
		return $output;
	}
	
	/**
	 * Non-Affiliate content shortcode.
	 *
	 * @param array $atts attributes
	 * @param string $content this is rendered for non-affiliates
	 */
	public static function affiliates_is_not_affiliate( $atts, $content = null ) {
		
		remove_shortcode( 'affiliates_is_not_affiliate' );
		$content = do_shortcode( $content );
		add_shortcode( 'affiliates_is_not_affiliate', array( __CLASS__, 'affiliates_is_not_affiliate' ) );
		
		$output = "";
		if ( !affiliates_user_is_affiliate( get_current_user_id() ) ) {
			$output .= $content;
		}
		return $output;
	}
	
	/**
	 * Referrals shortcode - renders referral information.
	 *
	 * @param array $atts attributes
	 * @param string $content not used
	 */
	public static function affiliates_referrals( $atts, $content = null ) {
		global $wpdb;
		
		remove_shortcode( 'affiliates_referrals' );
		$content = do_shortcode( $content );
		add_shortcode( 'affiliates_referrals', array( __CLASS__, 'affiliates_referrals' ) );
		
		$output = "";
		$options = shortcode_atts(
			array(
				'status'   => null,
				'from'     => null,
				'until'    => null,
				'show'     => 'count',
				'currency' => null
			),
			$atts
		);
		extract( $options );
		if ( $from !== null ) {
			$from = date( 'Y-m-d', strtotime( $from ) );
		}
		if ( $until !== null ) {
			$until = date( 'Y-m-d', strtotime( $until ) );
		}
		$user_id = get_current_user_id();
		if ( $user_id && affiliates_user_is_affiliate( $user_id ) ) {
			$affiliates_table = _affiliates_get_tablename( 'affiliates' );
			$affiliates_users_table = _affiliates_get_tablename( 'affiliates_users' );
			if ( $affiliate_id = $wpdb->get_var( $wpdb->prepare(
				"SELECT $affiliates_users_table.affiliate_id FROM $affiliates_users_table LEFT JOIN $affiliates_table ON $affiliates_users_table.affiliate_id = $affiliates_table.affiliate_id WHERE $affiliates_users_table.user_id = %d AND $affiliates_table.status = 'active'",
				intval( $user_id )
			))) {
				switch ( $show ) {
					case 'count' :
						switch ( $status ) {
							case null :
							case AFFILIATES_REFERRAL_STATUS_ACCEPTED :
							case AFFILIATES_REFERRAL_STATUS_CLOSED :
							case AFFILIATES_REFERRAL_STATUS_PENDING :
							case AFFILIATES_REFERRAL_STATUS_REJECTED :
								$referrals = affiliates_get_affiliate_referrals( $affiliate_id, $from, $until, $status );
								break;
							default :
								$referrals = "";
						}
						$output .= $referrals;
						break;
					case 'total' :
						if ( $totals = self::get_total( $affiliate_id, $from, $until, $status ) ) {
							$output .= '<ul>';
							foreach ( $totals as $currency_id => $total ) {
								$output .= '<li>';
								$output .= $currency_id;
								$output .= '&nbsp;';
								$output .= $total;
								$output .= '</li>';
							}
							$output .= '</ul>';
						}
						break;
				}
			}
		}
		return $output;
	}
	
	/**
	 * Retrieve totals for an affiliate.
	 * 
	 * @param int $affiliate_id
	 * @param string $from_date
	 * @param string $thru_date
	 * @param string $status
	 * @return array of totals indexed by currency_id or false on error
	 */
	private static function get_total( $affiliate_id, $from_date = null , $thru_date = null, $status = null ) {
		global $wpdb;
		$referrals_table = _affiliates_get_tablename( 'referrals' );
		$where = " WHERE affiliate_id = %d";
		$values = array( $affiliate_id );
		if ( $from_date ) {
			$from_date = date( 'Y-m-d', strtotime( $from_date ) );
		}
		if ( $thru_date ) {
			$thru_date = date( 'Y-m-d', strtotime( $thru_date ) + 24*3600 );
		}
		if ( $from_date && $thru_date ) {
			$where .= " AND datetime >= %s AND datetime < %s ";
			$values[] = $from_date;
			$values[] = $thru_date;
		} else if ( $from_date ) {
			$where .= " AND datetime >= %s ";
			$values[] = $from_date;
		} else if ( $thru_date ) {
			$where .= " AND datetime < %s ";
			$values[] = $thru_date;
		}
		if ( !empty( $status ) ) {
			$where .= " AND status = %s ";
			$values[] = $status;
		} else {
			$where .= " AND status IN ( %s, %s ) ";
			$values[] = AFFILIATES_REFERRAL_STATUS_ACCEPTED;
			$values[] = AFFILIATES_REFERRAL_STATUS_CLOSED;
		}
		$totals = $wpdb->get_results( $wpdb->prepare(
			"SELECT SUM(amount) total, currency_id FROM $referrals_table
			$where
			GROUP BY currency_id
			",
			$values
		) );
		if ( $totals ) {
			$result = array();
			foreach( $totals as $total ) {
				if ( ( $total->currency_id !== null ) && ( $total->total !== null ) ) {
					$result[$total->currency_id] = $total->total;
				}
			}
			return $result;
		} else {
			return false;
		}
	}
	
	/**
	 * URL shortcode - renders the affiliate url.
	 *
	 * @param array $atts attributes
	 * @param string $content (is not used)
	 */
	public static function affiliates_url( $atts, $content = null ) {
		global $wpdb;
		
		remove_shortcode( 'affiliates_url' );
		$content = do_shortcode( $content );
		add_shortcode( 'affiliates_url', array( __CLASS__, 'affiliates_url' ) );
		
		$output = "";
		$user_id = get_current_user_id();
		if ( $user_id && affiliates_user_is_affiliate( $user_id ) ) {
			$affiliates_table = _affiliates_get_tablename( 'affiliates' );
			$affiliates_users_table = _affiliates_get_tablename( 'affiliates_users' );
			if ( $affiliate_id = $wpdb->get_var( $wpdb->prepare(
				"SELECT $affiliates_users_table.affiliate_id FROM $affiliates_users_table LEFT JOIN $affiliates_table ON $affiliates_users_table.affiliate_id = $affiliates_table.affiliate_id WHERE $affiliates_users_table.user_id = %d AND $affiliates_table.status = 'active'",
				intval( $user_id )
			))) {
				$encoded_affiliate_id = affiliates_encode_affiliate_id( $affiliate_id );
				if ( strlen( $content ) == 0 ) {
					$base_url = get_bloginfo( 'url' );
				} else {
					$base_url = $content;
				}
				$separator = '?';
				$url_query = parse_url( $base_url, PHP_URL_QUERY );
				if ( !empty( $url_query ) ) {
					$separator = '&';
				}
				$output .= $base_url . $separator . 'affiliates=' . $encoded_affiliate_id;
			}
		}
		return $output;
	}
	
	/**
	 * Renders a login form that can redirect to a url or the current page.
	 * 
	 * @param array $atts
	 * @param string $content
	 * @return string rendered form
	 */
	function affiliates_login_redirect( $atts, $content = null ) {
		extract( shortcode_atts( array( 'redirect_url' => '' ), $atts ) );
		$form = '';
		if ( !is_user_logged_in() ) {
			if ( empty( $redirect_url ) ) {
				$redirect_url = get_permalink();
			}
			$form = wp_login_form( array( 'echo' => false, 'redirect' => $redirect_url ) );
		}
		return $form;
	}
	
	/**
	 * Renders a link to log out.
	 * 
	 * @param array $atts
	 * @param string $content not used
	 * @return string rendered logout link or empty if not logged in
	 */
	function affiliates_logout( $atts, $content = null ) {
		if ( is_user_logged_in() ) {
			return '<a href="' . esc_url( wp_logout_url() ) .'">' . __( 'Log out' ) . '</a>';
		} else {
			return '';
		}
	}
}
Affiliates_Shortcodes::init();
