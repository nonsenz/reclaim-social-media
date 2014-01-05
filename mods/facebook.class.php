<?php
class facebook_reclaim_module extends reclaim_module {
    private static $shortname = 'facebook';
//    private static $apiurl= "https://graph.facebook.com/%s/feed/?limit=%s&locale=de&access_token=%s&locale=".get_bloginfo ( 'language' );
    private static $apiurl= "https://graph.facebook.com/%s/feed/?limit=%s&locale=de&access_token=%s";
    private static $count = 20;
    private static $timeout = 15;
    
    private static function get_access_token(){
        $rawData = parent::import_via_curl(sprintf('https://graph.facebook.com/oauth/access_token?client_id=%s&client_secret=%s&grant_type=client_credentials', get_option('facebook_app_id'), get_option('facebook_app_secret')), self::$timeout);
        $pos = strpos($rawData, '=');
        if($pos !== false) {
            $token = substr($rawData, $pos + 1);
            update_option('facebook_oauth_token', $token);
        }
    }     

    public static function register_settings() {
        parent::register_settings(self::$shortname);
        
        register_setting('reclaim-social-settings', 'facebook_username');
        register_setting('reclaim-social-settings', 'facebook_user_id');
        register_setting('reclaim-social-settings', 'facebook_username_slug');
        register_setting('reclaim-social-settings', 'facebook_app_id');
        register_setting('reclaim-social-settings', 'facebook_app_secret');
        register_setting('reclaim-social-settings', 'facebook_oauth_token');
    }

    public static function display_settings() {
?>
        <tr valign="top">
            <th colspan="2"><strong><?php _e('facebook', 'reclaim'); ?></strong></th>
        </tr>
<?php        
        parent::display_settings(self::$shortname);
?>
        <tr valign="top">
            <th scope="row"><?php _e('facebook user ID (548616784)', 'reclaim'); ?></th>
            <td><input type="text" name="facebook_user_id" value="<?php echo get_option('facebook_user_id'); ?>" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('facebook username slug (diplix)', 'reclaim'); ?></th>
            <td><input type="text" name="facebook_username_slug" value="<?php echo get_option('facebook_username_slug'); ?>" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('facebook username (Felix Schwenzel)', 'reclaim'); ?></th>
            <td><input type="text" name="facebook_username" value="<?php echo get_option('facebook_username'); ?>" /></td>
        </tr>        
        <tr valign="top">
            <th scope="row"><?php _e('facebook app id', 'reclaim'); ?></th>
            <td><input type="text" name="facebook_app_id" value="<?php echo get_option('facebook_app_id'); ?>" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('facebook app secret', 'reclaim'); ?></th>
            <td><input type="text" name="facebook_app_secret" value="<?php echo get_option('facebook_app_secret'); ?>" /></td>
        </tr>     
        </tr>
<!--
        <tr valign="top">
            <th scope="row"><?php _e('facebook oauth token (optional)', 'reclaim'); ?></th>
            <td><input type="text" name="facebook_oauth_token" value="<?php echo get_option('facebook_oauth_token'); ?>" /></td>
        </tr>
-->
        <tr valign="top">
            <th scope="row"><?php _e('get facebook permissions', 'reclaim'); ?></th>
            <td>
            <?php
            if ( (get_option('facebook_app_id')!="") && (get_option('facebook_app_secret')!="") ) {
            	echo '<a href="'
            	.plugins_url( '/helper/access_token.php' , dirname(__FILE__) ) .'?app_id='
            	.get_option('facebook_app_id')
            	.'&app_secret='.get_option('facebook_app_secret')
            	.'&reclaim_settings_page='.get_bloginfo('wpurl') . "/wp-admin/admin.php?page=reclaim/reclaim.php"
            	.'">click to get permissions</a>';
            }
            else {
            	echo 'enter facebook app id and facebook app secret';
            }
            ?>
            </td>
        </tr>     



<?php
    }

