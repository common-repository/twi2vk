<?php 

/*
Plugin Name: Twi2VK
Plugin URI: http://tigors.net/twi2vk/
Description: Crossposts twitter status to VK.com  
Version: 0.1.1
Author: TIgor4eg
Author URI: http://tigors.net
License: GPL2

*/

/*  Copyright 2011-2012 Tesliuk Igor  (email : tigor@tigors.net)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

add_action('update_status_in_vk_event', 'update_status_in_vk');
add_action('admin_menu',"admin_twi2vk_menu");


function admin_twi2vk_menu() {
	add_options_page('Twi2VK', 'Twi2VK', 'manage_options', 'twi2vk', 'twi2vk_options');
	add_action( 'admin_init', 'register_twi2vk_settings' );

}

function register_twi2vk_settings() 
{
	register_setting('twi2vk_group','twi2vk_settings');
}


function twi2vk_get_token_by_code($settings)
{
	$url = 'https://oauth.vk.com/access_token?client_id=3034345&client_secret=rtgfEI8LwxpHTnUq1HoR&code='.$settings['auth_code'];
	
	$ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

    $otvet = curl_exec($ch);
    curl_close($ch);
	
	
	
	
	$json = json_decode($otvet, true);
	
	$settings['auth_code'] = '';
	if ('' != $json['access_token'])
	{
		$settings['access_token'] = $json['access_token'];
		$settings['owner_id'] = $json['user_id'];
	} else {
		$settings['error'] = $json;
	}
	
	
	return $settings;
}

function twi2vk_enter_captcha($uid,$captcha)
{
	$options = get_option('twi2vk');
	$settings = get_option('twi2vk_settings');
	$item = $options[$uid];
	
	$reply = json_decode($item['reply'], true);
	
	
	$item['reply'] = twi2vk_requestVK('wall.post', array(
            'owner_id' => $settings['owner_id'],
            'message' => $item['message'],
            'from_group' => 1,
            'attachments' => $item['attachments'],
			'captcha_sid' => $reply['error']['captcha_sid'],
			'captcha_key' => $captcha
        ));
	
	twi2vk_posted($item);
	
	return $item['reply'];
}

function twi2vk_options()
{
	?>
	<h1>Twi2VK</h1>
	<form method="post" action="options.php">

            <?php settings_fields('twi2vk_group'); ?>
	
	
	<?php
	echo update_status_in_vk();
	
	$settings = get_option('twi2vk_settings');

	if ('' != $settings['auth_code'])
	{
		$settings = twi2vk_get_token_by_code($settings);
	}
	
	 if ('true' == $settings['auth_error'])
	{
		?> 
		Some of actions reported an error with auth token. Wipe it?<br />
		<input type="submit" name="twi2vk_settings[error_action]" value="ignore">
		<input type="submit" name="twi2vk_settings[error_action]" value="reset">
		<?php
	}
	
	if ('ignore'==$settings['error_action'])
	{
		$settings['auth_error'] = 'false';
		$settings['error_action'] = '';
	}
	
	if ('reset'==$settings['error_action'])
	{
		$settings['access_token'] = '';
		$settings['auth_error'] = 'false';
		$settings['error_action'] = '';
	}
	
	$captcha = $settings['captcha'];
	if (count($captcha)>0)
	{
		foreach ($captcha as $key => $value)
		{
			echo twi2vk_enter_captcha($key,$value);
		}
	}
	unset ($settings['captcha']);
	update_option('twi2vk_settings', $settings);
	
	$options = get_option('twi2vk');
	
	?>
		<table>
			<tr>
				<td>Twitter Username</td>
				<td>
					<input type="text" name="twi2vk_settings[twitter_username]" value="<?php echo $settings['twitter_username']?>">
				</td>
				
			</tr>
			<tr>
				<td>VK.com user id</td>
				<td>
					<input type="text" name="twi2vk_settings[owner_id]" value="<?php echo $settings['owner_id']?>">
				</td>
				
			</tr>
			<?php if (''== $settings['access_token']) {?>
			<tr>
				<td>VK auth code</td>
				<td>
					<input type="text" name="twi2vk_settings[auth_code]" value="<?php echo $settings['auth_code']?>">
				</td>
				<td>
					Use <a target="_blank" href="https://oauth.vk.com/authorize?client_id=3034345&scope=wall,offline&redirect_uri=http://api.vk.com/blank.html&display=page&response_type=code">this link</a> to get code. 
				</td>
				
			</tr>
			<?php } ?>
		</table>
	<input type="submit" value="Send"><input type="submit" name="twi2vk_settings[error_action]" value="reset">
	
	<br />
		<table>
		<tr>
		<td>Time checked</td>
		<td>Tweet UID</td>
		<td>Tweet text</td>
		<td>VKontakte response</td>
		</tr>
		<?php
			foreach ($options as $item)
			{
				$reply = json_decode($item['reply'], true);
				
				
				?>
				<tr>
					<td><?php echo $item['time']?></td>
					<td><a href="<?php echo $item['guid']?>"><?php echo $item['uid']?></a></td>
					<td><?php 
					
					
					echo $item['message'];?></td>
					<td><?php 
					
					
					if ($reply['response'] != NULL) 
					{
						echo $reply['response']['post_id'];
					} else {
					
						
						if ($reply['error']['error_code'] == 14)
						{
							?>
							
							
							
							<img src="http://api.vk.com/captcha.php?sid=<?php echo $reply['error']['captcha_sid']; ?>">
							<input type="text" name="twi2vk_settings[captcha][<?php echo $item['uid']?>]">
							<input type="submit" value="Send">
							
							<?php
							echo $reply['error']['captcha_sid'];
						
						
						
						} else {
						
							$item['reply'] = twi2vk_requestVK('wall.post', array(
								'owner_id' => $settings['owner_id'],
								'message' => $item['message'],
								'from_group' => 1,
								'attachments' => $item['attachments'],
							));
	
							twi2vk_posted($item);
							$reply = json_decode($item['reply'],true);
							var_dump($reply);
							
							if ($reply['error']['error_code'] == 5)
							{
								$settings['auth_error'] = 'true';
							}
							
						}
					}
					
					
					?>
					
					</td>
				</tr>
				<?php
			}
		
		?>
		</table>
	</form>
	<?php

}

function twi2vk_activator()	
{
	
	if (! get_option('twi2vk'))
		{
		$option = '';
		add_option('twi2vk', $option,'', 'no' );
		}
	
	for ($i = 0; $i < 6; $i++)
	{
	
		wp_schedule_event(time()+600*$i, 'hourly', 'update_status_in_vk_event');
	
	}
	
	
}
	
register_activation_hook(__FILE__,'twi2vk_activator');

function twi2vk_requestVK($method, $params)
{
	$settings = get_option('twi2vk_settings');
	
	
    $params['access_token'] = $settings['access_token'];
	
	
    $query = http_build_query($params);

    $link = 'https://api.vk.com/method/' . $method . '?' . $query;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $link);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

    $otvet = curl_exec($ch);
    curl_close($ch);
	
	$reply = json_decode($otvet ,true);
	if (5 == $reply['error']['error_code'] )
		{
		$settings['auth_error'] = 'true';
		update_option('twi2vk_settings', $settings);
		}
	
    return $otvet;
}

function get_tigor4eg_twitter(){
	$options = get_option('twi2vk');
	$settings = get_option('twi2vk_settings');
	
	
	
	$xml = simplexml_load_file ( 'http://api.twitter.com/1/statuses/user_timeline.rss?screen_name='.$settings['twitter_username']);
	$i = 0;
	
	
	
	foreach ($xml -> channel ->item as $item)
	{
		$guid = (string)$item->guid;
		
		preg_match('/[0-9]+$/', $guid,$matches);
		$uid = $matches[0];
		
		if (twi2vk_new($uid)) {
			
			$return[$i]['message'] 	= (string)$item->description;
			$return[$i]['guid']		= $guid;
			$return[$i]['uid'] = $uid;
			$return[$i]['time'] = time();
			$i++;
		}
	}
	
	return $return;
}


function twi2vk_new($uid)
{
	
	$options = get_option('twi2vk');
	
	if (isset($options[$uid]))
		{
			
			return false;
		} else {
			
			return true;
		}
	
}

function twi2vk_expandShortUrl($matches) 
{
	$url = $matches[0];
    $headers = get_headers($url, 1);
	$loc = $headers['Location'];
	
	if(is_array($loc))
	{
		// get the highest numeric index
		$key = max(array_keys( $loc)); 
		
		return $loc[$key];
	} else {
		
		return $loc;
	}

    return $headers['Location'];
}

function twi2vk_posted($item)
{
	
	$options = get_option('twi2vk');
	$uid = $item['uid'];
	$min = time();
	$options[$uid] = $item;
	
	if (count($options) > 50) 
	{
		foreach ($options as $key => $sample)
			{
				if ($sample['time'] < $min)
					{
						$g = $key;
						$min = $sample['time'];
						
						
					}
			}
		
		unset ($options[$g]);
	
	}
	update_option('twi2vk', $options);
	return true;
	
}

function update_status_in_vk()
	{
	$options = get_option('twi2vk');
	$twitter = array_reverse(get_tigor4eg_twitter());
	
	
	
	
	foreach ($twitter as $item)
	{
			// Make short urls long
			$pattern = '/(http:\/\/t\.co\/[a-z0-9]+)/i';
			$item['message'] = preg_replace_callback($pattern, 'twi2vk_expandShortUrl',  $item['message']);
			
			preg_match('#http://\S+#',$item['message'],$matches);
			$item['attachments'] = $matches[0];
			
			
			// Replcae @username with http://twitter.com/username
			$pattern = '/@([a-zA-Z0-9]+)/';
			$item['message'] = preg_replace($pattern, 'http://twitter.com/$1 ',  $item['message']);
					
			
			
			
			
			$item['reply'] = post_to_vk($item);
			
			 
			
			
			twi2vk_posted($item);
			
			
		
	}
	
	
	
	
		
	return $return;
	}

function post_to_vk($item)
{
	$settings = get_option('twi2vk_settings');
	
	$result = twi2vk_requestVK('wall.post', array(
            'owner_id' => $settings['owner_id'],
            'message' => $item['message'],
            'from_group' => 1,
            'attachments' => $item['attachments']
        ));
	
	return $result;
}

register_deactivation_hook(__FILE__, 'twi2ck_deactivation');

function twi2ck_deactivation() {
	// Execute on deactivation
	
	wp_clear_scheduled_hook('update_status_in_vk_event');
}




?>