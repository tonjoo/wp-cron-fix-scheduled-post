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

add_action('wp_cron_fix_scheduled_post', 'wp_cron_fix_scheduled_post_do');

function wp_cron_fix_scheduled_post_do( $from_front = false )  {
	/*
	 * Bail if needed
	 */
	$wp_cron_fix_scheduled_missed = get_transient( 'wp_cron_fix_scheduled_missed_timeout' );

	if ( ( $wp_cron_fix_scheduled_missed !== false ) && ( $wp_cron_fix_scheduled_missed > ( time() - ( 100 ) ) ) ) {

		wp_cron_fix_scheduled_post_do_log("Old process still running");
		
		if(!$from_front)
			return true;
	}

	set_transient( 'wp_cron_fix_scheduled_missed_timeout',  time(), 100 );

	global $wpdb;

	$qry = "SELECT ID FROM {$wpdb->posts} WHERE ( ( post_date > 0 && post_date <= %s ) ) AND post_status = 'future' LIMIT 0,10";

	$sql = $wpdb->prepare( $qry, current_time( 'mysql', 0 ) );

	$scheduledIDs = $wpdb->get_col( $sql );

	if ( ! count( $scheduledIDs ) ) {
		
		wp_cron_fix_scheduled_post_do_log("no missed post");

		return true;
	}
	
	wp_cron_fix_scheduled_post_do_log("Missed post : ".implode(",", $scheduledIDs ));

	foreach ( $scheduledIDs as $scheduledID ) {
		if ( ! $scheduledID )
			continue;

		wp_publish_post( $scheduledID );
	}

	if($from_front)
		return $scheduledIDs;
	else
		return true;
}

// Add custom cron interval
add_filter( 'cron_schedules', 'wp_cfsp_add_custom_cron_intervals', 10, 1 );

function wp_cfsp_add_custom_cron_intervals( $schedules ) {
	// $schedules stores all recurrence schedules within WordPress
	$schedules['wp_fsp_add_custom_cron_intervals_five_minutes'] = array(
		'interval'	=> 300,	// Number of seconds
		'display'	=> 'Once Every 5 Minutes'
	);

	// Return our newly added schedule to be merged into the others
	return (array)$schedules; 
}

function wp_cron_fix_scheduled_post_do_log( $message = false ) {

	if(!defined('CFSP_LOG'))
		define('CFSP_LOG',false);
	
	if( !CFSP_LOG )
		return;

	global $wp_filesystem;

	$url = wp_nonce_url('plugins.php');

	$dir = plugin_dir_path( __FILE__ );

	global $wp_filesystem;

	// Initialize the WP filesystem, no more using 'file-put-contents' function
	if (empty($wp_filesystem)) {
	    require_once (ABSPATH . '/wp-admin/includes/file.php');
	    WP_Filesystem();
	}

	// if(!$wp_filesystem->put_contents( $path, $css, 0644) ) {
	    // return __('Failed to create css file');
	// }

	// if (false === ($creds = request_filesystem_credentials($url, '', false, $dir, null) ) ) {
	//     echo "Could not create filesystem credentials";
	//     return;
	// }

	// if ( ! WP_Filesystem($creds) ) {
	//     request_filesystem_credentials($url, '', true, $dir, null);
	//     echo "Filesystem credentials were not available";
	//     return;
	// }

	//@todo if size > 1mb then create new file
	$text = $wp_filesystem->get_contents($dir.'log.txt');

	$message = "[".current_time( 'mysql' )."] ".$message;

	$text = $text.$message."\n";

	$wp_filesystem->put_contents(
	  $dir.'log.txt',
	  $text
	 );
}