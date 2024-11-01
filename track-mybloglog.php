<?php
/*
Plugin Name: Track MyBlogLog
Plugin URI: http://www.improvingtheweb.com/wordpress-plugins/track-mybloglog/
Description: Track MyBlogLog visitors on your website.
Author: Improving The Web
Version: 1.0
Author URI: http://www.improvingtheweb.com/
*/

if (!defined('WP_CONTENT_URL')) {
	define('WP_CONTENT_URL', get_option('siteurl') . '/wp-content');
}
if (!defined('WP_CONTENT_DIR')) {
	define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
}
if (!defined('WP_PLUGIN_URL')) {
	define('WP_PLUGIN_URL', WP_CONTENT_URL. '/plugins');
}
if (!defined('WP_PLUGIN_DIR')) {
	define('WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins');
}

if (!defined('TMBL_DIR')) {
	define('TMBL_DIR', dirname(__FILE__));
}

if (is_admin()) {	
	register_activation_hook(__FILE__, 'tmbl_install');
	register_deactivation_hook(__FILE__, 'tmbl_uninstall');
	
	require TMBL_DIR . '/admin.php';
} else {
	add_filter('the_content', 'tmbl_filter_content', 5);
	add_action('wp_footer', 'tmbl_footer', 9999); 
	add_action('wp_head', 'tmbl_header');
	
	if (!empty($_GET['tmbl_process'])) {
		add_action('init', 'tmbl_process', 1);	
	}
}

add_action('tmbl_clear_visits', 'tmbl_clear_visits');

function tmbl_install() {
	require TMBL_DIR . '/install.php';
	tmbl_do_install();
}

function tmbl_uninstall() {
	require TMBL_DIR . '/install.php';
	tmbl_do_uninstall();
}

function tmbl_process() {	
	require TMBL_DIR . '/process.php';
	tmbl_do_process();
	die();	
}

function tmbl_filter_content($content) {	
	global $tmbl_options, $tmbl_shown;
	if (empty($tmbl_options)) {
		$tmbl_options = get_option('tmbl_options');
	}

	if ($tmbl_options['message_text'] && !is_feed() && empty($tmbl_shown) && (($tmbl_options['message_show_on_posts'] && (is_home() || is_single())) || ($tmbl_options['message_show_on_pages'] && (is_home() || is_page())))) {
		$tmbl_shown = true;
		return '<span id="tmbl_placeholder"></span>' . $content;
	} else {
		return $content;
	}
}

function tmbl_header() {
	echo '<link rel="stylesheet" href="' . WP_PLUGIN_URL . '/track-mybloglog/style.css" type="text/css" />';
}

function tmbl_footer() {
	global $tmbl_options;
	if (empty($tmbl_options)) {
		$tmbl_options = get_option('tmbl_options');
	}
	if ($tmbl_options['tracking_code']) {
		echo $tmbl_options['tracking_code'];
	}
	echo '<script type="text/javascript" src="' . WP_PLUGIN_URL . '/track-mybloglog/mybloglog.js"></script>';
}

function tmbl_clear_visits() {
	global $wpdb;
	
	$cutoff_time = gmdate('Y-m-d H:i:s', time() - 60 * 60 * 24 * 30);
		
	$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}mybloglog_visits WHERE visit_date <= %s", $cutoff_time));
}
?>