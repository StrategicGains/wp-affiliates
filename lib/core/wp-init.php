<?php
/**
 * wp-init.php
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
 * @since affiliates 1.1.2
 */

global $affiliates_options, $affiliates_version, $affiliates_admin_messages;

if ( !isset( $affiliates_admin_messages ) ) {
	$affiliates_admin_messages = array();
}

if ( !isset( $affiliates_version ) ) {
	$affiliates_version = AFFILIATES_CORE_VERSION;
}

// options
include_once( AFFILIATES_CORE_LIB . '/class-affiliates-options.php' );
if ( $affiliates_options == null ) {
	$affiliates_options = new Affiliates_Options();
}

// utilities
include_once( AFFILIATES_CORE_LIB . '/class-affiliates-utility.php' );

// forms, shortcodes, widgets
include_once( AFFILIATES_CORE_LIB . '/class-affiliates-contact.php' );
include_once( AFFILIATES_CORE_LIB . '/class-affiliates-registration.php' );
include_once( AFFILIATES_CORE_LIB . '/class-affiliates-registration-widget.php' );
include_once( AFFILIATES_CORE_LIB . '/class-affiliates-shortcodes.php' );

add_action( 'widgets_init', 'affiliates_widgets_init' );

/**
 * Register widgets
 */
function affiliates_widgets_init() {
	register_widget( 'Affiliates_Contact' );
	register_widget( 'Affiliates_Registration_Widget' );
}

add_action( 'admin_init', 'affiliates_admin_init' );

/**
 * Hook into admin_init. Used to get our styles at the right place.
 * @see affiliates_admin_menu()
 * @link http://codex.wordpress.org/Function_Reference/wp_enqueue_script#Load_scripts_only_on_plugin_pages
 */
function affiliates_admin_init() {
	global $affiliates_version;
	wp_register_style( 'smoothness', AFFILIATES_PLUGIN_URL . 'css/smoothness/jquery-ui-1.8.16.custom.css', array(), $affiliates_version );
	wp_register_style( 'affiliates_admin', AFFILIATES_PLUGIN_URL . 'css/affiliates_admin.css', array(), $affiliates_version );
	
	wp_register_style( 'visualize', AFFILIATES_PLUGIN_URL . 'css/graph/visualize/visualize.css', array(), $affiliates_version );
	wp_register_style( 'visualize-affiliates-admin', AFFILIATES_PLUGIN_URL . 'css/graph/visualize-affiliates-admin.css', array(), $affiliates_version );
}

/**
 * Load styles.
 * @see affiliates_admin_menu()
 */
function affiliates_admin_print_styles() {
	wp_enqueue_style( 'smoothness' );
	wp_enqueue_style( 'affiliates_admin' );
	
	wp_enqueue_style( 'visualize' );
	wp_enqueue_style( 'visualize-affiliates-admin' );
}
	
/**
 * Load scripts.
 */
function affiliates_admin_print_scripts() {
	global $post_type, $affiliates_version;
	
	// load datepicker scripts for all
	wp_enqueue_script( 'datepicker', AFFILIATES_PLUGIN_URL . 'js/jquery.ui.datepicker.min.js', array( 'jquery', 'jquery-ui-core' ), $affiliates_version );
	wp_enqueue_script( 'datepickers', AFFILIATES_PLUGIN_URL . 'js/datepickers.js', array( 'jquery', 'jquery-ui-core', 'datepicker' ), $affiliates_version );
	// corners
	wp_enqueue_script( 'jquery-corner', AFFILIATES_PLUGIN_URL . 'js/jquery.corner.js', array( 'jquery', 'jquery-ui-core' ), $affiliates_version );
	// add more dates used for trips and events
	wp_enqueue_script( 'affiliates', AFFILIATES_PLUGIN_URL . 'js/affiliates.js', array( 'jquery', 'jquery-ui-core', 'jquery-ui-button', 'jquery-corner' ), $affiliates_version );
	// and thus are the translations of the buttons used
	
	wp_enqueue_script( 'excanvas', AFFILIATES_PLUGIN_URL . 'js/graph/visualize/excanvas.js', array(), $affiliates_version );
	wp_enqueue_script( 'visualize', AFFILIATES_PLUGIN_URL . 'js/graph/visualize/visualize.jQuery.js', array( 'jquery' ), $affiliates_version );
	wp_enqueue_script( 'visualize-affiliates-admin', AFFILIATES_PLUGIN_URL . 'js/graph/affiliates-admin.js', array( 'jquery', 'visualize' ), $affiliates_version );
	
	$GLOBALS['wp_scripts']->add_data( 'excanvas', 'conditional', 'lt IE 9' );
	
//	echo '
//		<script type="text/javascript">
//			var fooText = "' . __( 'Foo', AFFILIATES_PLUGIN_DOMAIN ) . '";
//		</script>
//		';
}

// ---

register_activation_hook( AFFILIATES_FILE, 'affiliates_activate' );

//global $affiliates_version, $affiliates_admin_messages;

add_action( 'init', 'affiliates_version_check' );
function affiliates_version_check() {
	global $affiliates_version, $affiliates_admin_messages;
	$previous_version = get_option( 'affiliates_plugin_version', null );
	$affiliates_version = AFFILIATES_CORE_VERSION;
	if ( strcmp( $previous_version, $affiliates_version ) < 0 ) {
		if ( affiliates_update( $previous_version ) ) {
			update_option( 'affiliates_plugin_version', $affiliates_version );
		} else {
			$affiliates_admin_messages[] = '<div class="error">Updating the Affiliates core FAILED.</div>';
		}
	}
}

function affiliates_admin_notices() {
	global $affiliates_admin_messages;
	if ( !empty( $affiliates_admin_messages ) ) {
		foreach ( $affiliates_admin_messages as $msg ) {
			echo $msg;
		}
	}
}

add_action( 'admin_notices', 'affiliates_admin_notices' );

/**
 * Tasks performed upon plugin activation.
 * - create tables
 * - insert default affiliate
 */
