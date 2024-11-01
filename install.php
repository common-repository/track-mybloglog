<?php
if (!defined('WP_CONTENT_URL')) die;

function tmbl_do_install() {
	tmbl_create_table();
	tmbl_default_options(true);
	wp_schedule_event(mktime(0, 0, 0, date('n'), date('j') + 1, date('Y')), 'daily', 'tmbl_clear_visits');
}

function tmbl_do_uninstall() {
	global $wpdb;
	
	delete_option('tmbl_options');
	$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}mybloglog_readers");
	$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}mybloglog_visits");
	wp_clear_scheduled_hook('tmbl_clear_visits');
}

function tmbl_create_table() {
	global $wpdb;
	
	$charset_collate = '';

	if ($wpdb->supports_collation()) {
		if (!empty($wpdb->charset)) {
			$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
		}
		if (!empty($wpdb->collate)) {
			$charset_collate .= " COLLATE $wpdb->collate";
		}
	}

	if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}mybloglog_visits'") != "{$wpdb->prefix}mybloglog_visits") {			
		$wpdb->query("CREATE TABLE  `{$wpdb->prefix}mybloglog_visits` (
	 		`visit_ID` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
	 		`member_id` BIGINT UNSIGNED NOT NULL,
	 		`visit_date` datetime NOT NULL default '0000-00-00 00:00:00',
	 		KEY `member_id` (member_id),
			KEY `visit_date` (visit_date))
			$charset_collate");
	}
	
	if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}mybloglog_readers'") != "{$wpdb->prefix}mybloglog_readers") {			
		$wpdb->query("CREATE TABLE  `{$wpdb->prefix}mybloglog_readers` (
			 `member_ID` BIGINT UNSIGNED NOT NULL ,
			 `nickname` VARCHAR( 100 ) NOT NULL ,
			 `screen_name` VARCHAR( 100 ) NOT NULL ,
			 `avatar` VARCHAR( 200 ) NOT NULL ,
			 `age` TINYINT NOT NULL ,
			 `sex` TINYINT( 1 ) NOT NULL DEFAULT -1,
			 `first_name` VARCHAR( 100 ) NOT NULL ,
			 `last_name` VARCHAR( 100 ) NOT NULL ,
			 `city` VARCHAR( 100 ) NOT NULL ,
			 `region` VARCHAR( 100 ) NOT NULL ,
			 `country` VARCHAR( 100 ) NOT NULL ,
			 `zip` VARCHAR( 20 ) NOT NULL ,
			 `bio` VARCHAR( 250 ) NOT NULL ,
			 `services` TEXT NOT NULL ,
			 `websites` TEXT NOT NULL ,
			 `tags` TEXT NOT NULL ,
			 `visits` SMALLINT UNSIGNED NOT NULL ,
			 `last_visit_date` datetime NOT NULL default '0000-00-00 00:00:00',
			 `ip_address` VARCHAR( 100 ) NOT NULL ,
			 PRIMARY KEY (`member_ID`), 
			 KEY `last_visit_date` (last_visit_date))
			 $charset_collate");
			
		$user = array('id' 			=> '2008121200351159', 
					  'nickname'    => 'Improving The Web', 
					  'screen_name' => 'improvingtheweb', 
					  'picture'     => 'http://f5.yahoofs.com/coreid/4958fe68i237bzul3re3/hMabbMo4drbTOlVnjbY0bYX3aZiPnkU-/1/tn48.jpg?ciAYU2JBZRcDwkJC', 
					  'profile_url' => 'http://www.mybloglog.com/buzz/members/improvingtheweb/', 
					  'sex' 		=> 1, 
					  'age' 		=> '',
					  'first_name'  => 'Wesley',
					  'last_name'   => '',
					  'city' 		=> '',
					  'region'	 	=> '',
					  'country' 	=> '',
					  'zip'			=> '',
					  'bio' 		=> '',
					  'tags' 		=> 'PHP, wordpress, webdev, blogging', 
					  'services'    => array('twitter'    => 'http://twitter.com/improvingtheweb',
		            					  	 'technorati' => 'http://www.technorati.com/people/technorati/improvingtheweb'),
		 			  'websites' 	=> array(0 => array('name' 	      => 'Improving The Web', 
													 	'url' 		  => 'http://improvingtheweb.com', 
													 	'profile_url' => 'http://www.mybloglog.com/buzz/community/ImprovingTheWeb', 
													 	'picture'     => 'http://f3.yahoofs.com/mbl/sh/29e7d353af3b532b38807960102b93353412cb11.gif?mlAAGKKBPqn0JDo2', 
													 	'description' => 'Improve your website, even if your not a geek')), 
					  'ip_address'  => '0');
					
		require TMBL_DIR . '/process.php';
		
		tmbl_save_user($user);
	}
}

function tmbl_default_options($install=false) {
	if ($install && get_option('tmbl_options')) {
		return;
	}
		
	$tmbl_options = array('api_key'							  => '', 
						  'tracking_code'					  => '', 
						  'message_activated'				  => 0,
						  'message_text' 					  => 'Hello [name], thanks for visiting my site. [if website]I\'ll be sure to return the favor and check out [website] as well![/if]', 
						  'message_only_show_to_new_visitors' => 1, 
						  'message_show_avatar' 			  => 1, 
						  'message_show_on_pages'			  => 1, 
						  'message_show_on_posts'			  => 1);
						
	if ($install) {
		add_option('tmbl_options', $tmbl_options);
	} else {
		return $tmbl_options;
	}
}

function tmbl_update_css($contents, $action='update') {
	$location = TMBL_DIR . '/style.css';
	
	if (strtolower($action) == 'reset to default') {
		$contents = '.tmbl_message {
 background:#fff;
 border:1px solid #eee;
 margin:10px 0;
 width:100%;
 padding:0;
 margin:0;
}

.tmbl_avatar {
 float:left;
 padding:0;
 margin:0;
 padding:5px;
}

.tmbl_avatar img{
 border:none;
 margin:0;
 padding:0;
 vertical-align:top;
}

.tmbl_text {
 padding:5px;
}';
	}

	if (!is_writable($location) || !($handle = fopen($location, 'w')) || fwrite($handle, $contents) === false) {
		return false;
	} else {
		fclose($handle);
		return true;
	}
}
?>