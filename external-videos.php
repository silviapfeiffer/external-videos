<?php
/*
 * Plugin Name: External Videos
 * Plugin URI: http://wordpress.org/extend/plugins/external-videos/
 * Description: This is a WordPress post types plugin for videos posted to external social networking sites. It creates a new WordPress post type called "External Videos" and aggregates videos from a external social networking site's user channel to the WordPress instance. For example, it finds all the videos of the user "Fred" on YouTube and addes them each as a new post type.
 * Author: Silvia Pfeiffer
 * Version: 0.14
 * Author URI: http://www.gingertech.net/
 * License: GPL2
 */

/*
  Copyright 2010  Silvia Pfeiffer  (email : silviapfeiffer1@gmail.com)

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
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

  @package    external-videos
  @author     Silvia Pfeiffer <silviapfeiffer1@gmail.com>
  @copyright  Copyright 2010+ Silvia Pfeiffer
  @license    http://www.gnu.org/licenses/gpl.txt GPL 2.0
  @version    0.14
  @link       http://wordpress.org/extend/plugins/external-videos/

*/

global $features_3_0;
$features_3_0 = false;

if (version_compare($wp_version,"3.0",">=")) {
    $features_3_0 = true;
}

require_once(ABSPATH . 'wp-admin/includes/taxonomy.php');
require_once(WP_PLUGIN_DIR . '/external-videos/ev-helpers.php');
require_once(WP_PLUGIN_DIR . '/external-videos/ev-video_sites.php');
require_once(WP_PLUGIN_DIR . '/external-videos/ev-widget.php');
require_once(WP_PLUGIN_DIR . '/external-videos/ev-shortcode.php');
require_once(WP_PLUGIN_DIR . '/external-videos/simple_html_dom.php');
require_once(WP_PLUGIN_DIR . '/external-videos/vimeo_library.php');

if ($features_3_0) {
    require_once(WP_PLUGIN_DIR . '/external-videos/ev-media-gallery.php');
}


/// ***   Pulling Videos From Diverse Sites   *** ///

function sp_ev_save_video($video) {

    // See if video exists
    $the_query = new WP_Query(array(
        'post_type' => 'external-videos',
        'meta_key' => 'video_id',
        'meta_value' => $video['video_id']
    ));
    while($the_query->have_posts()) {
        $query_video = $the_query->next_post();
        if (get_post_meta($query_video->ID, 'host_id', true)) {
            return false;
        }
    }

    // put content together
    $video_content .= "\n";
    $video_content .= $video['videourl'];
    $video_content .= "\n\n";
    $video_content .= "<p>".$video['description']."</p>";
    if ($video['category'] != '') {
      $video_content .= "<p><small><i>Category:</i> ".$video['category'];
      $video_content .= "<br/>";
    }
    $video_content .= "<i>Uploaded by:</i> <a href='".$video['author_url']."'>".$video['authorname']."</a>";
    $video_content .= "<br/>";
    $video_content .= "<i>Hosted:</i> <a href='".$video['videourl']."'>".$video['host_id']."</a>";
    $video_content .= "</small></p>";

    // prepare post
    $video_post = array();
    $video_post['post_type']      = 'external-videos';
    $video_post['post_title']     = $video['title'];
    $video_post['post_content']   = $video_content;
    $video_post['post_status']    = 'publish';
    $video_post['post_author']    = 1;
    $video_post['post_date']      = $video['published'];
    $video_post['tags_input']     = $video['keywords'];
    $video_post['post_mime_type'] = 'import';

    // save to DB
    $post_id = wp_insert_post($video_post);

    // add post meta
    add_post_meta($post_id, 'host_id',       $video['host_id']);
    add_post_meta($post_id, 'author_id',     $video['author_id']);
    add_post_meta($post_id, 'video_id',      $video['video_id']);
    add_post_meta($post_id, 'duration',      $video['duration']);
    add_post_meta($post_id, 'author_url',    $video['author_url']);
    add_post_meta($post_id, 'video_url',     $video['videourl']);
    add_post_meta($post_id, 'thumbnail_url', $video['thumbnail']);
    // Cheat here with a dummy image so we can show thumbnails properly
    add_post_meta($post_id, '_wp_attached_file', 'dummy.png');
    add_post_meta($post_id, 'description',   $video['description']);

    // category id & tag attribution
    $category_id = get_cat_ID('External Videos');
    wp_set_post_categories($post_id, array($category_id));
    wp_set_post_tags($post_id, $video['keywords'], 'post_tag');

    return true;
}