    public static function import() {
        parent::log(sprintf(__('%s is stale', 'reclaim'), self::$shortname));
        if (!get_option('facebook_oauth_token') && get_option('facebook_app_id') && get_option('facebook_app_secret')) {
            parent::log(sprintf(__('getting FB token', 'reclaim'), self::$shortname));
            self::get_access_token();
        }

        if (get_option('facebook_username') && get_option('facebook_username_slug') &&  get_option('facebook_oauth_token')) {
            parent::log(sprintf(__('BEGIN %s import', 'reclaim'), self::$shortname));
            $rawData = parent::import_via_curl(sprintf(self::$apiurl, get_option('facebook_username_slug'), self::$count, get_option('facebook_oauth_token')), self::$timeout);
            $rawData = json_decode($rawData, true);
            
            $data = self::map_data($rawData);
            parent::insert_posts($data);
            update_option('reclaim_'.self::$shortname.'_last_update', current_time('timestamp'));
            parent::log(sprintf(__('END %s import', 'reclaim'), self::$shortname));
        }
        else parent::log(sprintf(__('%s user data missing. No import was done', 'reclaim'), self::$shortname));
    }       

    private static function map_data($rawData) {
        $data = array();
        foreach($rawData['data'] as $entry){
            if (	(
					/* 
					* filtering
					* we don't want tweets, or ifft-stuff, cause that would
					* duplicate the other feeds (or would it not?)
					*/
                	!isset($entry['application']) || (
                    $entry['application']['name'] != "Twitter" // no tweets
                    && $entry['application']['namespace'] != "rssgraffiti" // no blog stuff
                    && $entry['application']['namespace'] != "ifthisthenthat" // no instagrams and ifttt                            
                    )
                )
                && (
                    !isset($entry['status_type']) || $entry['status_type'] != "approved_friend" // no new friend anouncements
                )
                && ($entry['privacy']['value'] == "" || $entry['privacy']['value'] == "EVERYONE") // privacy OK? is it public?
                && $entry['from']['id'] == get_option('facebook_user_id') // only own stuff $user_namestuff
            ) {
            
            		/*
            		* OK, everything is filtered now, lets proceed ...
            		*/
                $link = self::get_link($entry);
                $image = self::get_image_url($entry);
                $title = self::get_title($entry);
                $excerpt = self::get_excerpt($entry, $link, $image);
                $post_format = self::get_post_format($entry);
                if ($post_format=="link") {$title = $entry['name'];}
                
                $data[] = array(                
                    'post_author' => get_option(self::$shortname.'_author'),
                    'post_category' => array(get_option(self::$shortname.'_category')),
	                'post_format' => $post_format,
                    'post_date' => date('Y-m-d H:i:s', strtotime($entry["created_time"])),
                    'post_content' => $excerpt,
//                    'post_excerpt' => $excerpt,
                    'post_title' => reclaim_text_add_more(reclaim_text_excerpt($title, 50, 0, 1, 0),' …', ''),
                    'post_type' => 'post',
                    'post_status' => 'publish',
                    'ext_permalink' => $link,
                    'ext_image' => $image,
                    'ext_guid' => $entry["id"]                
                );
            }
        }    
        return $data;
    }

    private static function get_post_format($entry) {
			if ($entry['type']=="link") {
            	$post_format = "link";
            }
			elseif ($entry['type']=="photo") {
            	$post_format = "image";
            }
			elseif ($entry['type']=="status") {
            	$post_format = "status";
	            if (!isset($entry["story"]) || $entry["story"] == "") {
	            	$post_format = "aside";
	            }
            }
			elseif ($entry['type']=="video") {
            	$post_format = "video";
            }
            else {
	           	$post_format = "aside";
            }
        return $post_format;
	}
    private static function get_link($entry) {
        if (isset($entry["link"])) {
            $link = htmlentities($entry["link"]);
        } else {
            $id = substr(strstr($entry['id'], '_'),1);
            $link = "https://www.facebook.com/".get_option('facebook_username_slug')."/posts/".$id;                
        } 
        return $link;
    } 
    
    private static function get_image_url($entry) {
        $image = '';
        if (isset($entry['picture'])) {
            $image = $entry['picture'];
            if ($image) {
                $parse_image_url = parse_url($image);
                if (isset($parse_image_url['query']) && $parse_image_url['query'])  {
                    $parts = explode('&', $parse_image_url['query']); 
                    foreach ($parts as $p) {
                        $item = explode('=', $p);
                        if ($item[0] == 'url') $image = urldecode($item[1]);                        
                    } 
                }
            }
        }
		//get larger image instead of _s (small)
		$image = str_replace( '_s.', '_n.', $image );
		$image = str_replace( '_q.', '_n.', $image );
        return $image;
    }
    
