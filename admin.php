<?php
if (!defined('WP_CONTENT_URL')) die;

add_action('admin_menu', 'tmbl_admin_menu');

if (isset($_GET['tmbl_reader_log'])) {
	add_action('init', 'tmbl_admin_reader_log');
}

function tmbl_admin_menu() {
	add_thickbox();
	add_submenu_page('options-general.php', 'Track MyBlogLog', 'Track MyBlogLog', 8, 'Track MyBlogLog', 'tmbl_admin');
}

function tmbl_admin() {
	global $tmbl_options, $wpdb;

	if (empty($tmbl_options)) {
		$tmbl_options = get_option('tmbl_options');
	}
			
	if (!empty($_POST)) {
		check_admin_referer('track-mybloglog');
	}
			
	if (!empty($_POST['tmbl_do_api'])) {	
		require TMBL_DIR . '/process.php';
		
		$tmbl_options['api_key']	   = trim(stripslashes($_POST['api_key']));
		$tmbl_options['tracking_code'] = trim(stripslashes($_POST['tracking_code']));
		
		$report = tmbl_verify_credentials();
			
		if (!$report['success']) {
			echo '<div id="message" class="error"><p>' . __($report['message'], 'tmbl') . '</p></div>';
		} else {
			update_option('tmbl_options', $tmbl_options);
				
			echo '<div id="message" class="updated fade"><p>' . __('Settings saved successfully.', 'tmbl') . '</p></div>' . "\n";		
		}
	} else if (!empty($_POST['tmbl_do_message'])) {
		$tmbl_options['message_text'] 					   = trim(stripslashes($_POST['message_text']));
		$tmbl_options['message_activated']				   = (int) $_POST['message_activated'];
		$tmbl_options['message_only_show_to_new_visitors'] = (int) $_POST['message_only_show_to_new_visitors'];
		$tmbl_options['message_show_avatar'] 			   = (int) $_POST['message_show_avatar'];
		$tmbl_options['message_show_on_pages'] 		 	   = (int) $_POST['message_show_on_pages'];
		$tmbl_options['message_show_on_posts'] 			   = (int) $_POST['message_show_on_posts'];
		
		update_option('tmbl_options', $tmbl_options);
		
		echo '<div id="message" class="updated fade"><p>' . __('Settings saved successfully.', 'tmbl') . '</p></div>' . "\n";		
	} else if (!empty($_POST['tmbl_do_css'])) {
		require TMBL_DIR . '/install.php';
		
		if ($success = tmbl_update_css($_POST['css'], $_POST['tmbl_do_css'])) {
			echo '<div id="message" class="updated fade"><p>' . __('CSS updated successfully.', 'tmbl') . '</p></div>' . "\n";
		} else {
			echo '<div id="message" class="error"><p>' . __('Cannot write to file, make sure it is writable.', 'tmbl') . '</p></div>';
		}
	}
	
	$css_location 	   = TMBL_DIR . '/style.css';
	$css_contents 	   = file_get_contents($css_location);
	$css_writable 	   = is_writable($css_location);
	
	if (!isset($_GET['pagenum'])) {
		$_GET['pagenum'] = 1;
	}
	
	$page = absint($_GET['pagenum']);
	
	if ($page < 1 || $page != $_GET['pagenum']) {
		$page = 1;
	}
						
	$readers = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}mybloglog_readers ORDER BY last_visit_date DESC LIMIT " . (($page-1) * 20) . ", 20");
		
	$page_links = paginate_links(array('base'      => add_query_arg('pagenum', '%#%'),
									   'format'    => '',
									   'prev_text' => __('&laquo;'),
									   'next_text' => __('&raquo;'),
									   'total'     => ceil($wpdb->get_var("SELECT count(*) FROM {$wpdb->prefix}mybloglog_readers") / 20),
									   'current'   => $page));
	?>
	
	<div class="wrap">
	<h2><?php _e('Track MyBlogLog', 'tmbl'); ?></h2>
	
	<p><?php _e('For detailed instructions on how to use this plugin, read <a href="http://www.improvingtheweb.com/wordpress-plugins/track-mybloglog/" target="_blank">the plugin page</a>.', 'tmbl'); ?></p>
	
	<h3><?php _e('Options', 'tmbl'); ?></h3>
	
	<?php if ($tmbl_options['api_key']): ?>
	<p><a href="#" onclick="javascript:document.getElementById('tmbl_options').style.display='block';return false;"><?php _e('click to edit', 'tmbl'); ?></a></p>
	<?php endif; ?>
	
	<div id="tmbl_options" <?php if ($tmbl_options['api_key']): ?>style="display:none"<?php endif; ?>>
	
	<form action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="post">	
	
	<?php if (!$tmbl_options['api_key']): ?>
	<p><strong><?php _e('API Key', 'tmbl'); ?></strong>: <?php _e('You\'ll need to register an API key. You can do so <a href="https://developer.yahoo.com/wsregapp/" target="_blank">here</a>. Choose "Generic, no user authentication required". Under "required access scopes" you also don\'t need to check anything.', 'tmbl'); ?></p>
	<?php endif; ?>
		
	<?php if (!$tmbl_options['tracking_code']): ?>
	<p><strong><?php _e('Tracking Code', 'tmbl'); ?></strong>: <?php _e('If you don\'t have the recent visitors widget, you\'ll need to enter your tracking code below. Go to <a href="http://www.mybloglog.com" target="_blank">your profile</a> &raquo; your sites &raquo; settings and look for it at the bottom of the page.', 'tmbl'); ?></p>
	<?php endif; ?>
	
	<table class="form-table">
	<tr>
	<th style="padding-left:0;">API key</th>
	<td><input type="text" name="api_key" size="50" value="<?php echo htmlspecialchars($tmbl_options['api_key']); ?>" /></td>
	</tr>
	<tr>
	<th style="padding-left:0;">Tracking code</th>
	<td>
	<input type="text" name="tracking_code" size="50" value="<?php echo htmlspecialchars($tmbl_options['tracking_code']); ?>" />	
	</td>
	</tr>
	<tr>
	<td colspan="2" style="padding-left:0;">
	<?php wp_nonce_field('track-mybloglog'); ?>
	<span class="submit"><input name="tmbl_do_api" value="<?php _e('Save', 'tmbl'); ?>" type="submit" /></span>
	</td>
	</tr>
	</table>

	</form>
	
	</div>
	
	<h3><?php _e('Personalized Message', 'tmbl'); ?></h3>
	
	<p><?php _e('If you want to, you can display a personalized message to MyBlogLog Visitors.', 'tmbl'); ?> <a href="#" onclick="javascript:document.getElementById('tmbl_personalized_message').style.display='block';return false;"><?php _e('click to edit', 'tmbl'); ?></a></p>
	
	<div id="tmbl_personalized_message" style="display:none">
	
	<form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
	
	<p>
	<input type="checkbox" name="message_activated" value="1" <?php if ($tmbl_options['message_activated']): ?>checked="checked"<?php endif; ?> /> <?php _e('Activate personalized message', 'tmbl'); ?>
	</p>
	
	<p>
	<textarea name="message_text" rows="3" cols="80"><?php echo htmlspecialchars($tmbl_options['message_text']); ?></textarea>
	</p>
	
	<p>
	<input type="checkbox" name="message_only_show_to_new_visitors" value="1" <?php if ($tmbl_options['message_only_show_to_new_visitors']): ?>checked="checked"<?php endif; ?> /> <?php _e('Only show to new visitors', 'tmbl'); ?>
	<input type="checkbox" name="message_show_avatar" value="1" <?php if ($tmbl_options['message_show_avatar']): ?>checked="checked"<?php endif; ?> /> <?php _e('Show avatar', 'tmbl'); ?>
	<input type="checkbox" name="message_show_on_posts" value="1" <?php if ($tmbl_options['message_show_on_posts']): ?>checked="checked"<?php endif; ?> /> <?php _e('Show on posts', 'tmbl'); ?>
	<input type="checkbox" name="message_show_on_pages" value="1" <?php if ($tmbl_options['message_show_on_pages']): ?>checked="checked"<?php endif; ?> /> <?php _e('Show on pages', 'tmbl'); ?>
	</p>

	<p>
	<?php wp_nonce_field('track-mybloglog'); ?>
	<span class="submit"><input name="tmbl_do_message" value="<?php _e('Save', 'tmbl'); ?>" type="submit" /></span>
	</p>

	</form>
	
	<h3><?php _e('Edit the CSS', 'tmbl'); ?></h3>
	<p><?php _e('If you want to edit the CSS for the personalized message, ', 'tmbl'); ?> <a href="#" onclick="javascript:document.getElementById('tmbl_css').style.display = 'block';return false;"><?php _e('click here'); ?></a></p>
	<div id="tmbl_css" style="display:none">
	<?php if (!$css_writable): ?><p><?php _e('You will have to make "style.css" file writable first.', 'tmbl'); ?></p><?php endif; ?>
	<form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
	<p>
	<textarea name="css" rows="10" cols="80"><?php echo htmlspecialchars($css_contents); ?></textarea>
	</p>	
	<p>
	<?php wp_nonce_field('track-mybloglog'); ?>
	<span class="submit"><input name="tmbl_do_css" value="<?php _e('Update', 'tmbl'); ?>" type="submit" /> <input name="tmbl_do_css" value="<?php _e('Reset to default', 'tmbl'); ?>" type="submit" /></span>
	</form>
	</div>
	
	</div>
	
	<h3><?php _e('MyBlogLog Visitor Log', 'tmbl'); ?></h3>	
	
	<?php if (!empty($readers)): ?>	
	<?php if ($page == 1 && count($readers) == 1 && $readers[0]->screen_name == 'improvingtheweb'): ?>
		<p><?php _e('The below result is a sample only. It will fill up with real results as the site is being visited.', 'tmbl'); ?></p>		
	<?php endif; ?>
	
	<?php if ($page_links): ?>
		<div class="tablenav"><div class="tablenav-pages"><?php echo $page_links; ?></div></div>
	<?php endif; ?>
	
	<table class="widefat post fixed" cellspacing="0">
	<thead>
	<tr>
	<th scope="col">Avatar</th>
	<th scope="col">Name</th>
	<th scope="col">Location</th>
	<th scope="col">Websites</th>
	<th scope="col">Services</th>
	<th scope="col">Tags</th>
	<th scope="col">Last Visit</th>
	</tr>
	</thead>

	<tfoot>
	<tr>
	<th scope="col">Avatar</th>
	<th scope="col">Name</th>
	<th scope="col">Location</th>
	<th scope="col">Websites</th>
	<th scope="col">Services</th>
	<th scope="col">Tags</th>
	<th scope="col">Last Visit</th>
	</tr>
	</tfoot>
	
	<tbody>
	<?php foreach ($readers as $reader): ?>
	<tr class="alternate" valign="top">
		<td><img src="<?php echo ($reader->avatar ? $reader->avatar : 'http://pub.mybloglog.com/images/nopic_48.gif'); ?>" title="Avatar" /></td>
		<td>
			<a href="http://www.mybloglog.com/buzz/members/<?php echo $reader->screen_name; ?>" target="_blank"><?php if ($reader->first_name): ?><?php echo $reader->first_name; ?> <?php echo $reader->last_name; ?><?php elseif ($reader->nickname): ?><?php echo $reader->nickname; ?><?php else: ?><?php echo $reader->screen_name; ?><?php endif; ?></a>
			<?php if ($reader->age || $reader->sex != -1): ?>
			<br />
			<?php endif; ?>
			<?php if ($reader->age): ?><?php echo $reader->age; ?><?php if ($reader->sex): ?>, <?php endif; ?><?php endif; ?>
			<?php if ($reader->sex != -1): ?><?php echo ($reader->sex == 1 ? 'Male' : 'Female'); ?><?php endif; ?>
		</td>
		<td>
			<?php if ($reader->city || $reader->region || $reader->country): ?>
			<?php if ($reader->city): ?><?php echo $reader->city; ?><?php if ($reader->region): ?>, <?php endif; ?><?php endif; ?>
			<?php if ($reader->region): ?><?php echo $reader->region; ?><?php endif; ?>
			<?php if ($reader->country): ?>
				<?php if ($reader->city || $reader->region): ?><br /><?php endif; ?>
				<?php echo $reader->country; ?>
			<?php endif; ?>
			<?php else: ?>
				Not listed
			<?php endif; ?>
		</td>
		<td>
		<?php if ($reader->websites): ?>
		<?php $reader->websites = unserialize($reader->websites); ?>
		<ul>
		<?php foreach ($reader->websites as $website): ?>
			<li><a href="<?php echo $website['url']; ?>" target="_blank"><?php echo $website['name']; ?></a></li>			
		<?php endforeach; ?>
		</ul>
		<?php else: ?>
			Not listed
		<?php endif; ?>
		</td>
		<td>
		<?php if ($reader->services): ?>
		<?php $reader->services = unserialize($reader->services); ?>
		<ul>
		<?php foreach ($reader->services as $service_name => $service_url): ?>
			<li><a href="<?php echo $service_url; ?>" target="_blank"><?php echo $service_name; ?></a></li>
		<?php endforeach; ?>
		</ul>
		<?php else: ?>
			Not listed
		<?php endif; ?>
		</td>
		<td>
		<?php if ($reader->tags): ?>
			<?php echo $reader->tags; ?>
		<?php else: ?>
			Not listed
		<?php endif; ?>
		</td>
		<td>
		<?php if ($reader->visits > 1): ?><a href="?tmbl_reader_log=<?php echo $reader->member_ID; ?>&TB_iframe=true&width=220&height=175" class="thickbox" title="Reader visit Log"><?php endif; ?><?php echo $reader->last_visit_date; ?><?php if ($reader->visits > 1): ?></a><?php endif; ?> <span style="font-weight:bold">(<?php echo $reader->visits; ?>)</span>
		</td>
	</tr>
	<?php endforeach; ?>
	</tbody>
	</table>	
	
	<?php if ($page_links): ?>
		<div class="tablenav"><div class="tablenav-pages"><?php echo $page_links; ?></div></div>
	<?php endif; ?>
	
	<?php else: ?>
		<p>No readers found.</p>
	<?php endif; ?>
	
	<h3><?php _e('Acknowledgements', 'tmbl'); ?></h3>	
	<p>Subscribe to my blog at <a href="http://www.improvingtheweb.com" style="background:yellow;padding:5px;" target="_blank">Improving The Web</a> : <a href="http://rss.improvingtheweb.com/improvingtheweb/wVZp" target="_blank">RSS</a> | <a href="http://twitter.com/improvingtheweb" target="_blank">Twitter</a></p>	
	
	</div>
	<?php
}

function tmbl_admin_reader_log() {
	global $wpdb;
	
	$visits = $wpdb->get_results($wpdb->prepare("SELECT UNIX_TIMESTAMP(visit_date) AS visit_date FROM {$wpdb->prefix}mybloglog_visits WHERE member_id = %s ORDER BY visit_date DESC LIMIT 10", $_GET['tmbl_reader_log']));
	
	echo '<html><head><style type="text/css">body { font: 13px "Lucida Grande", Verdana, Arial, "Bitstream Vera Sans", sans-serif; }</style></head><body>';
	
	if (empty($visits)) {
		echo '<p>' . _e('No visit log for this member yet.', 'tmbl') . '</p>';
	} else {
		echo '<ul>';
		foreach ($visits as $visit) {
			echo '<li>' . date('F j, g:i a', $visit->visit_date) . '</li>';
		}
		echo '</ul>';
	}
	
	echo '</body></html>';
	
	die();
}
?>