// FIX to render External Video post type entries on Category and Tag archive pages
// by Chris Jean, chris@ithemes.com
add_filter( 'pre_get_posts', 'sp_ev_filter_query_post_type' );
function sp_ev_filter_query_post_type( $query ) {
    if ( $query->query_vars['suppress_filters'] || ( ! is_category() && ! is_tag() ) )
        return $query;
   
    $post_type = get_query_var( 'post_type' );
   
    if ( 'any' == $post_type )
        return $query;
   
    if ( empty( $post_type ) ) {
        $post_type = 'any';
    }
    else {
        if ( ! is_array( $post_type ) )
            $post_type = array( $post_type );
       
        $post_type[] = 'external-videos';
    }
   
    $query->set( 'post_type', $post_type );
   
    return $query;
}

function sp_ev_update_videos($authors) {
  $current_videos = sp_ev_get_all_videos($authors);
  
  if (!$current_videos) return 0;
  
  // save new videos & determine list of all current video_ids
  $num_videos = 0;
  $video_ids = array();
  foreach ($current_videos as $video) {
    array_push($video_ids, $video['video_id']);
    $is_new = sp_ev_save_video($video);
    if ($is_new) {
        $num_videos++;
    }
  }
  
  // remove deleted videos
  $del_videos = 0;
  $all_videos = new WP_Query(array('post_type'  => 'external-videos',
                                   'nopaging' => 1));
  while($all_videos->have_posts()) {
    $old_video = $all_videos->next_post();
    $video_id  = get_post_meta($old_video->ID, 'video_id', true);
    if (!in_array($video_id, $video_ids)) {
      wp_delete_post($old_video->ID, false);
      $del_videos += 1;      
    }
  }
  if ($del_videos > 0) {
    echo "Note: $del_videos video(s) were deleted on external host and thus removed from this collection.";
  }
  
  return $num_videos;
}

function sp_ev_get_all_videos($authors) {
    $new_videos = array();

    foreach ($authors as $author) {
        switch ($author['host_id']) {
            case 'youtube':
                $videos = sp_ev_fetch_youtube_videos($author['author_id']);
                break;
            case 'vimeo':
                $videos = sp_ev_fetch_vimeo_videos($author['author_id'],
                                                  $author['developer_key'],
                                                  $author['secret_key']);
                break;
            case 'dotsub':
                $videos = sp_ev_fetch_dotsub_videos($author['author_id']);
                break;
        }
        // append $videos to the end of $new_videos
        array_splice($new_videos, count($new_array), 0, $videos);
    }

    return $new_videos;
}

function sp_ev_delete_videos() {
    $del_videos = 0;
    // query all post types of external videos
    $ev_posts = new WP_Query(array('post_type' => 'external-videos',
                                   'nopaging' => 1));
    while ($ev_posts->have_posts()) : $ev_posts->the_post();
      wp_delete_post(get_the_ID(), false);
      $del_videos += 1;
    endwhile;
    
    return $del_videos;
}

// ADDS POST TYPES TO RSS FEED
add_filter('request', 'sp_ev_feed_request');
function sp_ev_feed_request($qv) {
  $raw_options = get_option('sp_external_videos_options');
  $options = $raw_options == "" ? array('version' => 1, 'authors' => array(), 'rss' => false) : $raw_options;
  if ($options['rss'] == true) {
  	if (isset($qv['feed']) && !isset($qv['post_type']))
  		$qv['post_type'] = array('external-videos', 'post');
  }
	return $qv;
}

/// ***   Admin Settings Page   *** ///

function sp_ev_remote_author_exists($host_id, $author_id) {
    $url = null;
    switch ($host_id) {
        case 'youtube':
            $url = "http://www.youtube.com/$author_id";
            break;
        case 'vimeo':
            $url = "http://www.vimeo.com/$author_id";
            break;
        case 'dotsub':
            $url = "http://dotsub.com/view/user/$author_id";
            break;
    }
    $headers = wp_remote_request($url);
    
    if( is_wp_error( $headers ) ) {
      // return false on error
      return false;
    }

    if (!$headers || preg_match('/^[45]/', $headers['response']['code'])  ) {
        return false;
    }

    return true;
}

function sp_ev_local_author_exists($host_id, $author_id, $authors) {
    foreach ($authors as $author) {
        if ( $author['author_id'] == $author_id && $author['host_id'] == $host_id ) {
            return true;
        }
    }

    return false;
}