function affiliates_activate() {
	global $wpdb, $wp_roles;

	_affiliates_set_default_capabilities();

	$affiliates_table = _affiliates_get_tablename('affiliates');
	if ( $wpdb->get_var( "SHOW TABLES LIKE '" . $affiliates_table . "'" ) != $affiliates_table ) {
		$queries[] = "CREATE TABLE " . $affiliates_table . "(
				affiliate_id bigint(20) unsigned NOT NULL auto_increment,
				name         varchar(100) NOT NULL,
				email        varchar(512) default NULL,
				from_date    date NOT NULL,
				thru_date    date default NULL,
				status       varchar(10) NOT NULL DEFAULT 'active',
				type         varchar(10) NULL,
				PRIMARY KEY  (affiliate_id),
				INDEX        affiliates_afts (affiliate_id, from_date, thru_date, status),
				INDEX        affiliates_sft (status, from_date, thru_date)
			);";
			
		// email @see http://tools.ietf.org/html/rfc5321
		// 2.3.11. Mailbox and Address
		// ... The standard mailbox naming convention is defined to
		// be "local-part@domain"; ...
		// 4.1.2. Command Argument Syntax
		// ...
		// Mailbox        = Local-part "@" ( Domain / address-literal )
		// ...
		// 4.5.3. Sizes and Timeouts
		// 4.5.3.1.1. Local-part
		// The maximum total length of a user name or other local-part is 64 octets.
		// 4.5.3.1.2. Domain
		// The maximum total length of a domain name or number is 255 octets.
		// 4.5.3.1.3. Path
		// The maximum total length of a reverse-path or forward-path is 256
		// octets (including the punctuation and element separators).
		// So the maximum size of an email address is ... ?
		// 64 + 1 + 255 = 320 octets
		// Then again, people change their minds ... we'll assume 512 as sufficient.
		// Note: WP's user.user_email is varchar(100) @see wp-admin/includes/schema.php
			
		// IPv6 addr
		// FFFF:FFFF:FFFF:FFFF:FFFF:FFFF:FFFF:FFFF = 340282366920938463463374607431768211455
		// Note that ipv6 is not part of the PK but can be handled using
		// the lower bits of the ipv6 address to fill in ip and using the
		// complete IPv6 address on ipv6.
		// Note also, that currently Affiliates does NOT use ipv6.
			
		$referrals_table = _affiliates_get_tablename( 'referrals' );
		$queries[] = "CREATE TABLE " . $referrals_table . "(
				referral_id  BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				affiliate_id bigint(20) unsigned NOT NULL default '0',
				post_id      bigint(20) unsigned NOT NULL default '0',
				datetime     datetime NOT NULL,
				description  varchar(5000),
				ip           int(10) unsigned default NULL,
				ipv6         decimal(39,0) unsigned default NULL,
				user_id      bigint(20) unsigned default NULL,
				amount       decimal(18,2) default NULL,
				currency_id  char(3) default NULL,
				data         longtext default NULL,
				status       varchar(10) NOT NULL DEFAULT '" . AFFILIATES_REFERRAL_STATUS_ACCEPTED . "',
				type         varchar(10) NULL,
				reference    VARCHAR(100) DEFAULT NULL,
				PRIMARY KEY  (referral_id),
				INDEX        aff_referrals_apd (affiliate_id, post_id, datetime),
				INDEX        aff_referrals_da (datetime, affiliate_id),
				INDEX        aff_referrals_sda (status, datetime, affiliate_id),
				INDEX        aff_referrals_tda (type, datetime, affiliate_id),
				INDEX        aff_referrals_ref (reference(20))
			);";
		// @see http://bugs.mysql.com/bug.php?id=27645 as of now (2011-03-19) NOW() can not be specified as the default value for a datetime column
			
		// A note about whether or not to record information about
		// http_user_agent here: No, we don't do that!
		// Our business is to record hits on affiliate links, not
		// to gather statistical data about user agents. For our
		// purpose, it does not add value to record that and if
		// one wishes to do so, there are better solutions anyhow.
		// IMPORTANT:
		// datetime -- records the datetime with respect to the server's timezone
		// date and time are are also with respect to the server's timezone
		// date -- we do NOT use this to display information to the user
		// time -- same applies to this here
		// date and time (and currently only date really) are used for better
		// performance on certain queries.
		// We DO use the datetime (adjusted to the user's timezone using
		// DateHelper's s2u() function to display the date and time
		// in accordance to the user's date and time.
		$hits_table = _affiliates_get_tablename( 'hits' );
		$queries[] = "CREATE TABLE " . $hits_table . "(
				affiliate_id    BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
				date            DATE NOT NULL,
				time            TIME NOT NULL,
				datetime        DATETIME NOT NULL,
				ip              INT(10) UNSIGNED DEFAULT NULL,
				ipv6            DECIMAL(39,0) UNSIGNED DEFAULT NULL,
				is_robot        TINYINT(1) DEFAULT 0,
				user_id         BIGINT(20) UNSIGNED DEFAULT NULL,
				count           INT DEFAULT 1,
				type            VARCHAR(10) DEFAULT NULL,
				PRIMARY KEY     (affiliate_id, date, time, ip),
				INDEX           aff_hits_ddt (date, datetime),
				INDEX           aff_hits_dtd (datetime, date)
			);";
			
		$robots_table = _affiliates_get_tablename( 'robots' );
		$queries[] = "CREATE TABLE " . $robots_table . "(
				robot_id    bigint(20) unsigned NOT NULL auto_increment,
				name        varchar(100) NOT NULL,
				PRIMARY KEY (robot_id),
				INDEX       aff_robots_n (name)
			);";
			
		$affiliates_users_table = _affiliates_get_tablename( 'affiliates_users' );
		$queries[] = "CREATE TABLE " . $affiliates_users_table . "(
				affiliate_id bigint(20) unsigned NOT NULL,
				user_id      bigint(20) unsigned NOT NULL,
				PRIMARY KEY (affiliate_id, user_id)
			);";
			
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $queries );
	}
	$today = date( 'Y-m-d', time() );
	$direct = intval( $wpdb->get_var( "SELECT COUNT(affiliate_id) FROM $affiliates_table WHERE type = '" . AFFILIATES_DIRECT_TYPE . "';" ) );
	if ( $direct <= 0 ) {
		$wpdb->query( "INSERT INTO $affiliates_table (name, from_date, type) VALUES ('" . AFFILIATES_DIRECT_NAME . "','$today','" . AFFILIATES_DIRECT_TYPE . "');" );
	}
}

/**
 * Determines the default capabilities for the administrator role.
 * In lack of an administrator role, these capabilities are assigned
 * to any role that can manage_options.
 * This is also used to assure a minimum set of capabilities is
 * assigned to an appropriate role, so that it's not possible
 * to lock yourself out (although deactivating and then activating
 * the plugin would have the same effect but with the danger of
 * deleting all plugin data).
 * @param boolean $activate defaults to true, when this function is called upon plugin activation
 * @access private
 */
function _affiliates_set_default_capabilities() {
	global $wp_roles;
	// The administrator role should be there, if it's not, assign privileges to
	// any role that can manage_options:
	if ( $administrator_role = $wp_roles->get_role( 'administrator' ) ) {
		$administrator_role->add_cap( AFFILIATES_ACCESS_AFFILIATES );
		$administrator_role->add_cap( AFFILIATES_ADMINISTER_AFFILIATES );
		$administrator_role->add_cap( AFFILIATES_ADMINISTER_OPTIONS );
	} else {
		foreach ( $wp_roles->role_objects as $role ) {
			if ($role->has_cap( 'manage_options' ) ) {
				$role->add_cap( AFFILIATES_ACCESS_AFFILIATES );
				$role->add_cap( AFFILIATES_ADMINISTER_AFFILIATES );
				$role->add_cap( AFFILIATES_ADMINISTER_OPTIONS );
			}
		}
	}
}

