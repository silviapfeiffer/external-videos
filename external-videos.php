<?php
/*
 * Plugin Name: External Videos
 * Plugin URI: http://wordpress.org/
 * Description: This is a WordPress post types plugin for videos posted to external social networking sites. It creates a new WordPress post type called "External Videos" and aggregates videos from a external social networking site's user channel to the WordPress instance. For example, it finds all the videos of the user "Fred" on YouTube and addes them each as a new post type.
 * Author: Silvia Pfeiffer
 * Version: 0.4
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
  @copyright  Copyright 2010 Silvia Pfeiffer
  @license    http://www.gnu.org/licenses/gpl.txt GPL 2.0
  @version    0.1
  @link       http://www.gingertech.net/

*/

$features_3_0 = false;

if (version_compare($wp_version,"3.0",">=")) {
    $features_3_0 = true;
}

require_once(ABSPATH . 'wp-admin/includes/taxonomy.php');
require_once(WP_PLUGIN_DIR . '/external-videos/helpers.php');
require_once(WP_PLUGIN_DIR . '/external-videos/video_sites_fetching.php');
require_once(WP_PLUGIN_DIR . '/external-videos/vimeo_library.php');
require_once(WP_PLUGIN_DIR . '/external-videos/ev-widget.php');
require_once(WP_PLUGIN_DIR . '/external-videos/simple_html_dom.php');

if ($feature_3_0) {
    require_once(WP_PLUGIN_DIR . '/external-videos/media_gallery.php');
}


/// ***   Pulling Videos From Diverse Sites   *** ///

function save_video($video) {

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

    // category id
    $category_id = get_cat_ID('Videos');

    // put content together
    $video_content .= $video['videourl'];
    $video_content .= "\n<p>".$video['description']."</p>";
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
    $video_post['post_category']  = array($category_id);
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

    return true;
}

function update_videos($authors) {
    $new_videos = 0;

    foreach ($authors as $author) {
        switch ($author['host_id']) {
            case 'youtube':
                $new_videos += fetch_youtube_videos($author['author_id']);
                break;
            case 'vimeo':
                $new_videos += fetch_vimeo_videos($author['author_id'],
                                                  $author['developer_key'],
                                                  $author['secret_key']);
                break;
            case 'dotsub':
                $new_videos += fetch_dotsub_videos($author['author_id']);
                break;
        }
    }

    return $new_videos;
}


/// ***   Admin Settings Page   *** ///

function remote_author_exists($host_id, $author_id) {
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

    if (!$headers || preg_match('/^[45]/', $headers['response']['code'])  ) {
        return false;
    }

    return true;
}

function local_author_exists($host_id, $author_id, $authors) {
    foreach ($authors as $author) {
        if ( $author['author_id'] == $author_id && $author['host_id'] == $host_id ) {
            return true;
        }
    }

    return false;
}