function sp_ev_authorization_exists($host_id, $developer_key, $secret_key) {
  switch ($host_id) {
      case 'youtube':
          return true;
      case 'vimeo':
          if ($developer_key == "" or $secret_key == "") {
            return false;
          }
      case 'dotsub':
          return true;
  }
  
  return true;
}

function sp_ev_settings_page() {
    // activate cron hook if not active
    sp_ev_activation();
    
    wp_enqueue_script('jquery');
    ?>
    <form id="delete_author" method="post" action="<?php echo $_SERVER["REQUEST_URI"] ?>" style="display: none">
        <input type="hidden" name="external_videos" value="Y" />
        <input type="hidden" name="action" value="delete_author" />
        <input type="hidden" name="host_id" />
        <input type="hidden" name="author_id" />
    </form>

    <script type="text/javascript">
        function delete_author(host_id, author_id) {
          jQuery('#delete_author [name="host_id"]').val(host_id);
          jQuery('#delete_author [name="author_id"]').val(author_id);
          var confirmtext = "Are you sure you want to remove "+author_id+" on "+host_id +"?";
          if (!confirm(confirmtext)) {
              return false;
          }
          jQuery('#delete_author').submit();
        }
    </script>
 
    <div class="wrap">
        <h2>External Videos Settings</h2>
    <?php

    $VIDEO_HOSTS = array(
        'youtube' => 'YouTube',
        'vimeo'   => 'Vimeo',
        'dotsub'  => 'DotSub',
    );

    $raw_options = get_option('sp_external_videos_options');

    $options = $raw_options == "" ? array('version' => 1, 'authors' => array(), 'rss' => false) : $raw_options;

    // clean up entered data from surplus white space
    $_POST['author_id'] = trim($_POST['author_id']);
    $_POST['secret_key'] = trim($_POST['secret_key']);
    $_POST['developer_key'] = trim($_POST['developer_key']);

    if ($_POST['external_videos'] == 'Y' ) {
        if ($_POST['action'] == 'add_author') {
            if (!array_key_exists($_POST['host_id'], $VIDEO_HOSTS)) {
                ?><div class="error"><p><strong><?php echo __('Invalid video host.'); ?></strong></p></div><?php
            }
            elseif (sp_ev_local_author_exists($_POST['host_id'], $_POST['author_id'], $options['authors'])) {
                ?><div class="error"><p><strong><?php echo __('Author already exists.'); ?></strong></p></div><?php
            }
            elseif (!sp_ev_remote_author_exists($_POST['host_id'], $_POST['author_id'])) {
                ?><div class="error"><p><strong><?php echo __('Invalid author.'); ?></strong></p></div><?php
            }
            elseif (!sp_ev_authorization_exists($_POST['host_id'],$_POST['developer_key'],$_POST['secret_key'])) {
              ?><div class="error"><p><strong><?php echo __('Missing developer key.'); ?></strong></p></div><?php              
            }
            else {
                $options['authors'][] = array('host_id' => $_POST['host_id'], 
                                              'author_id' => $_POST['author_id'],
                                              'developer_key' => $_POST['developer_key'],
                                              'secret_key' => $_POST['secret_key']);
                update_option('sp_external_videos_options', $options);
                ?><div class="updated"><p><strong><?php echo __('Added author.'); ?></strong></p></div><?php
            }
        }
        elseif ($_POST['action'] == 'delete_author') {
            if (!sp_ev_local_author_exists($_POST['host_id'], $_POST['author_id'], $options['authors'])) {
                ?><div class="error"><p><strong><?php echo __("Can't delete an author that doesn't exist."); ?></strong></p></div><?php
            }
            else {
                foreach ($options['authors'] as $key => $author) {
                    if ($author['host_id'] == $_POST['host_id'] && $author['author_id'] == $_POST['author_id']) {
                        unset($options['authors'][$key]);
                    }
                }
                $options['authors'] = array_values($options['authors']);
                
                // also remove the author's videos from the posts table
                $del_videos = 0;
                $author_posts = new WP_Query(array('post_type'  => 'external-videos',
                                                   'meta_key'   => 'author_id',
                                                   'meta_value' => $_POST['author_id'],
                                                   'nopaging' => 1));
                while($author_posts->have_posts()) {
                  $query_video = $author_posts->next_post();
                  $host = get_post_meta($query_video->ID, 'host_id', true);
                  if ($host == $_POST['host_id']) {
                    wp_delete_post($query_video->ID, false);
                    $del_videos += 1;
                  }
                }
                
                update_option('sp_external_videos_options', $options);
                ?><div class="updated"><p><strong><?php printf(__("Deleted author and its %d videos."), $del_videos); ?></strong></p></div><?php
            }
        }
        elseif ($_POST['action'] == 'sp_ev_update_videos') {
            $num_videos = sp_ev_update_videos($options['authors']);
            ?><div class="updated"><p><strong><?php printf(__("Found %d videos."), $num_videos); ?></strong></p></div><?php
        }
        elseif ($_POST['action'] == 'sp_ev_delete_videos') {
            $num_videos = sp_ev_delete_videos();
            ?><div class="deleted"><p><strong><?php printf(__("Deleted %d videos."), $num_videos); ?></strong></p></div><?php
        }
        elseif ($_POST['action'] == 'ev_settings') {
            ?><div class="update"><p><strong><?php
            
            if ($_POST['ev-rss'] == "rss") {
              printf(__("Added video pages to RSS feed."));
              $options['rss'] = true;
            } else {
              printf(__("Removed video pages from RSS feed.")); 
              $options['rss'] = false;
            }
            update_option('sp_external_videos_options', $options);
            ?></strong></p></div><?php
        }
    }

    ?>
    <h3>Authors</h3>
    <?php
    foreach ($options['authors'] as $author) {
        echo $author['author_id'] . " at " . $VIDEO_HOSTS[$author['host_id']] . 
            " (<a href=\"#\" onclick=\"delete_author('" . $author['host_id'] . "', '" . $author['author_id'] . "');\">Delete</a>) <br />\n";
    }
    ?>

    <h3>Add Authors</h3>
    <form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">
        <input type="hidden" name="external_videos" value="Y" />
        <input type="hidden" name="action" value="add_author" />
        <p>
            Video Host:
            <select name="host_id">
            <?php
            foreach ($VIDEO_HOSTS as $key => $value) {
                echo "<option value=\"$key\">$value";
            }
            ?>
            </select>
        <p>
            Author ID:
            <input type="text" name="author_id" value="<?php echo $_POST['author_id'] ?>"/>
        <p>
            Developer Key:
            <input type="text" name="developer_key" value="<?php echo $_POST['developer_key'] ?>"/>
            (required for <a href="http://vimeo.com/api/docs/getting-started">Vimeo</a>, leave empty else)
        <p>
            Secret Key:
            <input type="text" name="secret_key" value="<?php echo $_POST['secret_key'] ?>"/>
            (required for Vimeo, leave empty else)
        </p>
        <p class="submit">
            <input type="submit" name="Submit" value="Add new author" />
        </p>
    </form>

    <h3>Plugin Settings</h3>
    <form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">
      <input type="hidden" name="external_videos" value="Y" />
      <input type="hidden" name="action" value="ev_settings" />
      <p>
        <?php
        $ev_rss = $options['rss'];
        ?>
        
        <input type="checkbox" name="ev-rss" value="rss" <?php if ($ev_rss == true) echo "checked"; ?>/>
        Add video posts to Website RSS feed
      </p>
      <p class="submit">
          <input type="submit" name="Submit" value="Save" />
      </p>
    </form>
    
    <h3>Update Videos (newly added/deleted videos)</h3>
    <form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">
        <input type="hidden" name="external_videos" value="Y" />
        <input type="hidden" name="action" value="sp_ev_update_videos" />
        <p class="submit">
            <input type="submit" name="Submit" value="Update Videos from Channels" />
        </p>
    </form>
    <p>Next automatic update scheduled for:
      <i><?php echo date('Y-m-d H:i:s', wp_next_scheduled('ev_daily_event')) ?></i>
    </p><br/>

    <h3>Delete All Videos</h3>
    <p>
      Be careful with this option - you will lose all links you have built between blog posts and the video pages.
      This is really only meant as a reset option.
    </p>
    <form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">
        <input type="hidden" name="external_videos" value="Y" />
        <input type="hidden" name="action" value="sp_ev_delete_videos" />
        <p class="submit">
            <input type="submit" name="Submit" value="Remove All Stored Videos From Channels" />
        </p>
    </form>

    </div>
    <?php
}

