<?php
function tmbl_verify_credentials() {
	global $tmbl_options;
	if (empty($tmbl_options)) {
		$tmbl_options = get_option('tmbl_options');
	}
		
	if (!$tmbl_options['api_key']) {
		return array('success' => 0, 'message' => 'Please fill in your API key.');
	} else if (!$result = tmbl_url_open('http://mybloglog.yahooapis.com/v1/user/screen_name/improvingtheweb?appid=' . $tmbl_options['api_key'] . '&format=php')) {			
		return array('success' => 0, 'message' => 'Could not receive web page. Are you sure your server can retrieve offsite pages? Please try again.');
	} else if ($result['response']['code'] != 200) {
		$tmbl_options['api_key'] = '';
		return array('success' => 0, 'message' => 'Invalid HTTP response (' . $result['response']['code'] . ')');
	} else {
		$result = unserialize($result['body']);
		if (!empty($result['name'])) {
			return array('success' => 1);
		} else {
			return array('success' => 0, 'Invalid response.');
		}
	}
}

function tmbl_do_process() {
	global $tmbl_options, $wpdb;
	if (empty($tmbl_options)) {
		$tmbl_options = get_option('tmbl_options');
	}
	
	if (empty($tmbl_options['api_key']) || !empty($_COOKIE['tmbl_session'])) { 
		return;
	}
			
	$cutoff_time = gmdate('Y-m-d H:i:s', time() + (get_option('gmt_offset') * 3600) - 12 * 3600);

	if ($wpdb->query($wpdb->prepare("SELECT visit_date FROM {$wpdb->prefix}mybloglog_visits WHERE member_id = %s AND visit_date >= %s", $_GET['member_id'], $cutoff_time))) {
		return;
	}
			
	if ($api = tmbl_url_open('http://mybloglog.yahooapis.com/v1/user/' . $_GET['member_id'] . '?appid=' . $tmbl_options['api_key'] . '&format=php')) {	
		if ($api['response']['code'] != 200) {
			return;
		}	
				
		$api = unserialize($api['body']);		
		
		if (isset($api['nickname'])) {
			$user = array('id'          => $api['id'], 
						  'nickname'    => str_replace(array('<em>', '</em>'), '', $api['nickname']), 
						  'screen_name' => $api['screen_name'], 
						  'picture'     => $api['pict'], 
						  'profile_url' => $api['url'], 
						  'sex'			=> -1,
						  'age'	        => $api['profile']['age'], 
						  'first_name'  => $api['profile']['location']['first_name'], 
						  'last_name'   => $api['profile']['location']['last_name'], 
						  'city' 	    => $api['profile']['location']['city'], 
						  'region'      => $api['profile']['location']['region'],
						  'country'     => $api['profile']['location']['country'], 
						  'zip'  	    => $api['profile']['location']['zip'], 
						  'bio' 	    => $api['profile']['location']['bio'], 
						  'services'    => '', 
						  'websites'    => '',
						  'tags'		=> '');
			
			if ($api['profile']['sex'] == 'male') {
				$user['sex'] = 1;
			} else if ($api['profile']['sex']) {
				$user['sex'] = 0;
			}
			
			if ($user['picture'] == 'http://pub.mybloglog.com/images/nopic_48.gif') {
				$user['picture'] = '';
			}
						
			if (!empty($api['profile']['services']['service'])) {
				$user['services'] = array();
				foreach ($api['profile']['services']['service'] as $service) {
					$user['services'][$service['name']] = $service['profile_url'];
				}
			} 
				
			if (!empty($api['sites_authored']['site'])) {
				$user['websites'] = array();
				foreach ($api['sites_authored']['site'] as $site) {
					$user['websites'][] = array('name' => $site['name'], 'url' => $site['site_url'], 'profile_url' => $site['url'], 'picture' => $site['pict'], 'description' => $site['description']);
				}
			}
		
			if (!empty($api['tags']['tag'])) {
				foreach ($api['tags']['tag'] as $tag) {
					$user['tags'] .= $tag['name'] . ', ';
				}
				$user['tags'] = substr($user['tags'], 0, -2);
			} 
			
			$user['ip_address'] = preg_replace('/[^0-9a-fA-F:., ]/', '', $_SERVER['REMOTE_ADDR']);
			
			tmbl_save_user($user);
			
			$display_message = $tmbl_options['message_activated'];
			
			if ($display_message && $tmbl_options['message_only_show_to_new_visitors']) {
				if (!empty($_COOKIE['tmbl_nickname']) || !empty($_COOKIE['tmbl_session']) || !empty($COOKIE['comment_author_' . COOKIEHASH])) {
					$display_message = false;
				}
			}
			
			$cookie_timeout = time() + 30000000;
			
			if (empty($_COOKIE['comment_author_' . COOKIEHASH])) {
				if ($user['first_name'] || $user['last_name']) {
					$name = $user['first_name'] . ' ' . $user['last_name'];
				} else if ($user['nickname']) {
					$name = $user['nickname'];
				} else {
					$name = $user['screen_name'];
				}
				
				setcookie('comment_author_' . COOKIEHASH, $name, $cookie_timeout, COOKIEPATH, COOKIE_DOMAIN);
				
				if (!empty($user['websites'])) {
					setcookie('comment_author_url_' . COOKIEHASH, clean_url($user['websites'][0]['url']), $cookie_timeout, COOKIEPATH, COOKIE_DOMAIN);
				}
			}
			
			setcookie('tmbl_session', 1, 0, COOKIEPATH, COOKIE_DOMAIN);
			
			setcookie('tmbl_nickname', $user['nickname'], $cookie_timeout, COOKIEPATH, COOKIE_DOMAIN);	
						
			if ($display_message) {
				tmbl_personalize_message($user);
			}			
		} 
	}	
}