    private static function get_title($entry) {
        if (isset($entry["story"]) && $entry["story"]) {
            $title = $entry['story'];
        }
        elseif (isset($entry["message"]) && $entry["message"]) {
            $title = $entry['message'];
        }
        else {
            $title = __('Facebook activity', 'reclaim');
        }
        return $title;
    }
    
    private static function get_excerpt($entry, $link = '', $image  = ''){
        $description = "";
       	$post_format = "";
		if ($image == "http://www.facebook.com/images/devsite/attachment_blank.png") {$image ="";}
        
        if (isset($entry["story"]) && $entry["story"]) { // story
            $description .= $entry["story"];
            if (isset($entry["message"]) && $entry["message"]) {
                $description .= '<blockquote class="fb-story">'.$entry["message"].'</blockquote>';
            }
        }        
        elseif (isset($entry['application']) && $entry['application']['name']=='Likes') { // likes?
            if (isset($entry['name']) && $entry['name']) { 
                $entry_name = $entry['name']; 
            } 
            else {
                $entry_name = __('multiple items', 'reclaim');  // manchmal liefert fb nix (nochmal id checken?)
            }
			// facebook_username = "Felix Schwenzel", facebook_user_id = "548616784", facebook_username_slug = "diplix"
            $description = "like. ";
            $description .= sprintf(__('%s liked <a href="%s">%s</a>', 'reclaim'), get_option('facebook_username'), $link, $entry_name);            
        }
        elseif (isset($entry["type"]) && $entry["type"] == 'status') { // status?
            if (!isset($entry["story"]) || $entry["story"] == "") {  // no story?
                if (isset($entry["message"])) {
                    $description .= $entry["message"];
                }
            } else { // story?
                $description = $entry["story"];
                if (isset($entry["message"])) {
                    $description = '<blockquote>'.$entry["message"].'</blockquote>';
                }
            }
        }
        else {        
            if (isset($entry["message"])) {
                $description = '<div class="fbmessage">'.$entry["message"].'</div>';
            }
        }
		
		$description = make_clickable($description);
		//now other's content
		$fblink_description = "";
        if ($image) {
            $fblink_description .= '<div class="fbimage"><img src="'.$image.'"></div>';
        }
        if (isset($entry['name']) && $entry['name']) {
            $fblink_description .= '<div class="fblink-title"><a href="'.$link.'">'.$entry["name"].'</a></div>';
        }
        if (isset($entry['properties']) && $entry['properties']) {
			$fblink_description .= '<div class="fblink-title props"><a href="'.$entry['properties'][0]['href'].'">'.$entry['properties'][0]['name'].' '.$entry['properties'][0]['text'].'</a></div>';
		}
        if (isset($entry["caption"]) && $entry["caption"]) {
	        if (isset($entry["description"]) && $entry["description"]) {
				$fblink_description .=	'<p class="clearfix fblink-caption">'.$entry["caption"].'</p>';
			}
			else {
				$fblink_description .=	'<p class="fblink-description caption">'.$entry["caption"].'</p>';
			}
        }
        if (isset($entry["description"]) && $entry["description"]) {
			$fblink_description .= '<p class="fblink-description">'.$entry["description"].'</p>';
		}
		if (isset($entry['name']) && $entry['name']) {
			$description .= '<blockquote class="clearfix fbname fblink">'.$fblink_description.'</blockquote>'; // other's content
		}

		$fb_link = "https://www.facebook.com/".$entry['from']['id']."/posts/".substr($entry['id'], 10);
		$description .= '<p class="fbviewpost-facebook">(<a href="'.$fb_link.'">'.__('View on Facebook', 'reclaim').'</a>)</p>';
		// add embedcode
		$description = '<div class="fb-post" data-href="'.$fb_link.'" data-width="100%">'
		.$description
		.'</div>';

        return $description;
    }
}
?>