add_action('admin_menu', 'sp_external_videos_options');
function sp_external_videos_options() {
  add_options_page('External Videos Options','External Videos', 10, __FILE__, 'sp_ev_settings_page');
}


/// ***   Admin Interface to External Videos   *** ///

add_filter('manage_edit-external-videos_columns', 'sp_external_videos_columns');
function sp_external_videos_columns($columns)
{
    $columns = array(
        "cb"          => "<input type=\"checkbox\" />",
        "title"       => "Video Title",
        "thumbnail"   => "Thumbnail",
        "host"        => "Host",
        "duration"    => "Duration",
        "published"   => "Published",
        "parent"      => "Attached to",
        "tags"        => "Tags",
        "comments"    => "Comments",
    );
    return $columns;
}

add_action('manage_posts_custom_column', 'sp_external_videos_custom_columns');
function sp_external_videos_custom_columns($column_name)
{
    global $post;
    $duration  = get_post_meta($post->ID, 'duration');
    $host      = get_post_meta($post->ID, 'host_id');
    $thumbnail = get_post_meta($post->ID, 'thumbnail_url');

    switch($column_name) {
    case 'ID':
        echo $post->ID;
        break;

    case 'thumbnail':
        echo "<img src='".$thumbnail[0]."' width='120px' height='90px'/>";
        break;

    case 'duration':
        echo $duration[0];
        break;

    case 'published':
        echo $post->post_date;
        break;

    case 'host':
        echo $host[0];
        break;

    case 'tags':
        echo $post->tags_input;
        break;

    case 'parent':
        if ( $post->post_parent > 0 ) {
            if ( get_post($post->post_parent) ) {
                $title =_draft_or_post_title($post->post_parent);
            }
            ?>
            <strong><a href="<?php echo get_edit_post_link( $post->post_parent ); ?>"><?php echo $title ?></a></strong>, <?php echo get_the_time(__('Y/m/d')); ?><br/>
            <a class="hide-if-no-js" onclick="findPosts.open('media[]','<?php echo $post->ID ?>');return false;" href="#the-list"><?php _e('Change'); ?></a>
            <?php
        } else {
            ?>
            <?php _e('(Unattached)'); ?><br />
            <a class="hide-if-no-js" onclick="findPosts.open('media[]','<?php echo $post->ID ?>');return false;" href="#the-list"><?php _e('Attach'); ?></a>
            <?php
        }
        break;

    }
}