function authorization_exists($host_id, $developer_key, $secret_key) {
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

function settings_page() {
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
          if (!confirm("Are you sure you want to delete")) {
              return false;
          }
          jQuery('#delete_author [name="host_id"]').val(host_id);
          jQuery('#delete_author [name="author_id"]').val(author_id);
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

    $raw_options = get_option('external_videos_options');

    $options = $raw_options == "" ? array('version' => 1, 'authors' => array()) : $raw_options;


    if ($_POST['external_videos'] == 'Y' ) {
        if ($_POST['action'] == 'add_author') {
            if (!array_key_exists($_POST['host_id'], $VIDEO_HOSTS)) {
                ?><div class="error"><p><strong><?php echo __('Invalid video host.'); ?></strong></p></div><?php
            }
            elseif (local_author_exists($_POST['host_id'], $_POST['author_id'], $options['authors'])) {
                ?><div class="error"><p><strong><?php echo __('Author already exists.'); ?></strong></p></div><?php
            }
            elseif (!remote_author_exists($_POST['host_id'], $_POST['author_id'])) {
                ?><div class="error"><p><strong><?php echo __('Invalid author.'); ?></strong></p></div><?php
            }
            elseif (!authorization_exists($_POST['host_id'],$_POST['developer_key'],$_POST['secret_key'])) {
              ?><div class="error"><p><strong><?php echo __('Missing developer key.'); ?></strong></p></div><?php              
            }
            else {
                $options['authors'][] = array('host_id' => $_POST['host_id'], 
                                              'author_id' => $_POST['author_id'],
                                              'developer_key' => $_POST['developer_key'],
                                              'secret_key' => $_POST['secret_key']);
                update_option('external_videos_options', $options);
                ?><div class="updated"><p><strong><?php echo __('Added author.'); ?></strong></p></div><?php
            }
        }
        elseif ($_POST['action'] == 'delete_author') {
            if (!local_author_exists($_POST['host_id'], $_POST['author_id'], $options['authors'])) {
                ?><div class="error"><p><strong><?php echo __("Can't delete an author that doesn't exist."); ?></strong></p></div><?php
            }
            else {
                foreach ($options['authors'] as $key => $author) {
                    if ($author['host_id'] == $_POST['host_id'] && $author['author_id'] == $_POST['author_id']) {
                        unset($options['authors'][$key]);
                    }
                }
                $options['authors'] = array_values($options['authors']);
                // TODO (JF) - Also remove the videos from the posts table
                update_option('external_videos_options', $options);
                ?><div class="updated"><p><strong><?php echo __('Deleted author.'); ?></strong></p></div><?php
            }
        }
        elseif ($_POST['action'] == 'update_videos') {
            $num_videos = update_videos($options['authors']);
            ?><div class="updated"><p><strong><?php printf(__("Found %d videos."), $num_videos); ?></strong></p></div><?php
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
            (required for Vimeo, leave empty else)
        <p>
            Secret Key:
            <input type="text" name="secret_key" value="<?php echo $_POST['secret_key'] ?>"/>
            (required for Vimeo, leave empty else)
        </p>
        <p class="submit">
            <input type="submit" name="Submit" value="Add new author" />
        </p>
    </form>

    <h3>Update Videos</h3>
    <form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">
        <input type="hidden" name="external_videos" value="Y" />
        <input type="hidden" name="action" value="update_videos" />
        <p class="submit">
            <input type="submit" name="Submit" value="Add New Videos from Channels" />
        </p>
    </form>
    <p>Next automatic update scheduled for:
      <i><?php echo date('Y-m-d H:i:s', wp_next_scheduled('external_videos_daily_event')) ?></i>
    </p>

    </div>
    <?php
}

function external_videos_options() {
  add_options_page('External Videos Options','External Videos', 10, __FILE__, 'settings_page');
}


/// ***   Short Code   *** ///

// handlex [external-videos] shortcode
function external_videos_gallery($attr, $content = null) {
  global $wp_query, $post;
  
  // start output buffer collection
  ob_start();

  $params = array(
    'show_posts'  => 20,
    'post_type'   => 'external-videos',
    'post_status' => 'publish',
  );
  $old_params = $wp_query->query;
  $params['paged'] = $old_params['paged'];
   
  query_posts($params);
  ?>

  <?php // if ( $wp_query->max_num_pages > 1 ) : ?>
  <div id="nav-above" class="navigation">
    <div class="nav-previous"><?php next_posts_link( __( '<span class="meta-nav">&larr;</span> Older videos', 'twentyten' ) ); ?></div>
    <div class="nav-next"><?php previous_posts_link( __( 'Newer videos <span class="meta-nav">&rarr;</span>', 'twentyten' ) ); ?></div>
  </div><!-- #nav-above -->
  <?php // endif; ?>

  <div class="gallerycontainer">
    <?php
    while ( have_posts() ) {
      the_post();
      $thumbnail = get_post_meta(get_the_ID(), 'thumbnail_url');
      $thumb = $thumbnail[0];
      $videourl  = get_post_meta(get_the_ID(), 'video_url');
      $video = trim($videourl[0]);
      $description = get_post_meta(get_the_ID(), 'description');
      $desc = $description[0];
      $short_title = shorten_text(get_the_title(), 33);
      $thickbox_title = shorten_text(get_the_title(), 90);
      // get oEmbed code
      $oembed = new WP_Embed();
      $html = $oembed->shortcode(null, $video);
      // replace width with 600, height with 360
      $html = preg_replace ('/width="\d+"/', 'width="600"', $html);
      $html = preg_replace ('/height="\d+"/', 'height="360"', $html);
    ?>
    <div style="margin:2px; height:auto; width:auto; float:left;">
      <a href="#TB_inline?height=500&width=700&inlineId=hiddenModalContent_<?php the_ID() ?>"
         title="<?php echo $thickbox_title ?>" class="thickbox">
        <img title="<?php the_title() ?>" src="<?php echo $thumb ?>" width="120px" height="90px"
             style="display:inline; margin:0; border:1px solid black;"/>
      </a>
      <div style="width:120px; height: 12px; margin-bottom:7px; line-height: 90%">
        <small><i><?php echo get_the_time('F j, Y') ?></i></small>
      </div>
      <div style="width:120px; height: 30px; margin-bottom:20px; line-height: 80%">
        <small><?php echo $short_title ?></small>
      </div>
      <!-- Hidden content for the thickbox -->
      <div id="hiddenModalContent_<?php echo $post->ID ?>" style="display:none;">
        <p align="center"  style="margin-bottom:10px;">
          <?php echo $html ?>
        </p>
        <div style="margin-bottom:10px;">
          <?php
          if ($post->post_parent > 0) {
          ?>
            <a href="<?php echo get_permalink($post->post_parent) ?>">Blog post related to this video</a>
          <?php
          }
          ?>
          <br/>
          <?php
          if ($feature_3_0) {
          ?>
          <a href="<?php the_permalink() ?>">Video page</a>
          <?php
          }
          ?>
        </div>
        <div style="margin-bottom:10px;">
          <?php echo $desc ?>
        </div>
        <div style="text-align: center;">
          <input type="submit" id="Login" value="OK" onclick="tb_remove()"/>
        </div>
      </div>
    </div>
    <?php
    }
    ?>
    <div style="clear: both;"></div>
  </div>

  <?php // if ( $wp_the_query->max_num_pages > 1 ) : ?>
    <div id="nav-below" class="navigation">
      <div class="nav-previous"><?php next_posts_link( __( '<span class="meta-nav">&larr;</span> Older videos', 'twentyten' ) ); ?></div>
      <div class="nav-next"><?php previous_posts_link( __( 'Newer videos <span class="meta-nav">&rarr;</span>', 'twentyten' ) ); ?></div>
    </div><!-- #nav-below -->
  <?php // endif; ?>
  <?php
  //Reset Query
  wp_reset_query();
  $result = ob_get_clean();
  return $result;
}


/// ***   Admin Interface to External Videos   *** ///

function external_videos_columns($columns)
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

function external_videos_custom_columns($column_name)
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
            <strong><a href="<?php echo get_edit_post_link( $post->post_parent ); ?>"><?php echo $title ?></a></strong>, <?php echo get_the_time(__('Y/m/d')); ?>
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

function external_videos_activation() {
  // register daily "cron" on plugin installation
  if (!wp_next_scheduled('external_videos_daily_event')) {
    wp_schedule_event( time(), 'daily', 'external_videos_daily_event' );
  }
}

function external_videos_deactivation() {
  // unregister daily "cron"
  wp_clear_scheduled_hook('external_videos_daily_event');
}

function external_videos_daily_function() {
  $raw_options = get_option('external_videos_options');
  $options = $raw_options == "" ? array('version' => 1, 'authors' => array()) : $raw_options;
  update_videos($options['authors']);
}


/// ***   Setup of Plugin   *** ///

function external_videos_init() {
    // create a "video" category to store posts against
    wp_create_category('Videos');

    // create "external videos" post type
    register_post_type('external-videos', array(
        'label'           => __('External Videos'),
        'singular_label'  => __('External Video'),
        'description'     => ('pulls in videos from external hosting sites'),
        'public'          => true,
        'show_ui'         => true,
        'capability_type' => 'post',
        'hierarchical'    => false,
        'rewrite'         => false,
        'query_var'       => false,
        'supports'        => array('title', 'editor', 'author', 'parent', 'thumbnail', 'excerpts', 'custom-fields', 'comments', 'revisions', 'excerpts')
    ));

    // Oembed support for dotsub
    wp_oembed_add_provider('#http://(www\.)?dotsub\.com/view/.*#i', 'http://api.embed.ly/v1/api/oembed', true);
    
    // enable thickbox use for gallery
    wp_enqueue_style('thickbox');
    wp_enqueue_script('thickbox');

}

add_shortcode('external-videos', 'external_videos_gallery');
add_action('admin_menu', 'external_videos_options');
add_action('init', 'external_videos_init');
add_action('widgets_init', 'external_videos_load_widget');
add_action('manage_posts_custom_column', 'external_videos_custom_columns');
add_filter('manage_edit-external-videos_columns', 'external_videos_columns');
add_action('external_videos_daily_event', 'external_videos_daily_function');
register_activation_hook(__FILE__, 'external_videos_activation');
register_activation_hook(__FILE__, 'external_videos_deactivation');
