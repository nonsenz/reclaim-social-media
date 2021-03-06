<?php
/*  Copyright 2013-2014 diplix

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

class instagram_reclaim_module extends reclaim_module {
    private static $apiurl= "https://api.instagram.com/v1/users/%s/media/recent/?access_token=%s&count=%s";
    private static $timeout = 15;
    private static $count = 40;
    private static $post_format = 'image'; // or 'status', 'aside'

// callback-url: http://root.wirres.net/reclaim/wp-content/plugins/reclaim/vendor/hybridauth/hybridauth/src/
// new app: http://instagram.com/developer/clients/manage/

    public function __construct() {
        $this->shortname = 'instagram';
    }

    public function register_settings() {
        parent::register_settings($this->shortname);

        register_setting('reclaim-social-settings', 'instagram_user_id');
        register_setting('reclaim-social-settings', 'instagram_user_name');
        register_setting('reclaim-social-settings', 'instagram_client_id');
        register_setting('reclaim-social-settings', 'instagram_client_secret');
        register_setting('reclaim-social-settings', 'instagram_access_token');
    }

    public function display_settings() {
        if ( isset( $_GET['link']) && (strtolower($_GET['mod'])=='instagram') && (isset($_SESSION['hybridauth_user_profile']))) {
            $user_profile       = json_decode($_SESSION['hybridauth_user_profile']);
            $user_access_tokens = json_decode($_SESSION['hybridauth_user_access_tokens']);
            $error = $_SESSION['e'];

            if ($error) {
                echo '<div class="error"><p><strong>Error:</strong> ',esc_html( $error ),'</p></div>';
            }
            else {
                update_option('instagram_user_id', $user_profile->identifier);
                update_option('instagram_user_name', $user_profile->displayName);
                update_option('instagram_access_token', $user_access_tokens->access_token);
            }
//            print_r($_SESSION);
//            echo "<pre>" . print_r( $user_profile, true ) . "</pre>" ;
//            echo $user_access_tokens->accessToken;
//            echo $user_profile->displayName;
            if(session_id()) {
                session_destroy ();
            }
        }
?>
        <tr valign="top">
            <th colspan="2"><h3><?php _e('instagram', 'reclaim'); ?></h3></th>
        </tr>
<?php
        parent::display_settings($this->shortname);
?>
        <tr valign="top">
            <th scope="row"><?php _e('Instagram user id', 'reclaim'); ?></th>
            <td><p><?php echo get_option('instagram_user_id'); ?></p>
            <input type="hidden" name="instagram_user_id" value="<?php echo get_option('instagram_user_id'); ?>" />
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('Instagram user name', 'reclaim'); ?></th>
            <td><p><?php echo get_option('instagram_user_name'); ?></p>
            <input type="hidden" name="instagram_user_name" value="<?php echo get_option('instagram_user_name'); ?>" />
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('Instagram client id', 'reclaim'); ?></th>
            <td><input type="text" type="password" name="instagram_client_id" value="<?php echo get_option('instagram_client_id'); ?>" />
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('Instagram client secret', 'reclaim'); ?></th>
            <td><input type="text" type="password" name="instagram_client_secret" value="<?php echo get_option('instagram_client_secret'); ?>" />
            <input type="hidden" name="instagram_access_token" value="<?php echo get_option('instagram_access_token'); ?>" />
            <p class="description">Get your Instagram client and credentials <a href="http://instagram.com/developer/">here</a>. Use <code><?php echo plugins_url('reclaim/vendor/hybridauth/hybridauth/hybridauth/') ?></code> as "OAuth redirect_uri"</p>
            </td>
        </tr>

        <tr valign="top">
            <th scope="row"></th>
            <td>
            <?php
            if (
            (get_option('instagram_client_id')!="")
            && (get_option('instagram_client_secret')!="")

            ) {
                $link_text = __('Authorize with Instagram', 'reclaim');
                // && (get_option('facebook_oauth_token')!="")
                if ( (get_option('instagram_user_id')!="") && (get_option('instagram_access_token')!="") ) {
                    echo sprintf(__('<p>Instagram authorized as %s</p>', 'reclaim'), get_option('instagram_user_name'));
                    $link_text = __('Authorize again', 'reclaim');
                }

                // send to helper script
                // put all configuration into session
                // todo
                $config = self::construct_hybridauth_config();
                $callback =  urlencode(get_bloginfo('wpurl') . '/wp-admin/options-general.php?page=reclaim/reclaim.php&link=1&mod='.$this->shortname);

                $_SESSION[$this->shortname]['config'] = $config;
//                $_SESSION[$this->shortname]['mod'] = $this->shortname;


            	echo '<a class="button button-secondary" href="'
            	.plugins_url( '/helper/hybridauth/hybridauth_helper.php' , dirname(__FILE__) )
            	.'?'
            	.'&mod='.$this->shortname
            	.'&callbackUrl='.$callback
            	.'">'.$link_text.'</a>';

            }
            else {
            	echo _e('enter instagram app id and secret', 'reclaim');
            }
            ?>
            </td>
        </tr>

<?php
    }

    public function construct_hybridauth_config() {
        $config = array(
            // "base_url" the url that point to HybridAuth Endpoint (where the index.php and config.php are found)
            "base_url" => plugins_url('reclaim/vendor/hybridauth/hybridauth/hybridauth/'),
            "providers" => array (
                "Instagram" => array(
                    "enabled" => true,
                    "keys"    => array ( "id" => get_option('instagram_client_id'), "secret" => get_option('instagram_client_secret') ),
                    "wrapper" => array(
                        "path"  => dirname( __FILE__ ) . '/../vendor/hybridauth/hybridauth/additional-providers/hybridauth-instagram/Providers/Instagram.php',
                        "class" => "Hybrid_Providers_Instagram",
                    ),
                    "scope" => "basic comments",
                ),
            ),
        );
        return $config;
    }

    public function import($forceResync) {
        if (get_option('instagram_user_id') && get_option('instagram_access_token') ) {
            $rawData = parent::import_via_curl(sprintf(self::$apiurl, get_option('instagram_user_id'), get_option('instagram_access_token'), self::$count), self::$timeout);
            $rawData = json_decode($rawData, true);

            if ($rawData) {
            	$data = self::map_data($rawData);
            	parent::insert_posts($data);
            	update_option('reclaim_'.$this->shortname.'_last_update', current_time('timestamp'));
            	parent::log(sprintf(__('END %s import', 'reclaim'), $this->shortname));
            }
            else parent::log(sprintf(__('%s returned no data. No import was done', 'reclaim'), $this->shortname));
        }
        else parent::log(sprintf(__('%s user data missing. No import was done', 'reclaim'), $this->shortname));
    }

    private function map_data($rawData) {
        $data = array();
        //echo '<li><a href="'.$record->permalinkUrl.'">'.$record->description.' @ '.$record->venueName.'</a></li>';
        foreach($rawData['data'] as $entry){
            $description = $entry['caption']['text'];
            $venueName = $entry['location']['name'];
            if (isset($description) && isset($venueName)) {
            	$title = $description . ' @ ' . $venueName;
            }
            elseif ( isset($description) && !isset($venueName)) {
            	$title = $description;
            }
            else {
            	$title = ' @ ' . $venueName;
            }
            // save geo coordinates?
            // "location":{"latitude":52.546969779,"name":"Simit Evi - Caf\u00e9 \u0026 Simit House","longitude":13.357669574,"id":17207108},
            // http://codex.wordpress.org/Geodata
            $lat = $entry['location']['latitude'];
            $lon = $entry['location']['longitude'];

            // save meta like this?

            $post_meta["geo_latitude"] = $lat;
            $post_meta["geo_longitude"] = $lon;

            $id = $entry["link"];
            $link = $entry["link"];
            $image_url = $entry['images']['standard_resolution']['url'];
            $tags = $entry['tags']; // not sure if that works
            $filter = $entry['filter'];
            $tags[] = 'filter:'.$filter;

            $content = self::construct_content($entry,$id,$image_url,$title);
            $content_type = "constructed";
            if ($entry['type']=='video') {
                // what to do with videos?
                // post format, show embed code instead of pure image
                // todo: get that video file and show it nativly in wp
                // $entry['videos']['standard_resolution']['url']
                self::$post_format = 'video';
                $content_type = "embed_code"; // use instagram embed code?
                $content_type = "constructed";
            }
            else {
                self::$post_format = 'image';
            }

            $post_meta["_".$this->shortname."_link_id"] = $entry["id"];
            $post_meta["_post_generator"] = $this->shortname;

            $data[] = array(
                'post_author' => get_option($this->shortname.'_author'),
                'post_category' => array(get_option($this->shortname.'_category')),
                'post_format' => self::$post_format,
                'post_date' => get_date_from_gmt(date('Y-m-d H:i:s', $entry["created_time"])),
                'post_content' => $content[$content_type],
                'post_title' => $title,
                'post_type' => 'post',
                'post_status' => 'publish',
                'tags_input' => $tags,
                'ext_permalink' => $link,
                'ext_image' => $image_url,
                'ext_embed_code' => $content['embed_code'],
                'ext_guid' => $id,
                'post_meta' => $post_meta
            );

        }
        return $data;
    }

    private function construct_content($entry,$id,$image_url,$description) {
        $post_content_original = htmlentities($description);
        
/*
        $post_content_constructed = 'ich habe ein vine-video hochgeladen.'
            .'<a href="'.$entry['permalinkUrl'].'"><img src="'.$image_url.'" alt="'.$description.'"></a>';
*/
        if ($entry['type']=='image') {
            $post_content_constructed = 
                 'ich habe <a href="'.$entry['link'].'">ein instagram</a> hochgeladen.'
//                .'<a href="'.$entry['link'].'">'
                .'<div class="inimage">[gallery size="large" columns="1" link="file"]</div>'
//                .'</a>'
            ;
        } else {
            $post_content_constructed = 
                //'ich habe <a href="'.$entry['link'].'">ein instagram</a> hochgeladen.'
                '[video src="'.$entry['videos']['standard_resolution']['url'].'" poster="'.$image_url.'"]';
        }
        $post_content_constructed .= '<p class="viewpost-instagram">(<a rel="syndication" href="'.$entry['link'].'">'.__('View on Instagram', 'reclaim').'</a>)</p>';

        // instagram embed code:
        // <iframe src="//instagram.com/p/jD91oVoLab/embed/" width="612" height="710" frameborder="0" scrolling="no" allowtransparency="true"></iframe>
        $embed_code = '<frameset><iframe class="instagram-embed" src="'.$entry['link'].'embed/" width="612" height="710" frameborder="0" scrolling="no" allowtransparency="true"></iframe>'
            .'<noframes>'
            //.'ich habe <a href="'.$entry['link'].'">ein instagram</a> hochgeladen.'
            //.'<a href="'.$entry['link'].'">'
            .'<div class="inimage">[gallery size="large" columns="1" link="file"]</div>'
            .'<p class="viewpost-instagram">(<a rel="syndication" href="'.$entry['link'].'">'.__('View on Instagram', 'reclaim').'</a>)</p>'
            //.'</a>'
            .'</noframes></frameset>';

        $content = array(
            'original' =>  $post_content_original,
            'constructed' =>  $post_content_constructed,
            'embed_code' => $embed_code,
            'image' => $image_url
        );

        return $content;
    }
}