/// ***   Daily "Cron" Setup   *** ///

register_activation_hook(WP_PLUGIN_DIR . '/external-videos/external-videos.php', 'sp_ev_activation' );
function sp_ev_activation() {
  // register daily "cron" on plugin installation
  if (!wp_next_scheduled('ev_daily_event')) {
    wp_schedule_event( time(), 'daily', 'ev_daily_event' );
  }
}

register_deactivation_hook(WP_PLUGIN_DIR . '/external-videos/external-videos.php', 'sp_ev_deactivation' );
function sp_ev_deactivation() {
  // unregister daily "cron"
  wp_clear_scheduled_hook('ev_daily_event');
}

add_action('ev_daily_event', 'sp_ev_daily_function');
function sp_ev_daily_function() {
  $raw_options = get_option('sp_external_videos_options');
  $options = $raw_options == "" ? array('version' => 1, 'authors' => array(), 'rss' => false) : $raw_options;
  sp_ev_update_videos($options['authors']);
}


/// ***   Setup of Plugin   *** ///

add_action('init', 'sp_external_videos_init');
function sp_external_videos_init() {
    // create a "video" category to store posts against
    wp_create_category('External Videos');

    // create "external videos" post type
    register_post_type('external-videos', array(
        'label'           => __('External Videos'),
        'singular_label'  => __('External Video'),
        'description'     => ('pulls in videos from external hosting sites'),
        'public'          => true,
        'publicly_queryable' => true,
        'show_ui'         => true,
        'capability_type' => 'post',
        'hierarchical'    => false,
        'query_var'       => false,
        'supports'        => array('title', 'editor', 'author', 'thumbnail', 'excerpts', 'custom-fields', 'comments', 'revisions', 'excerpt'),
        'taxonomies'      => array('post_tag', 'category')
    ));

    // Oembed support for dotsub
    wp_oembed_add_provider('#http://(www\.)?dotsub\.com/view/.*#i', 'http://api.embed.ly/v1/api/oembed', true);
    

    // enable thickbox use for gallery
    wp_enqueue_style('thickbox');
    wp_enqueue_script('thickbox');
}

/// *** Setup of Videos Gallery: implemented in ev-media-gallery.php *** ///
add_shortcode('external-videos', 'sp_external_videos_gallery');

/// *** Setup of Widget: implemented in ev-widget.php file *** ///
add_action('widgets_init', 'sp_external_videos_load_widget');