/**
 * There must be at least one role with the minimum set of capabilities
 * to access and manage the Affiliates plugin's options.
 * If this condition is not met, the minimum set of capabilities is
 * reestablished.
 */
function _affiliates_assure_capabilities() {
	global $wp_roles;
	$complies = false;
	$roles = $wp_roles->role_objects;
	foreach( $roles as $role ) {
		if ( $role->has_cap( AFFILIATES_ACCESS_AFFILIATES ) && ( $role->has_cap( AFFILIATES_ADMINISTER_OPTIONS ) ) ) {
			$complies = true;
			break;
		}
	}
	if ( !$complies ) {
		_affiliates_set_default_capabilities();
	}
}

/**
 * D'oh :/
 * Yes, update hooks are awesome ...
 */
function affiliates_update( $previous_version ) {
	global $wpdb, $affiliates_admin_messages;
	$result = true;
	$queries = array();
	switch ( $previous_version ) {
		case '1.1.0' :
		case '1.1.1' :
			// add new fields and index to referrals
			$referrals_table = _affiliates_get_tablename( 'referrals' );
			$type_row = $wpdb->get_row( "SHOW COLUMNS FROM " . $referrals_table . " LIKE 'type'" );
			if ( empty( $type_row )  ) {
				$queries[] = "ALTER TABLE " . $referrals_table . "
					ADD COLUMN type         varchar(10) NULL,
					ADD INDEX aff_referrals_tda (type, datetime, affiliate_id)
					;";
			}
			break;

			default : // 1.0.0 1.0.1 1.0.2 1.0.3 1.0.4
				$affiliates_users_table = _affiliates_get_tablename( 'affiliates_users' );
				if ( $wpdb->get_var( "SHOW TABLES LIKE '" . $affiliates_users_table . "'" ) != $affiliates_users_table ) {
					$queries[] = "CREATE TABLE " . $affiliates_users_table . " (
							affiliate_id bigint(20) unsigned NOT NULL,
							user_id      bigint(20) unsigned NOT NULL,
							PRIMARY KEY (affiliate_id, user_id)
						);";
				}

				// add new fields and index to referrals
				$referrals_table = _affiliates_get_tablename( 'referrals' );
				$amount_row = $wpdb->get_row( "SHOW COLUMNS FROM " . $referrals_table . " LIKE 'amount'" );
				if ( empty( $amount_row )  ) {
					$queries[] = "ALTER TABLE " . $referrals_table . "
					ADD COLUMN amount       decimal(18,2) default NULL,
					ADD COLUMN currency_id  char(3) default NULL,
					ADD COLUMN status       varchar(10) NOT NULL DEFAULT '" . AFFILIATES_REFERRAL_STATUS_ACCEPTED . "',
					ADD COLUMN type         varchar(10) NULL,
					ADD INDEX aff_referrals_da (datetime, affiliate_id),
					ADD INDEX aff_referrals_sda (status, datetime, affiliate_id),
					ADD INDEX aff_referrals_tda (type, datetime, affiliate_id)
					;";
					$queries[] = "UPDATE " . $referrals_table . " SET status = '" . AFFILIATES_REFERRAL_STATUS_ACCEPTED . "' WHERE status IS NULL;";
				}
				break;
	} // switch
	// add new PK & fields if it's not a new installation
	if ( !empty( $previous_version ) && strcmp( $previous_version, "1.2.0" ) < 0 ) {
		$referrals_table = _affiliates_get_tablename( 'referrals' );
		$queries[] = "ALTER TABLE " . $referrals_table . "
		ADD COLUMN referral_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		ADD COLUMN reference VARCHAR(100) DEFAULT NULL,
		DROP PRIMARY KEY,
		ADD PRIMARY KEY (referral_id),
		ADD INDEX aff_referrals_ref (reference(20));";
	}
	//		dbDelta won't handle ALTER ...
	//		if ( !empty( $queries ) ) {
	//			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	//			dbDelta( $queries );
	//		}
	foreach ( $queries as $query ) {
		if ( $wpdb->query( $query ) === false ) {
			// fail but still try to go on
			$result = false;
		}
	}
	return $result;
}

register_deactivation_hook( AFFILIATES_FILE, 'affiliates_deactivate' );

/**
 * Drop tables and clear data if the plugin is deactivated.
 * This will happen only if the user chooses to delete data upon deactivation.
 */
function affiliates_deactivate() {
	global $wpdb, $affiliates_options, $wp_roles;

	$delete_data = get_option( 'aff_delete_data', false );
	if ( $delete_data ) {
		foreach ( $wp_roles->role_objects as $role ) {
	 		$role->remove_cap( AFFILIATES_ACCESS_AFFILIATES );
			 $role->remove_cap( AFFILIATES_ADMINISTER_AFFILIATES );
			$role->remove_cap( AFFILIATES_ADMINISTER_OPTIONS );
		}
		$wpdb->query('DROP TABLE IF EXISTS ' . _affiliates_get_tablename( 'referrals' ) );
		$wpdb->query('DROP TABLE IF EXISTS ' . _affiliates_get_tablename( 'hits' ) );
		$wpdb->query('DROP TABLE IF EXISTS ' . _affiliates_get_tablename( 'affiliates' ) );
		$wpdb->query('DROP TABLE IF EXISTS ' . _affiliates_get_tablename( 'robots' ) );
		$wpdb->query('DROP TABLE IF EXISTS ' . _affiliates_get_tablename( 'affiliates_users' ) );
		$affiliates_options->flush_options();
		delete_option( 'affiliates_plugin_version' );
		delete_option( 'aff_cookie_timeout_days' );
		delete_option( 'aff_use_direct' );
		delete_option( 'aff_id_encoding' );
		delete_option( 'aff_default_referral_status' );
		delete_option( 'aff_delete_data' );
	}
}

add_action( 'init', 'affiliates_init' );
	
/**
 * Initialize.
 * Loads the plugin's translations.
 */
function affiliates_init() {
	load_plugin_textdomain( AFFILIATES_PLUGIN_DOMAIN, null, 'affiliates/lib/core/languages' );
}

add_filter( 'query_vars', 'affiliates_query_vars' );

/**
 * Register the affiliate query variable.
 * In addition to existing query variables, we'll use affiliates in the URL to track referrals.
 * @see affiliates_rewrite_rules_array() for the rewrite rule that matches the affiliates id
 */
function affiliates_query_vars( $query_vars ) {
	$query_vars[] = "affiliates";
	return $query_vars;
}

add_filter( 'rewrite_rules_array', 'affiliates_rewrite_rules_array' );

/**
 * BEWARE ! of the order in which rules are applied.
 * In our case, we need our rule to be placed *** in front of $rules ***.
 * @param array $rules current rules
 */