function tmbl_save_user($user) {
	global $wpdb;
	
	//using %s because %d cannot cope.. and %f adds .00000
	$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}mybloglog_visits(member_id, visit_date) VALUES (%s, %s)", $user['id'], current_time('mysql')));	
	
	if ($user['websites']) {
		$user['websites'] = serialize($user['websites']);
	}
	if ($user['services']) {
		$user['services'] = serialize($user['services']);
	}
										
	$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}mybloglog_readers(member_id, nickname, screen_name, avatar, age, sex, first_name, last_name, city, region, country, 
								 zip, bio, services, websites, tags, visits, last_visit_date, ip_address) VALUES (%s, %s, %s, %s, %d, %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, 1, %s, %s)
								 ON DUPLICATE KEY UPDATE nickname = %s, screen_name = %s, avatar = %s, age = %d, sex = %d, first_name = %s, last_name = %s, city = %s, region = %s, 
								 country = %s, zip = %s, bio = %s, services = %s, websites = %s, tags = %s, last_visit_date = %s, visits = visits + 1, ip_address = %s",
								 $user['id'], $user['nickname'], $user['screen_name'], $user['picture'], $user['age'], $user['sex'], $user['first_name'], $user['last_name'], $user['city'], 
								 $user['region'], $user['country'], $user['zip'], $user['bio'], $user['services'], $user['websites'], $user['tags'], 
								 current_time('mysql'), $user['ip_address'], $user['nickname'], $user['screen_name'], $user['picture'], $user['age'], $user['sex'], 
								 $user['first_name'], $user['last_name'], $user['city'], $user['region'], $user['country'], $user['zip'], $user['bio'], $user['services'],
								 $user['websites'], $user['tags'], current_time('mysql'), $user['ip_address']));
}

function tmbl_personalize_message($user) {
	global $tmbl_options;
	
	if (empty($tmbl_options)) {
		$tmbl_options = get_option('tmbl_options');
	}
	
	$message = str_replace(array('[nickname]', '[screen_name]', '[first_name]', '[last_name]'), array($user['nickname'], $user['screen_name'], $user['first_name'], $user['last_name']), $tmbl_options['message_text']);

	$message = str_replace('[avatar]', '<img src="' . $user['picture'] . '" />', $message);
	
	if (!empty($user['websites'])) {
		$message = str_replace('[website]', '<a href="' . $user['websites'][0]['url'] . '" target="_blank" rel="nofollow">' . $user['websites'][0]['name'] . '</a>', $message);
	}

	if ($user['first_name'] || $user['last_name']) {
		$message = str_replace('[name]', $user['first_name'] . ' ' . $user['last_name'], $message);
	} else if ($user['nickname']) {
		$message = str_replace('[name]', $user['nickname'], $message);
	} else {
		$message = str_replace('[name]', $user['screen_name'], $message);
	}
	
	if (strpos($message, '[if website]') !== false) {
		if (!empty($user['websites'])) {
			$message = str_replace(array('[if website]', '[/if]'), '', $message);
		} else {
			$message = preg_replace('/\[if website\].*?\[\/if\]/i', '', $message);
		}
	}
	
	$message = trim($message);
	
	if ($message) {
		header('Content-type: text/javascript');
		
		$message = '<div class="tmbl_message">' . ($tmbl_options['message_show_avatar'] ? '<div class="tmbl_avatar"><img src="' . $user['picture'] . '" /></div>' : '') . '<div class="tmbl_text">' . $message . '</div><div style="clear:both"></div></div>';
	
		echo 'if (document.getElementById("tmbl_placeholder")) { document.getElementById("tmbl_placeholder").innerHTML = "' . addslashes($message) . '"; }';
	}
}

function tmbl_url_open($url, $args=array('timeout' => 10), $tries=1) {
	if (function_exists('wp_remote_get')) {
		$result = wp_remote_get($url, $args);

		if (is_wp_error($result)) {
			if ($tries < 3 && $result->get_error_code() == 'http_request_failed') {
				return tmbl_url_open($url, $args, ++$tries);
			} else {
				return false;
			}			
		} else {
			return $result;
		}
	} else {	
		if (!class_exists('Snoopy')) {
			require_once ABSPATH . 'wp-includes/class-snoopy.php';
		}
		
		$snoopy = new Snoopy();
				
		if (!$snoopy->fetch($url) || !$snoopy->results) {
			if ($tries < 3) {
				return tmbl_url_open($url, $args, ++$tries);
			} else {
				return false;
			}
		}		
		
		return array('body' => $snoopy->results, 'response' => array('code' => $snoopy->status));
	}
}
?>