<?php 
/*
 * Plugin Name: WP cron fix scheduled post
 * Plugin URI: https://tonjoo.com/product/wp-cron-fix-scheduled-post/
 * Description: Fix wordpress scheduled post using cron job ,based on https://wordpress.org/plugins/wp-missed-schedule/
 * Author: Tonjoo Studio
 * Author URI: https://tonjoostudio.com
 * Version: 1.0.0
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Network: true
 */

defined( 'ABSPATH' ) OR exit;

defined( 'WPINC' ) OR exit;

register_activation_hook(__FILE__, 'wp_cron_fix_scheduled_post_active');

function wp_cron_fix_scheduled_post_active() {
	wp_schedule_event(time(), 'wp_fsp_add_custom_cron_intervals_five_minutes', 'wp_cron_fix_scheduled_post');
}

register_deactivation_hook(__FILE__, 'wp_cron_fix_scheduled_post_deactive');

function wp_cron_fix_scheduled_post_deactive() {
	wp_clear_scheduled_hook('wp_cron_fix_scheduled_post');
}



add_action('init','wp_cron_fix_scheduled_post_init');;

function wp_cron_fix_scheduled_post_init() {
	
	add_action('wp_cron_fix_scheduled_post', 'wp_cron_fix_scheduled_post_do');
	
}

function wp_cron_fix_scheduled_post_do() 
{
	/*
	 * Bail if needed
	 */
	$wp_cron_fix_scheduled_missed = get_transient( 'wp_cron_fix_scheduled_missed_timeout' );

	if ( ( $wp_cron_fix_scheduled_missed !== false ) && ( $wp_cron_fix_scheduled_missed > ( time() - ( 200 ) ) ) )
		return;

	set_transient( 'wp_cron_fix_scheduled_missed_timeout',  time(), 200 );

	global $wpdb;

	$qry = "SELECT ID FROM {$wpdb->posts} WHERE ( ( post_date > 0 && post_date <= %s ) ) AND post_status = 'future' LIMIT 0,10";

	$sql = $wpdb->prepare( $qry, current_time( 'mysql', 0 ) );

	$scheduledIDs = $wpdb->get_col( $sql );

	if ( ! count( $scheduledIDs ) )
		return;

	foreach ( $scheduledIDs as $scheduledID )
	{
		if ( ! $scheduledID )
			continue;

		wp_publish_post( $scheduledID );
	}
}

// Add custom cron interval
add_filter( 'cron_schedules', 'wp_cfsp_add_custom_cron_intervals', 10, 1 );

function wp_cfsp_add_custom_cron_intervals( $schedules ) {
	// $schedules stores all recurrence schedules within WordPress
	$schedules['wp_fsp_add_custom_cron_intervals_five_minutes'] = array(
		'interval'	=> 300,	// Number of seconds, 600 in 10 minutes
		'display'	=> 'Once Every 5 Minutes'
	);

	// Return our newly added schedule to be merged into the others
	return (array)$schedules; 
}