function affiliates_rewrite_rules_array( $rules ) {
	$rule = array(
		// we could be more restrictive (if we wanted to)
		//'affiliates/([0-9a-zA-Z]+)/?$' => 'index.php?affiliates=$matches[1]'
		//'affiliates/([^/]+)/?$' => 'index.php?affiliates=$matches[1]'
 		AFFILIATES_REGEX_PATTERN => 'index.php?affiliates=$matches[1]'
	);
	$rules = $rule + $rules ; // !
	return $rules;
}

add_filter( 'wp_loaded', 'affiliates_wp_loaded' );

/** 
 * Flushes the rewrite rules.
 */
function affiliates_wp_loaded() {
	global $wp_rewrite;
	$wp_rewrite->flush_rules();
}

add_filter( 'parse_request', 'affiliates_parse_request' );
	
/**
 * Looks in the query variables and sets a cookie with the affiliate id.
 * Hook into parse_request.
 * @param WP $wp the WordPress environment
 * @link http://es.php.net/manual/en/function.setcookie.php
 */
function affiliates_parse_request( $wp ) {

	global $wpdb, $affiliates_options;

	$affiliate_id = isset( $wp->query_vars['affiliates'] ) ? affiliates_check_affiliate_id_encoded( trim( $wp->query_vars['affiliates'] ) ) : null;

	if ( $affiliate_id ) {
		$encoded_id = affiliates_encode_affiliate_id( $affiliate_id );
		setcookie(
			AFFILIATES_COOKIE_NAME,
			$encoded_id,
			time() + AFFILIATES_COOKIE_TIMEOUT_BASE * get_option( 'aff_cookie_timeout_days', AFFILIATES_COOKIE_TIMEOUT_DAYS ),
			SITECOOKIEPATH,
			COOKIE_DOMAIN
		);
		affiliates_record_hit( $affiliate_id );

		// we could use this to avoid ending up on the blog listing page
		//unset( $wp->query_vars['affiliates'] );
		// but we use a redirect so that we end up on the desired url without the affiliate id dangling on the url
		$current_url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		$current_url = remove_query_arg( 'affiliates', $current_url );
		$current_url = ereg_replace(AFFILIATES_REGEX_PATTERN, '', $current_url);
		wp_redirect($current_url);
		exit; // "wp_redirect() does not exit automatically and should almost always be followed by exit." @see http://codex.wordpress.org/Function_Reference/wp_redirect  
	}
}

/**
 * Record a hit on an affiliate link.
 *
 * @param int $affiliate_id the affiliate's id
 * @param int $now UNIX timestamp to use, if null the current time is used
 * @param string $type the type of hit to record
 */
function affiliates_record_hit( $affiliate_id, $now = null, $type = null ) {
	global $wpdb;
	// add a hit
	// @todo check/store IPv6 addresses
	$http_user_agent = $_SERVER['HTTP_USER_AGENT'];
	$robots_table    = _affiliates_get_tablename( 'robots' );
	$robots_query    = $wpdb->prepare( "SELECT name FROM $robots_table WHERE %s LIKE concat('%%',name,'%%')", $http_user_agent );
	$got_robots      = $wpdb->get_results( $robots_query );
		
	$table    = _affiliates_get_tablename( 'hits' );
	if ( $now == null ) {
		$now = time();
	}
	$date     = date( 'Y-m-d' , $now );
	$time     = date( 'H:i:s' , $now );
	$datetime = date( 'Y-m-d H:i:s' , $now );

	$columns    = '(affiliate_id, date, time, datetime, type';
	$formats    = '(%d,%s,%s,%s,%s';
	$values     = array( $affiliate_id, $date, $time, $datetime, $type );
	$ip_address = $_SERVER['REMOTE_ADDR'];

	if ( $ip_int = ip2long( $ip_address ) ) {
		$columns .= ',ip';
		$formats .= ',%d';
		$values[] = $ip_int;
	}
	if ( $user_id = get_current_user_id() ) {
		$columns .= ',user_id';
		$formats .= ',%d';
		$values[] = $user_id;
	}
	if ( AFFILIATES_RECORD_ROBOT_HITS ) {
		if ( !empty( $got_robots ) ) {
			$columns .= ',is_robot';
			$formats .= ',%d';
			$values[] = '1';
	}
	}
	$columns .= ')';
	$formats .= ')';
	$query = $wpdb->prepare( "INSERT INTO $table $columns VALUES $formats ON DUPLICATE KEY UPDATE count = count + 1", $values );
	$wpdb->query( $query );
}

/**
 * Suggest to record a referral. This function is used to actually store referrals and associated information.
 * If a valid affiliate is found, the referral is stored and attributed to the affiliate and the affiliate's id is returned.
 * Use the $description to describe the nature of the referral and the associated transaction.
 * Use $data to store transaction data or additional information as needed.
 *
 * $data must either be a string or an array. If given as an array, it is assumed that one provides
 * field data (for example: customer id, transaction id, customer name, ...) and for each field
 * one entry is added to the $data array obeying this format:
 *
 * $data['example'] = array(
 *   'title' => 'Field title',
 *   'domain' => 'Domain'
 *   'value' => 'Field value'
 * );
 *
 * This information is then used to display field data for each referral in the Referrals section.
 *
 * 'title' is the field's title, the title is translated using the 'domain'.
 * 'value' is displayed as the field's value.
 *
 * Example:
 *
 * A customer has submitted an order and a possible referral shall be recorded, including the customer's id
 * and the order id:
 *
 * $data = array(
 *   'customer-id' => array( 'title' => 'Customer Id', 'domain' => 'my_order_plugin', 'value' => $customer_id ),
 *   'order-id'    => array( 'title' => 'Order Id', 'domain' => 'my_order_plugin', 'value' => $order_id )
 * );
 * if ( $affiliate_id = affiliates_suggest_referral( $post_id, "Referral for order number $order_id", $data ) ) {
 *   $affiliate = affiliates_get_affiliate( $affiliate_id );
 *   $affiliate_email = $affiliate['email'];
 *   if ( !empty( $affiliate_email ) ) {
 *   	$message = "Dear Affiliate, an order has been made. You will be credited as soon as payment is received.";
 *   	my_order_plugin_send_an_email( $affiliate_email, $message );
 *   }
 * }
 *
 * @param int $post_id the referral post id; where the transaction or referral originates
 * @param string $description the referral description
 * @param string|array $data additional information that should be stored along with the referral
 * @param string $amount referral amount - if used, a $currency_id must be given
 * @þaram string $currency_id three letter currency code - if used, an $amount must be given
 * @return affiliate id if a valid referral is recorded, otherwise false
 */
