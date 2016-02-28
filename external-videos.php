<?php
/*
 * Plugin Name: External Videos
 * Plugin URI: http://wordpress.org/extend/plugins/external-videos/
 * Description: This is a WordPress post types plugin for videos posted to external social networking sites. It creates a new WordPress post type called "External Videos" and aggregates videos from a external social networking site's user channel to the WordPress instance. For example, it finds all the videos of the user "Fred" on YouTube and addes them each as a new post type.
 * Author: Silvia Pfeiffer
 * Version: 0.26
 * Author URI: http://www.gingertech.net/
 * License: GPL2
 * Text Domain: external-videos
 * Domain Path: /localization
 */

/*
  Copyright 2010+  Silvia Pfeiffer  (email : silviapfeiffer1@gmail.com)

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
  @version    0.26
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

/// *** vendor includes

set_include_path(WP_PLUGIN_DIR . '/external-videos/google-api-php-client/src/Google');
require_once(WP_PLUGIN_DIR . '/external-videos/google-api-php-client/src/Google/autoload.php');
require_once(WP_PLUGIN_DIR . '/external-videos/vimeo_library.php');


if ($features_3_0) {
    require_once(WP_PLUGIN_DIR . '/external-videos/ev-media-gallery.php');
}


/// ***   Pulling Videos From Diverse Sites   *** ///

function sp_ev_save_video($video) {

    // See if video exists
    $the_query = new WP_Query(array(
        'post_type' => 'external-videos',
        'post_status' => 'any',
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
    $video_content = "\n";
    $video_content .= $video['videourl'];
    $video_content .= "\n\n";
    $video_content .= '<p>'.trim($video['description']).'</p>';
    $video_content .= '<p><small>';
    if ($video['category'] != '') {
      $video_content .= __('<i>Category:</i>', 'external-videos') . ' ' .$video['category'];
      $video_content .= '<br/>';
    }
    $video_content .= __('<i>Uploaded by:</i>', 'external-videos') . ' <a href="'.$video['author_url'].'">'.$video['authorname'].'</a>';
    $video_content .= '<br/>';
    $video_content .= __('<i>Hosted:</i>', 'external-videos') . ' <a href="'.$video['videourl'].'">'.$video['host_id'].'</a>';
    $video_content .= '</small></p>';

    // prepare post
    $video_post = array();
    $video_post['post_type']      = 'external-videos';
    $video_post['post_title']     = $video['title'];
    $video_post['post_content']   = $video_content;
    $video_post['post_status']    = $video['ev_post_status'];
    $video_post['post_author']    = $video['ev_author'];
    $video_post['post_date']      = $video['published'];
    $video_post['tags_input']     = $video['keywords'];
    $video_post['post_mime_type'] = 'import';
    $video_post['post_excerpt']   = trim( strip_tags( $video['description'] ) );

    // save to DB
    $post_id = wp_insert_post($video_post);
    $post = get_post( $post_id );

    // set post format
    if ( current_theme_supports( 'post-formats' ) &&
         post_type_supports( $post->post_type, 'post-formats' )) {
        set_post_format( $post, $video['ev_post_format'] );
    }

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
    add_post_meta($post_id, 'description',   trim($video['description']));
    // video embed code
    add_post_meta($post_id, 'embed_code', sp_ev_embed_code($video['host_id'], $video['video_id']));

    // category id & tag attribution
    wp_set_post_categories($post_id, $video['ev_category']);
    wp_set_post_tags($post_id, $video['keywords'], 'post_tag');

    return true;
}

// FIX to render External Video post type entries on Category and Tag archive pages
// by Chris Jean, chris@ithemes.com
add_filter( 'pre_get_posts', 'sp_ev_filter_query_post_type' );
function sp_ev_filter_query_post_type( $query ) {
    if ( ( isset($query->query_vars['suppress_filters']) && $query->query_vars['suppress_filters'] )  || ( ! is_category() && ! is_tag() && ! is_author() ) )
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

function sp_ev_update_videos($authors, $delete) {
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

  // want to delete externally deleted videos?
  if (!$delete) {
    return $num_videos;
  }

  // remove deleted videos
  $del_videos = 0;
  $all_videos = new WP_Query(array('post_type'  => 'external-videos',
                                   'nopaging' => 1));
  while($all_videos->have_posts()) {
    $old_video = $all_videos->next_post();
    $video_id  = get_post_meta($old_video->ID, 'video_id', true);
    if ($video_id != NULL && !in_array($video_id, $video_ids)) {
      $post = get_post( $query_video->ID );
      $post->post_status = 'trash';
      wp_update_post($post);
      // WP thinks it can just delete it and not move it to trash
      // if below worked, it could replace above three lines of code
      //wp_delete_post($old_video->ID, false);
      $del_videos += 1;
    }
  }
  if ($del_videos > 0) {
    echo sprintf(_n('Note: %d video was deleted on external host and moved to trash in this collection.', 'Note: %d videos were deleted on external host and moved to trash in this collection.', $del_videos, 'external-videos'), $del_videos);
  }

  return $num_videos;
}

function sp_ev_embed_code($site, $video_id) {
  $width = 560;
  $height = 315;
  $url = "";
  switch ($site) {
    case 'youtube':
      $url = "//www.youtube.com/embed/$video_id";
      break;
    case 'vimeo':
      $url = "//player.vimeo.com/video/$video_id";
      break;
    case 'dotsub':
      $url = "//dotsub.com/media/$video_id/embed/";
      break;
    case 'wistia':
      $url = "//fast.wistia.net/embed/iframe/$video_id";
      break;
    default:
      return "";
  }
  return "<iframe src='$url' frameborder='0' width='$width' height='$height' allowfullscreen></iframe>";
}

function sp_ev_get_all_videos($authors) {
    $new_videos = array();
    foreach ($authors as $author) {
        switch ($author['host_id']) {
            case 'youtube':
                $videos = sp_ev_fetch_youtube_videos($author);
                break;
            case 'vimeo':
                $videos = sp_ev_fetch_vimeo_videos($author);
                break;
            case 'dotsub':
                $videos = sp_ev_fetch_dotsub_videos($author);
                break;
            case 'wistia':
                $videos = sp_ev_fetch_wistia_videos($author);
                break;
        }
        // append $videos to the end of $new_videos
        if ($videos) {
          array_merge($new_videos, $videos);
        }
    }

    return $new_videos;
}

function sp_ev_delete_videos() {
    $del_videos = 0;
    // query all post types of external videos
    $ev_posts = new WP_Query(array('post_type' => 'external-videos',
                                   'nopaging' => 1));
    while ($ev_posts->have_posts()) : $ev_posts->the_post();
      $post = get_post( get_the_ID() );
      $post->post_status = 'trash';
      wp_update_post($post);
      $del_videos += 1;
    endwhile;

    return $del_videos;
}

// ADDS POST TYPES TO RSS FEED
add_filter('request', 'sp_ev_feed_request');
function sp_ev_feed_request($qv) {
  $raw_options = get_option('sp_external_videos_options');
  $options = $raw_options == "" ? array('version' => 1, 'authors' => array(), 'rss' => false, 'delete' => true) : $raw_options;
  if ($options['rss'] == true) {
  	if (isset($qv['feed']) && !isset($qv['post_type']))
  		$qv['post_type'] = array('external-videos', 'post');
  }
	return $qv;
}

/// ***   Admin Settings Page   *** ///

function sp_ev_remote_author_exists($host_id, $author_id, $developer_key) {
    $url = null;
    $args = array();
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
        case 'wistia':
            $url = "https://api.wistia.com/v1/account.json";
            $headers = array( 'Authorization' => 'Basic ' . base64_encode( "api:$developer_key" ) );
            $args['headers'] = $headers;
            break;
    }
    $result = wp_remote_request($url, $args);

    if( is_wp_error( $result ) ) {
      // return false on error
      return false;
    }

    if (!$result || preg_match('/^[45]/', $result['response']['code'])  ) {
        return false;
    }

    // for wistia: also check that api key belongs to user account
    if ($host_id == 'wistia') {
      $userUrl = json_decode($result['body'])->url;
      $expectUrl = "http://$author_id.wistia.com";
      if ($userUrl != $expectUrl) {
        return false;
      }
      echo "<div class='updated'><p><strong>Wistia account: make sure to <a href='http://wistia.com/doc/wordpress#using_the_oembed_embed_code'>activate oEmbed</a>.</strong></p></div>";
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
      case 'wistia':
          if ($developer_key == "") {
            return false;
          }
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
          var confirmtext = <?php echo '"'. sprintf(__('Are you sure you want to remove %s on %s?', 'external-videos'), '"+ author_id +"', '"+ host_id +"') .'"'; ?>;
          if (!confirm(confirmtext)) {
              return false;
          }
          jQuery('#delete_author').submit();
        }
    </script>

    <div class="wrap">
        <h2><?php _e('External Videos Settings', 'external-videos'); ?></h2>
    <?php

    $VIDEO_HOSTS = array(
        'youtube' => 'YouTube',
        'vimeo'   => 'Vimeo',
        'dotsub'  => 'DotSub',
        'wistia'  => 'Wistia'
    );

    $raw_options = get_option('sp_external_videos_options');

    $options = $raw_options == "" ? array('version' => 1, 'authors' => array(), 'rss' => false, 'delete' => true) : $raw_options;

    // clean up entered data from surplus white space
    $_POST['author_id'] = isset($_POST['author_id']) ? trim(sanitize_text_field($_POST['author_id'])) : '';
    $_POST['secret_key'] = isset($_POST['secret_key']) ? trim(sanitize_text_field($_POST['secret_key'])) : '';
    $_POST['developer_key'] = isset($_POST['developer_key']) ? trim(sanitize_text_field($_POST['developer_key'])) : '';

    if (isset($_POST['external_videos']) && $_POST['external_videos'] == 'Y' ) {
        if ($_POST['action'] == 'add_author') {
            if (!array_key_exists($_POST['host_id'], $VIDEO_HOSTS)) {
                ?><div class="error"><p><strong><?php echo __('Invalid video host.', 'external-videos'); ?></strong></p></div><?php
            }
            elseif (sp_ev_local_author_exists($_POST['host_id'], $_POST['author_id'], $options['authors'])) {
                ?><div class="error"><p><strong><?php echo __('Author already exists.', 'external-videos'); ?></strong></p></div><?php
            }
            elseif (!sp_ev_authorization_exists($_POST['host_id'],$_POST['developer_key'],$_POST['secret_key'])) {
              ?><div class="error"><p><strong><?php echo __('Missing developer key.', 'external-videos'); ?></strong></p></div><?php
            }
            elseif (!sp_ev_remote_author_exists($_POST['host_id'], $_POST['author_id'], $_POST['developer_key'])) {
                ?><div class="error"><p><strong><?php echo __('Invalid author - check spelling.', 'external-videos'); ?></strong></p></div><?php
            }
            else {
                $options['authors'][] = array('host_id' => $_POST['host_id'],
                                              'author_id' => $_POST['author_id'],
                                              'developer_key' => $_POST['developer_key'],
                                              'secret_key' => $_POST['secret_key'],
                                              'ev_author' => $_POST['user'],
                                              'ev_category' => $_POST['post_category'],
                                              'ev_post_format' => $_POST['post_format'],
                                              'ev_post_status' => $_POST['post_status']);
                update_option('sp_external_videos_options', $options);
                ?><div class="updated"><p><strong><?php echo __('Added author.', 'external-videos'); ?></strong></p></div><?php
            }
        }
        elseif ($_POST['action'] == 'delete_author') {
            if (!sp_ev_local_author_exists($_POST['host_id'], $_POST['author_id'], $options['authors'])) {
                ?><div class="error"><p><strong><?php echo __("Can't delete an author that doesn't exist.", 'external-videos'); ?></strong></p></div><?php
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
                    $post = get_post( $query_video->ID );
                    $post->post_status = 'trash';
                    wp_update_post($post);
                    $del_videos += 1;
                  }
                }

                update_option('sp_external_videos_options', $options);
                unset($_POST['host_id'], $_POST['author_id']);

                ?><div class="updated"><p><strong><?php printf(__('Deleted author and moved its %d video to trash.', 'Deleted author and moved its %d videos to trash.', $del_videos, 'external-videos'), $del_videos); ?></strong></p></div><?php
            }
        }
        elseif ($_POST['action'] == 'sp_ev_update_videos') {
            $num_videos = sp_ev_update_videos($options['authors'], $options['delete']);
            ?><div class="updated"><p><strong><?php printf(__('Found %d video.', 'Found %d videos.', $num_videos, 'external-videos'), $num_videos); ?></strong></p></div><?php
        }
        elseif ($_POST['action'] == 'sp_ev_delete_videos') {
            $num_videos = sp_ev_delete_videos();
            ?><div class="deleted"><p><strong><?php printf(__('Moved %d video into trash.', 'Moved %d videos into trash.', $num_videos, 'external-videos'), $num_videos); ?></strong></p></div><?php
        }
        elseif ($_POST['action'] == 'ev_settings') {
            ?><div class="updated"><p><strong><?php

            if ($_POST['ev-rss'] == "rss") {
              _e('Video pages will appear in RSS feed.','external-videos');
              $options['rss'] = true;
            } else {
              _e('Video pages will not appear in RSS feed.','external-videos');
              $options['rss'] = false;
            }
            ?><br/><?php
            if ($_POST['ev-delete'] == "delete") {
              _e('Externally removed videos will be trashed.','external-videos');
              $options['delete'] = true;
            } else {
              _e('Externally removed videos will be kept.','external-videos');
              $options['delete'] = false;
            }
            update_option('sp_external_videos_options', $options);
            ?></strong></p></div><?php
        }
    }

    ?>
    <h3><?php _e('Authors', 'external-videos'); ?></h3>
    <?php
    foreach ($options['authors'] as $author) {
        echo sprintf(__('%1$s at %2$s', 'external-videos'), $author['author_id'], $VIDEO_HOSTS[$author['host_id']]) . " (<a href=\"#\" onclick=\"delete_author('" . $author['host_id'] . "', '" . $author['author_id'] . "');\">". __('Delete'). "</a>) <br />\n";
    }
    ?>

    <h3><?php _e('Add Publishers', 'external-videos'); ?></h3>
    <form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">
        <input type="hidden" name="external_videos" value="Y" />
        <input type="hidden" name="action" value="add_author" />
        <p>
            <?php _e('Video Host:', 'external-videos'); ?>
            <select name="host_id">
            <?php
            foreach ($VIDEO_HOSTS as $key => $value) {
                echo "<option value=\"$key\">$value";
            }
            ?>
            </select>
            <?php _e('(DotSub is currently broken)'); ?>
        <p>
            <?php _e('Publisher ID:', 'external-videos'); ?>
            <input type="text" name="author_id" value="<?php echo sanitize_text_field($_POST['author_id']) ?>"/>
            <?php _e('(the identifier at the end of the URL; for wistia: domain prefix)', 'external-videos')?>
        <p>
            <?php _e('Developer Key:', 'external-videos'); ?>
            <input type="text" name="developer_key" value="<?php echo sanitize_text_field($_POST['developer_key']) ?>"/>
            <?php _e('(required for Vimeo/Wistia/YouTube, leave empty otherwise)', 'external-videos'); ?>
        <p>
            <?php _e('Secret Key/Application Name:', 'external-videos'); ?>
            <input type="text" name="secret_key" value="<?php echo sanitize_text_field($_POST['secret_key']) ?>"/>
            <?php _e('(required for Vimeo/YouTube, leave empty otherwise)', 'external-videos'); ?>
        </p>
        <p>
          <?php _e('Default WP User', 'external-videos'); ?>
          <?php wp_dropdown_users(); ?>
        </p>
        <p>
            <?php _e('Default Post Category'); ?>
            <?php $selected_cats = array( get_cat_ID('External Videos', 'external-videos') ); ?>
            <ul style="padding-left:20px;">
            <?php wp_category_checklist(0, 0, $selected_cats, false, null, true); ?>
            </ul>
        </p>
        <p>
            <?php _e('Default Post Format'); ?>
            <?php
            $post_formats = get_post_format_strings();
            unset( $post_formats['video'] );
            ?>
            <select name="post_format" id="ev_post_format">
              <option value="video"><?php echo get_post_format_string( 'video' ); ?></option>
            <?php foreach ( $post_formats as $format_slug => $format_name ): ?>
              <option<?php selected( get_option( 'post_format' ), $format_slug ); ?> value="<?php echo esc_attr( $format_slug ); ?>"><?php echo esc_html( $format_name ); ?></option>
            <?php endforeach; ?>
            </select>
        </p>
        <p>
          <?php _e('Set Post Status', 'external-videos'); ?>
          <select name='post_status' id='ev_post_status'>
            <option value='publish' selected><?php _e('Published') ?></option>
            <option value='pending'><?php _e('Pending Review') ?></option>
            <option value='draft'><?php _e('Draft') ?></option>
            <option value='private'><?php _e('Privately Published') ?></option>
            <option value='future'><?php _e('Scheduled') ?></option>
          </select>
        </p>
        <p class="submit">
            <input type="submit" name="Submit" value="<?php _e('Add new author', 'external-videos'); ?>" />
        </p>
    </form>

    <h3><?php _e('Plugin Settings', 'external-videos'); ?></h3>
    <form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">
      <input type="hidden" name="external_videos" value="Y" />
      <input type="hidden" name="action" value="ev_settings" />
      <?php
      $ev_rss = $options['rss'];
      $ev_del = $options['delete'];
      ?>
      <p>
        <input type="checkbox" name="ev-rss" value="rss" <?php if ($ev_rss == true) echo "checked"; ?>/>
        <?php _e('Add video posts to Website RSS feed', 'external-videos'); ?>
      </p>
      <p>
        <input type="checkbox" name="ev-delete" value="delete" <?php if ($ev_del == true) echo "checked"; ?>/>
        <?php _e('Move videos locally to trash when deleted on external site', 'external-videos'); ?>
      </p>
      <p class="submit">
          <input type="submit" name="Submit" value="<?php _e('Save'); ?>" />
      </p>
    </form>

    <h3><?php _e('Update Videos (newly added/deleted videos)', 'external-videos'); ?></h3>
    <form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">
        <input type="hidden" name="external_videos" value="Y" />
        <input type="hidden" name="action" value="sp_ev_update_videos" />
        <p class="submit">
            <input type="submit" name="Submit" value="<?php _e('Update Videos from Channels', 'external-videos'); ?>" />
        </p>
    </form>
    <p><?php _e('Next automatic update scheduled for:', 'external-videos'); ?>
      <i><?php echo date('Y-m-d H:i:s', wp_next_scheduled('ev_daily_event')) ?></i>
    </p><br/>

    <h3><?php _e('Delete All Videos', 'external-videos'); ?></h3>
    <p>
      <?php _e('Be careful with this option - you will lose all links you have built between blog posts and the video pages. This is really only meant as a reset option.', 'external-videos'); ?>.
    </p>
    <form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">
        <input type="hidden" name="external_videos" value="Y" />
        <input type="hidden" name="action" value="sp_ev_delete_videos" />
        <p class="submit">
            <input type="submit" name="Submit" value="<?php _e('Remove All Stored Videos From Channels', 'external-videos'); ?>" />
        </p>
    </form>

    </div>
    <?php
}

add_action('admin_menu', 'sp_external_videos_options');
function sp_external_videos_options() {
  add_options_page(__('External Videos Settings', 'external-videos'), __('External Videos', 'external-videos'), 'edit_posts', __FILE__, 'sp_ev_settings_page');
}


/// ***   Admin Interface to External Videos   *** ///

add_filter('manage_edit-external-videos_columns', 'sp_external_videos_columns');
function sp_external_videos_columns($columns) {
    $columns = array(
        'cb'          => '<input type="checkbox" />',
        'title'       => __('Video Title', 'external-videos'),
        'thumbnail'   => __('Thumbnail', 'external-videos'),
        'host'        => __('Host', 'external-videos'),
        'duration'    => __('Duration', 'external-videos'),
        'published'   => __('Published', 'external-videos'),
        'parent'      => __('Attached to', 'column name'),
        'tags'        => __('Tags', 'external-videos'),
        'comments'    => __('Comments', 'external-videos')
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
    if( is_array( $duration ) ) $duration = array_shift( $duration );
    if( is_array( $host ) ) $host = array_shift( $host );
    if( is_array( $thumbnail ) ) $thumbnail = array_shift( $thumbnail );

    switch($column_name) {
    case 'ID':
        echo $post->ID;
        break;

    case 'thumbnail':
        echo "<img src='".$thumbnail."' width='120px' height='90px'/>";
        break;

    case 'duration':
        echo $duration;
        break;

    case 'published':
        echo $post->post_date;
        break;

    case 'host':
        echo $host;
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
            <strong><a href="<?php echo get_edit_post_link( $post->post_parent ); ?>"><?php echo $title ?></a></strong>, <?php echo get_the_time(__('Y/m/d','external-videos')); ?><br/>
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
  $options = $raw_options == "" ? array('version' => 1, 'authors' => array(), 'rss' => false, 'delete' => true) : $raw_options;
  sp_ev_update_videos($options['authors'], $options['delete']);
}


/// ***   Setup of Plugin   *** ///

add_action('init', 'sp_external_videos_init');
function sp_external_videos_init() {
    $plugin_dir = basename(dirname(__FILE__));
   load_plugin_textdomain( 'external-videos', false, $plugin_dir . '/localization/' );

   // create a "video" category to store posts against
    wp_create_category(__('External Videos', 'external-videos'));

    // create "external videos" post type
    register_post_type('external-videos', array(
        'label'           => __('External Videos', 'external-videos'),
        'singular_label'  => __('External Video', 'external-videos'),
        'description'     => __('pulls in videos from external hosting sites','external-videos'),
        'public'          => true,
        'publicly_queryable' => true,
        'show_ui'         => true,
        'capability_type' => 'post',
        'hierarchical'    => false,
        'query_var'       => true,
        'supports'        => array('title', 'editor', 'author', 'thumbnail', 'excerpt', 'trackbacks', 'custom-fields', 'comments', 'revisions', 'post-formats'),
        'taxonomies'      => array('post_tag', 'category'),
        'has_archive'       => true,
        'rewrite'           => array('slug' => 'external-videos'),
        'yarpp_support'     => true
    ));

    // Oembed support for dotsub
    wp_oembed_add_provider('#http://(www\.)?dotsub\.com/view/.*#i', 'http://api.embed.ly/v1/api/oembed', true);
    // Oembed support for wistia
    wp_oembed_add_provider( '/https?:\/\/(.+)?(wistia\.(com|net)|wi\.st)\/.*/', 'http://fast.wistia.net/oembed', true );

    // enable thickbox use for gallery
    wp_enqueue_style('thickbox');
    wp_enqueue_script('thickbox');
}

/// *** Flush rewrite on activiation
register_activation_hook( __FILE__, 'my_rewrite_flush' );
function my_rewrite_flush() {
    // First, we "add" the custom post type via the above written function.
    sp_external_videos_init();

    // ATTENTION: This is *only* done during plugin activation hook in this example!
    // You should *NEVER EVER* do this on every page load!!
    flush_rewrite_rules();
}


/// *** Setup of Videos Gallery: implemented in ev-media-gallery.php *** ///
add_shortcode('external-videos', 'sp_external_videos_gallery');

/// *** Setup of Widget: implemented in ev-widget.php file *** ///
add_action('widgets_init', 'sp_external_videos_load_widget');