function affiliates_suggest_referral( $post_id, $description = '', $data = null, $amount = null, $currency_id = null, $status = null ) {
	global $wpdb, $affiliates_options;
	//$affiliate_id = affiliates_check_affiliate_id_encoded( trim( $_COOKIE[AFFILIATES_COOKIE_NAME] ) );
	$affiliate_id = isset( $_COOKIE[AFFILIATES_COOKIE_NAME] ) ? affiliates_check_affiliate_id_encoded( trim( $_COOKIE[AFFILIATES_COOKIE_NAME] ) ) : false;
	if ( !$affiliate_id ) {
		if ( get_option( 'aff_use_direct', true ) ) {
			// Assume a direct referral without id cookie:
			$affiliate_id = affiliates_get_direct_id();
		}
	}
	if ( $affiliate_id ) {

		$current_user = wp_get_current_user();
		$now = date('Y-m-d H:i:s', time() );
		$table = _affiliates_get_tablename( 'referrals' );
			
		$columns = "(affiliate_id, post_id, datetime, description";
		$formats = "(%d, %d, %s, %s";
		$values = array( $affiliate_id, $post_id, $now, $description );
			
		if ( !empty( $current_user ) ) {
			$columns .= ",user_id ";
			$formats .= ",%d ";
			$values[] = $current_user->ID;
		}

		// add ip
		$ip_address = $_SERVER['REMOTE_ADDR'];
		if ( $ip_int = ip2long( $ip_address ) ) {
			$columns .= ',ip ';
			$formats .= ',%d ';
			$values[] = $ip_int;
		}

		if ( is_array( $data ) && !empty( $data ) ) {
			$columns .= ",data ";
			$formats .= ",%s ";
			$values[] = serialize( $data );
		}

		if ( !empty( $amount ) && !empty( $currency_id ) ) {
			if ( $amount = Affiliates_Utility::verify_referral_amount( $amount ) ) {
				if ( $currency_id =  Affiliates_Utility::verify_currency_id( $currency_id ) ) {
					$columns .= ",amount ";
					$formats .= ",%s ";
					$values[] = $amount;

					$columns .= ",currency_id ";
					$formats .= ",%s ";
					$values[] = $currency_id;
				}
			}
		}
		if ( !empty( $status ) && Affiliates_Utility::verify_referral_status_transition( $status, $status ) ) {
			$columns .= ',status ';
			$formats .= ',%s ';
			$values[] = $status;
		} else {
			$columns .= ',status ';
			$formats .= ',%s ';
			$values[] = get_option( 'aff_default_referral_status', AFFILIATES_REFERRAL_STATUS_ACCEPTED );
		}

		$columns .= ")";
		$formats .= ")";

		// add the referral
		$query = $wpdb->prepare( "INSERT INTO $table $columns VALUES $formats", $values );
		$wpdb->query( $query );
	}
	return $affiliate_id;
}

/**
 * Returns an array of possible id encodings.
 * @return array of possible id encodings, keys: encoding identifier, values: encoding name
 */
function affiliates_get_id_encodings() {
	return array(
		AFFILIATES_NO_ID_ENCODING => __( 'No encoding', AFFILIATES_PLUGIN_DOMAIN ),
		AFFILIATES_MD5_ID_ENCODING => __( 'MD5', AFFILIATES_PLUGIN_DOMAIN )
	);
}

/**
 * Returns an encoded affiliate id.
 * If AFFILIATES_NO_ID_ENCODING is in effect, the $affiliate_id is returned as-is.
 * @param string|int $affiliate_id the affiliate id to encode
 * @return encoded affiliate id
 */
function affiliates_encode_affiliate_id( $affiliate_id ) {
	global $affiliates_options;
	$encoded_id = null;

	$id_encoding = get_option( 'aff_id_encoding', AFFILIATES_NO_ID_ENCODING );
	switch ( $id_encoding ) {
		case AFFILIATES_MD5_ID_ENCODING :
			$encoded_id = md5( $affiliate_id );
			break;
		default:
			$encoded_id = $affiliate_id;
	}
	return $encoded_id;
}

/**
 * Checks if an affiliate id is from a currently valid affiliate.
 * @param string $affiliate_id the affiliate id
 * @return returns the affiliate id if valid, otherwise FALSE
 */
function affiliates_check_affiliate_id_encoded( $affiliate_id ) {

	global $wpdb, $affiliates_options;

	$id_encoding = get_option( 'aff_id_encoding', AFFILIATES_NO_ID_ENCODING );
	switch( $id_encoding ) {
		case AFFILIATES_MD5_ID_ENCODING :
			$result = affiliates_check_affiliate_id_md5( $affiliate_id );
			break;
		default :
			$result = affiliates_check_affiliate_id( $affiliate_id );
	}
	return $result;
}

/**
 * Checks if an affiliate id is from a currently valid affiliate.
 * @param string $affiliate_id the affiliate id
 * @return returns the affiliate id if valid, otherwise FALSE
 */
function affiliates_check_affiliate_id( $affiliate_id ) {

	global $wpdb;
	$result = FALSE;
	$today = date( 'Y-m-d', time() );
	$table = _affiliates_get_tablename( 'affiliates' );
	$query = $wpdb->prepare( "SELECT * FROM $table WHERE affiliate_id = %d AND from_date <= %s AND ( thru_date IS NULL OR thru_date >= %s ) AND status = 'active'", intval( $affiliate_id ), $today, $today );
	$affiliate = $wpdb->get_row( $query, OBJECT );
	if ( !empty( $affiliate ) ) {
		$result = $affiliate->affiliate_id;
	}
	return $result;
}

/**
 * Checks if an md5-encoded affiliate id is from a currently valid affiliate.
 * @param string $affiliate_id_md5 the md5-encoded affiliate id
 * @return returns the (unencoded) affiliate id if valid, otherwise FALSE
 */
function affiliates_check_affiliate_id_md5( $affiliate_id_md5 ) {

	global $wpdb;
	$result = FALSE;
	$today = date( 'Y-m-d', time() );
	$table = _affiliates_get_tablename( 'affiliates' );
	$query = $wpdb->prepare( "SELECT * FROM (SELECT *, md5(affiliate_id) as affiliate_id_md5 FROM $table) md5d WHERE affiliate_id_md5 = %s AND from_date <= %s AND ( thru_date IS NULL OR thru_date >= %s ) AND status = 'active'", $affiliate_id_md5, $today, $today );
	$affiliate = $wpdb->get_row( $query, OBJECT );
	if ( !empty( $affiliate ) ) {
		$result = $affiliate->affiliate_id;
	}
	return $result;
}

/**
 * Returns the first id of an affiliate of type AFFILIATES_DIRECT_TYPE.
 * @return returns the affiliate id (if there is at least one of type AFFILIATES_DIRECT_TYPE), otherwise FALSE
 */
function affiliates_get_direct_id() {
	global $wpdb;
	$result = FALSE;
	$today = date( 'Y-m-d', time() );
	$table = _affiliates_get_tablename( 'affiliates' );
	$query = $wpdb->prepare( "SELECT * FROM $table WHERE type = %s AND from_date <= %s AND ( thru_date IS NULL OR thru_date >= %s ) AND status = 'active'", AFFILIATES_DIRECT_TYPE, $today, $today );
	$affiliate = $wpdb->get_row( $query, OBJECT );
	if ( !empty( $affiliate ) ) {
		$result = $affiliate->affiliate_id;
	}
	return $result;
}

include_once( AFFILIATES_CORE_LIB . '/affiliates-admin.php');
include_once( AFFILIATES_CORE_LIB . '/affiliates-admin-options.php');
include_once( AFFILIATES_CORE_LIB . '/affiliates-admin-affiliates.php');
include_once( AFFILIATES_CORE_LIB . '/affiliates-admin-hits.php');
include_once( AFFILIATES_CORE_LIB . '/affiliates-admin-hits-affiliate.php');
include_once( AFFILIATES_CORE_LIB . '/affiliates-admin-referrals.php');

add_action( 'admin_menu', 'affiliates_admin_menu' );

/**
 * Register our admin section.
 * Arrange to load styles and scripts required.
 */
function affiliates_admin_menu() {

	$pages = array();

	// main
	$page = add_menu_page(
		__( 'Affiliates Overview', AFFILIATES_PLUGIN_DOMAIN ),
		__( 'Affiliates', AFFILIATES_PLUGIN_DOMAIN ),
		AFFILIATES_ACCESS_AFFILIATES,
		'affiliates-admin',
		'affiliates_admin',
		AFFILIATES_PLUGIN_URL . '/images/affiliates.png'
	);
	$pages[] = $page;
	add_action( 'admin_print_styles-' . $page, 'affiliates_admin_print_styles' );
	add_action( 'admin_print_scripts-' . $page, 'affiliates_admin_print_scripts' );

	// overview on affiliates-admin
	$page = add_submenu_page(
		'affiliates-admin',
		__( 'Manage Affiliates', AFFILIATES_PLUGIN_DOMAIN ),
		__( 'Manage Affiliates', AFFILIATES_PLUGIN_DOMAIN ),
		AFFILIATES_ADMINISTER_AFFILIATES,
		'affiliates-admin-affiliates',
		apply_filters( 'affiliates_add_submenu_page_function', 'affiliates_admin_affiliates' )
	);
	$pages[] = $page;
	add_action( 'admin_print_styles-' . $page, 'affiliates_admin_print_styles' );
	add_action( 'admin_print_scripts-' . $page, 'affiliates_admin_print_scripts' );

	// hits by date
	$page = add_submenu_page(
		'affiliates-admin',
		__( 'Visits & Referrals', AFFILIATES_PLUGIN_DOMAIN ),
		__( 'Visits & Referrals', AFFILIATES_PLUGIN_DOMAIN ),
		AFFILIATES_ACCESS_AFFILIATES,
		'affiliates-admin-hits',
		apply_filters( 'affiliates_add_submenu_page_function', 'affiliates_admin_hits' )
	);
	$pages[] = $page;
	add_action( 'admin_print_styles-' . $page, 'affiliates_admin_print_styles' );
	add_action( 'admin_print_scripts-' . $page, 'affiliates_admin_print_scripts' );

	// hits by affiliate
	$page = add_submenu_page(
		'affiliates-admin',
		__( 'Affiliates & Referrals', AFFILIATES_PLUGIN_DOMAIN ),
		__( 'Affiliates & Referrals', AFFILIATES_PLUGIN_DOMAIN ),
		AFFILIATES_ACCESS_AFFILIATES,
		'affiliates-admin-hits-affiliate',
		apply_filters( 'affiliates_add_submenu_page_function', 'affiliates_admin_hits_affiliate' )
	);
	$pages[] = $page;
	add_action( 'admin_print_styles-' . $page, 'affiliates_admin_print_styles' );
	add_action( 'admin_print_scripts-' . $page, 'affiliates_admin_print_scripts' );

	// referrals
	$page = add_submenu_page(
		'affiliates-admin',
		__( 'Referrals', AFFILIATES_PLUGIN_DOMAIN ),
		__( 'Referrals', AFFILIATES_PLUGIN_DOMAIN ),
		AFFILIATES_ACCESS_AFFILIATES,
		'affiliates-admin-referrals',
		apply_filters( 'affiliates_add_submenu_page_function', 'affiliates_admin_referrals' )
	);
	$pages[] = $page;
	add_action( 'admin_print_styles-' . $page, 'affiliates_admin_print_styles' );
	add_action( 'admin_print_scripts-' . $page, 'affiliates_admin_print_scripts' );

	// options
	$page = add_submenu_page(
		'affiliates-admin',
		__( 'Affiliates options', AFFILIATES_PLUGIN_DOMAIN ),
		__( 'Options', AFFILIATES_PLUGIN_DOMAIN ),
		AFFILIATES_ADMINISTER_OPTIONS,
		'affiliates-admin-options',
		apply_filters( 'affiliates_add_submenu_page_function', 'affiliates_admin_options' )
	);
	$pages[] = $page;
	add_action( 'admin_print_styles-' . $page, 'affiliates_admin_print_styles' );
	add_action( 'admin_print_scripts-' . $page, 'affiliates_admin_print_scripts' );

	do_action( 'affiliates_admin_menu', $pages );
}

add_action( 'contextual_help', 'affiliates_contextual_help', 10, 3 );

function affiliates_contextual_help( $contextual_help, $screen_id, $screen ) {

	$show_affiliates_help = false;
	$help = '<h3><a href="http://www.itthinx.com/plugins/affiliates" target="_blank">Affiliates</a></h3>';
	$help .= '<p>';
	$help .= __( 'The complete documentation is available on the <a href="http://www.itthinx.com/plugins/affiliates" target="_blank">Affiliates plugin page</a>', AFFILIATES_PLUGIN_DOMAIN );
	$help .= '</p>';

	switch ( $screen_id ) {
		case 'toplevel_page_affiliates-admin' :
			$show_affiliates_help = true;
			$help .= '<p>' . __( 'This screen offers an overview with basic statistical data.', AFFILIATES_PLUGIN_DOMAIN ) . '</p>';
			$help .= '<ul>';
			$help .= '<li>' . __( '<em>From operative affiliates:</em> includes affiliates that are currently active. This excludes affiliates whose dates are not currently valid as well as those that have been deleted.', AFFILIATES_PLUGIN_DOMAIN ) . '</li>';
			$help .= '<li>' . __( '<em>From operative and non-operative affiliates:</em> excludes deleted affiliates.', AFFILIATES_PLUGIN_DOMAIN ) . '</li>';
			$help .= '<li>' . __( '<em>All time</em> includes data from any affiliates, including deleted affiliates.', AFFILIATES_PLUGIN_DOMAIN ) . '</li>';
			$help .= '<ul>';
			$help .= '<ul>';
			$help .= '<li>' . __( '<em>Hits</em> are HTTP requests for affiliate links.', AFFILIATES_PLUGIN_DOMAIN ) . '</li>';
			$help .= '<li>' . __( '<em>Visits</em> are unique and daily requests for affiliate links.', AFFILIATES_PLUGIN_DOMAIN ) . '</li>';
			$help .= '<li>' . __( '<em>Referrals</em> are recorded by request through the use of an API function. See the <a href="http://www.itthinx.com/plugins/affiliates" target="_blank">documentation</a> for more information.', AFFILIATES_PLUGIN_DOMAIN ) . '</li>';
			$help .= '</ul>';
			$help .= '<p>';
			$help .= __( 'The Affiliates plugin provides the <em>Affiliates Contact</em> widget that can be used to record lead referrals.', AFFILIATES_PLUGIN_DOMAIN );
			$help .= sprintf( __( 'To use it, place the widget in one of your <a href="%s">widget areas</a>.', AFFILIATES_PLUGIN_DOMAIN ), get_admin_url( null, 'widgets.php' ) );
			$help .= '</p>';

			$help .= '<p>';
			$help .= __( 'Note that <em>deleted</em> affiliates are not literally deleted but marked as such so that data that has been collected will still be accesible.', AFFILIATES_PLUGIN_DOMAIN );
			$help .= '</p>';
			break;
		case 'affiliates_page_affiliates-admin-affiliates':
			$show_affiliates_help = true;
			$help .= '<p>' . __( 'Here you can <strong>add</strong>, <strong>edit</strong> and <strong>remove</strong> affiliates.', AFFILIATES_PLUGIN_DOMAIN ) . '</p>';
			$help .= '<p>' .
				__( 'There are two types of links your affiliates may use to link to your site:', AFFILIATES_PLUGIN_DOMAIN ) .
				'</p>' .
				'<ul>' .
				'<li>' .
				'<p class="affiliate-link">' .
				__( 'Affiliate link', AFFILIATES_PLUGIN_DOMAIN ) .
				'<p/>' .
				'<p>' .
				__( 'This link uses a parameter in the URL to record vists you receive through your affiliates.', AFFILIATES_PLUGIN_DOMAIN ) .
				__( 'The affiliate information is removed once a visitor has landed on your site.', AFFILIATES_PLUGIN_DOMAIN ) .
				__( 'You may also append the ?affiliates=... part to links to your posts.', AFFILIATES_PLUGIN_DOMAIN ) .
				'</p>' .
				'</li>' .
				'<li>' .
				'<p class="affiliate-permalink">' .
				__( 'Affiliate permalink', AFFILIATES_PLUGIN_DOMAIN ) .
				'</p>' .
				'<p>' .
				__( 'This link uses a nicer URL to record vists you receive through your affiliates.', AFFILIATES_PLUGIN_DOMAIN ) .
				__( 'The affiliate information is removed once a visitor has landed on your site.', AFFILIATES_PLUGIN_DOMAIN ) .
				'</p>' .
				'</li>' .
				'</ul>' .
				'<p>' .
				__( 'Once a visitor has landed on your site through an affiliate link, referrals may be recorded and attributed to the affiliate.', AFFILIATES_PLUGIN_DOMAIN ) .
				'</p>';
			break;
		case 'affiliates_page_affiliates-admin-hits' :
			$show_affiliates_help = true;
			break;
		case 'affiliates_page_affiliates-admin-hits-affiliate' :
			$show_affiliates_help = true;
			break;
		case 'affiliates_page_affiliates-admin-referrals' :
			$show_affiliates_help = true;
			break;
		case 'affiliates_page_affiliates-admin-options' :
			$show_affiliates_help = true;
			break;
		default:
	}

	$help .= '<p>';
	$help .= __( 'If you require <em>support</em> or <em>customization</em> including <em>referrals integration</em> with your site, you may <a href="http://www.itthinx.com/" target="_blank">contact me here</a>.', AFFILIATES_PLUGIN_DOMAIN );
	$help .= __( 'If you find this plugin useful, please consider making a donation:', AFFILIATES_PLUGIN_DOMAIN );
	$help .= affiliates_donate( false, true );
	$help .= '</p>';

	if ( $show_affiliates_help ) {
		return $help;
	} else {
		return $contextual_help;
	}
}

/**
 * Returns or renders the footer.
 * @param boolean $render
 */
function affiliates_footer( $render = true ) {
	$footer = '<div class="affiliates-footer">' .
		'<p>' .
		__( 'Thank you for using the <a href="http://www.itthinx.com/plugins/affiliates" target="_blank">Affiliates</a> plugin by <a href="http://www.itthinx.com" target="_blank">itthinx</a>.', AFFILIATES_PLUGIN_DOMAIN ) .
		'&nbsp;' .
		__( 'Please get in touch if you need support, consulting or customization.', AFFILIATES_PLUGIN_DOMAIN ) .
		'</p>' .
		'<p>' .
		__( 'If you find this plugin useful, please consider making a donation:', AFFILIATES_PLUGIN_DOMAIN ) .
		affiliates_donate( false ) .
		'</p>' .
		'</div>';
	$footer = apply_filters( 'affiliates_footer', $footer );
	if ( $render ) {
		echo $footer;
	} else {
		return $footer;
	}
}

/**
 * Render or return a donation button.
 * Thanks for supporting me!
 * @param boolean $render
 */
function affiliates_donate( $render = true, $small = false ) {
	if ( !$small ) {
		$donate = '
			<form action="https://www.paypal.com/cgi-bin/webscr" method="post">
			<input type="hidden" name="cmd" value="_s-xclick">
			<input type="hidden" name="hosted_button_id" value="AGZGDNAD9L4YS">
			<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
			<img alt="" border="0" src="https://www.paypalobjects.com/es_ES/i/scr/pixel.gif" width="1" height="1">
			</form>
	 		';
	} else {
		$donate = '
			<form action="https://www.paypal.com/cgi-bin/webscr" method="post">
			<input type="hidden" name="cmd" value="_donations">
			<input type="hidden" name="business" value="itthinx@itthinx.com">
			<input type="hidden" name="lc" value="US">
			<input type="hidden" name="item_name" value="Support WordPress Plugins from itthinx">
			<input type="hidden" name="item_number" value="WordPress Plugins">
			<input type="hidden" name="no_note" value="0">
			<input type="hidden" name="currency_code" value="EUR">
			<input type="hidden" name="bn" value="PP-DonationsBF:btn_donate_SM.gif:NonHostedGuest">
			<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_SM.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
			<img alt="" border="0" src="https://www.paypalobjects.com/es_ES/i/scr/pixel.gif" width="1" height="1">
			</form>			
			';
	}
	if ( $render ) {
		echo $donate;
	} else {
		return $donate;
	}
}

/**
 * Retrieves an affiliate by id.
 * The array returned provides the following attributes:
 * <ul>
 * <li>affiliate_id - the integer id</li>
 * <li>name - the affiliate's name</li>
 * <li>email - email address</li>
 * <li>from_date - from when the affiliate is valid (*)</li>
 * <li>thru_date - [optional] until when the affiliate is valid (*)</li>
 * <li>status - either 'active' or 'deleted'</li>
 * </ul>
 * (*) Suggested referrals will succeed if the current date is within those dates. Both are inclusive.
 *
 * Usage example:
 * $affiliate = affiliates_get_affiliate( $affiliate_id );
 * $email = $affiliate['email'];
 *
 * @param int|string $affiliate_id the affiliate's id
 * @return array affiliate details or null
 */
function affiliates_get_affiliate( $affiliate_id ) {
	global $wpdb;
	$table = _affiliates_get_tablename( 'affiliates' );
	$affiliate = $wpdb->get_row(
		$wpdb->prepare( "SELECT * FROM $table WHERE affiliate_id = %d", intval( $affiliate_id ) ),
		ARRAY_A
	);
	return $affiliate;
}

/**
 * Returns true if the user is an affiliate.
 * @param int|object $user (optional) specify a user or use current if none given
 */
function affiliates_user_is_affiliate( $user_id = null ) {
	global $wpdb;
	$result = false;
	if ( is_user_logged_in() ) {
		if ( $user_id == null ) {
			$user = wp_get_current_user();
		} else {
			$user = get_user_by( 'id', $user_id );
		}
		if ( $user ) {
			$user_id = $user->ID;
			$affiliates_table = _affiliates_get_tablename( 'affiliates' );
			$affiliates_users_table = _affiliates_get_tablename( 'affiliates_users' );
			$affiliates = $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM $affiliates_users_table LEFT JOIN $affiliates_table ON $affiliates_users_table.affiliate_id = $affiliates_table.affiliate_id WHERE $affiliates_users_table.user_id = %d AND $affiliates_table.status ='active'", intval( $user_id ) )
			);
			$result = !empty( $affiliates );
		}
	}
	return $result;
}

/**
 * Returns an array of affiliates.
 * @param boolean $active returns only active affiliates (not deleted via the admin UI)
 * @return array of affiliates
 */
function affiliates_get_affiliates( $active = true, $valid = true ) {

	global $wpdb;
	$results = array();
	$today = date( 'Y-m-d', time() );
	$table = _affiliates_get_tablename( 'affiliates' );
	if ( $active ) {
		if ( $valid ) {
			$query = $wpdb->prepare( "SELECT * FROM $table WHERE from_date <= %s AND ( thru_date IS NULL OR thru_date >= %s ) AND status = 'active' ORDER BY NAME", $today, $today );
	 } else {
	 	$query = $wpdb->prepare( "SELECT * FROM $table WHERE status = 'active' ORDER BY NAME", $today, $today );
	 }
	} else {
		if ( $valid ) {
			$query = $wpdb->prepare( "SELECT * FROM $table WHERE from_date <= %s AND ( thru_date IS NULL OR thru_date >= %s ) ORDER BY NAME", $today, $today );
		} else {
			$query = $wpdb->prepare( "SELECT * FROM $table ORDER BY NAME", $today, $today );
		}
	}
	if ( $affiliates = $wpdb->get_results( $query, ARRAY_A ) ) {
		$results = $affiliates;
	}
	return $results;
}

/**
 * Returns the number of hits for a given affiliate.
 *
 * @param int $affiliate_id the affiliate's id
 * @param string $from_date optional from date
 * @param string $thru_date optional thru date
 * @return int number of hits
 */
function affiliates_get_affiliate_hits( $affiliate_id, $from_date = null , $thru_date = null ) {
	global $wpdb;
	$hits_table = _affiliates_get_tablename('hits');
	$result = 0;
	$where = " WHERE affiliate_id = %d";
	$values = array( $affiliate_id );
	if ( $from_date ) {
		$from_date = date( 'Y-m-d', strtotime( $from_date ) );
	}
	if ( $thru_date ) {
		$thru_date = date( 'Y-m-d', strtotime( $thru_date ) + 24*3600 );
	}
	if ( $from_date && $thru_date ) {
		$where .= " AND date >= %s AND date < %s ";
		$values[] = $from_date;
		$values[] = $thru_date;
	} else if ( $from_date ) {
		$where .= " AND date >= %s ";
		$values[] = $from_date;
	} else if ( $thru_date ) {
		$where .= " AND date < %s ";
		$values[] = $thru_date;
	}
	$query = $wpdb->prepare("
		SELECT sum(count) FROM $hits_table $where
		",
		$values
	);
	$result = intval( $wpdb->get_var( $query) );
	return $result;
}

/**
 * Returns the number of visits for a given affiliate.
 * One or more hits from the same IP on the same day count as one visit.
 *
 * @param int $affiliate_id the affiliate's id
 * @param string $from_date optional from date
 * @param string $thru_date optional thru date
 * @return int number of visits
 */
function affiliates_get_affiliate_visits( $affiliate_id, $from_date = null , $thru_date = null ) {
	global $wpdb;
	$hits_table = _affiliates_get_tablename('hits');
	$result = 0;
	$where = " WHERE affiliate_id = %d";
	$values = array( $affiliate_id );
	if ( $from_date ) {
		$from_date = date( 'Y-m-d', strtotime( $from_date ) );
	}
	if ( $thru_date ) {
		$thru_date = date( 'Y-m-d', strtotime( $thru_date ) + 24*3600 );
	}
	if ( $from_date && $thru_date ) {
		$where .= " AND date >= %s AND date < %s ";
		$values[] = $from_date;
		$values[] = $thru_date;
	} else if ( $from_date ) {
		$where .= " AND date >= %s ";
		$values[] = $from_date;
	} else if ( $thru_date ) {
		$where .= " AND date < %s ";
		$values[] = $thru_date;
	}
	$query = $wpdb->prepare("
		SELECT SUM(visits) FROM
		(SELECT count(DISTINCT IP) visits FROM $hits_table $where GROUP BY DATE) tmp
		",
		$values
	);
	$result = intval( $wpdb->get_var( $query) );
	return $result;
}

/**
 * Returns the number of referrals for a given affiliate.
 *
 * @param int $affiliate_id the affiliate's id
 * @param string $from_date optional from date
 * @param string $thru_date optional thru date
 * @return int number of hits
 */
function affiliates_get_affiliate_referrals( $affiliate_id, $from_date = null , $thru_date = null, $status = null ) {
	global $wpdb;
	$referrals_table = _affiliates_get_tablename( 'referrals' );
	$result = 0;
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
	$query = $wpdb->prepare("
		SELECT count(*) FROM $referrals_table $where
		",
		$values
	);
	$result = intval( $wpdb->get_var( $query) );
	return $result;
}

/**
 * Returns the prefixed DB table name.
 * @param string $name the name of the DB table
 * @return string prefixed DB table name
 */
function _affiliates_get_tablename( $name ) {
	global $wpdb;
	return $wpdb->prefix . AFFILIATES_TP . $name;
}
