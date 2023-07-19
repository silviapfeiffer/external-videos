<?php

/*
*  SP_EV_SyncPosts
*
*  Class for External Videos post modification and sync functions
*
*  @type  class
*  @date  18/07/23
*  @since  1.4.0
*
*/

if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if( ! class_exists( 'SP_EV_SyncPosts' ) ) :

class SP_EV_SyncPosts {

  public function __construct() {

    add_action( 'init', array( $this, 'initialize' ) );

    add_action( 'wp_ajax_update_videos_handler',
                array( $this, 'update_videos_handler' ) );
    add_action( 'wp_ajax_unembed_videos_handler',
                array( $this, 'unembed_videos_handler' ) );
    add_action( 'wp_ajax_delete_all_videos_handler',
                array( $this, 'delete_all_videos_handler' ) );

    add_action( 'ev_daily_event', array( $this, 'daily_function' ) );

  }


  /*
  *  initialize
  *
  *  actions that need to go on the init hook
  *
  *  @type  function
  *  @date  18/07/23
  *  @since  1.4.0
  *
  *  @param
  *  @return
  */

  function initialize() {

    // nothing yet
  }


  /*
  *  update_videos_handler
  *
  *  Used by settings page for manually updating from channels
  *  AJAX handler for "Update videos from channels" form
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param
  *  @return
  */

  function update_videos_handler() {

    // Handle the ajax request
    check_ajax_referer( 'ev_settings' );

    // Get EV options once now for the helper functions
    $options = SP_External_Videos::get_options();
    $HOSTS = $options['hosts'];
    $delete = $options['delete'];

    $new_messages = $trash_messages = '';

    // figure out whether we're updating a single author, or all
    // if single limit the $update_authors and $update_hosts array accordingly
    if( isset( $_POST['host_id'] ) && isset( $_POST['author_id'] ) ) {

      // it's single
      $this_host = $_POST['host_id'];
      $this_author = $_POST['author_id'];

      // get the relevant local author from host
      $update_hosts = array( $this_host=>$HOSTS[$this_host] ); // has to stay indexed and loopable
      $update_author = $HOSTS[$this_host]['authors'][$this_author]; // has to be whole author array

    } else {

      // it's update all
      $update_hosts = $HOSTS;
      $update_author = null;

    }

    // sync_videos() gets everything current and returns messages about it
    // it also updates post_meta to current state
    $sync_response = $this->sync_videos( $update_hosts, $update_author );
    $new_messages = $sync_response['messages'];
    $current_video_ids = $sync_response['current_video_ids'];

    // trash_deleted_videos() checks for videos deleted on host
    // and returns messages about it
    if( $delete ) {
      $trash_messages = $this->trash_deleted_videos( $update_hosts,
                                                     $update_author,
                                                     $current_video_ids );
    }

    $messages = $new_messages . $trash_messages;

    wp_send_json( $messages );

  }

  /*
  *  sync_videos
  *
  *  Used by update_videos_handler() and daily_function()
  *  Uses fetch_hosted_videos(), wrap_admin_notice() and save_video()
  *  Saves any new videos from host channels to the database.
  *  Updates existing videos according to returned data.
  *  Returns messages about number of video posts added.
  *  Works for single-author and update-all
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param   $update_hosts, $update_author
  *  @return  array( html $messages, array $current_video_ids )
  */

  function sync_videos( $update_hosts, $update_author = null ) {

    $current_video_ids = array();
    $current_videos = $this->fetch_hosted_videos( $update_hosts,
                                                  $update_author );

    // If there's nothing on the channels, return with a message and the
    // empty array of $current_video_ids
    if ( !$current_videos ) {
      return $this->no_videos_response( $update_hosts );
    }

    // If we're still here there's news on the channels.
    // we're going to count how many we add at each host
    $count_added = $count_updated = array();
    // fill out this array with zeros, or we'll get an error
    foreach( $update_hosts as $host ){
      $host_id = $host['host_id'];
      $count_added[$host_id] = 0;
      $count_updated[$host_id] = 0;
    }

    // save or update current videos & build list of all new video_ids
    foreach ( $current_videos as $video ) {
      // $current_video_ids is an array of all current video ids
      array_push( $current_video_ids, $video['video_id'] );
      // save_video() checks if is new, and saves video post
      // But it also might just update the video post_meta
      // Ideally we'd build $update_messages too, at least how many
      // save_video() could return true, false or "updated" (or message)
      $is_new = $this->save_video( $video );
      if ( $is_new ) {
        $host_id = $video['host_id'];
        $count_added[$host_id]++;
      }
    }

    $messages = $this->build_sync_messages( $update_hosts, $count_added );

    // return the messages and the array of current_video_ids,
    // needed by trash_deleted_videos()
    return array(
      'messages'          => $messages,
      'current_video_ids' => $current_video_ids
    );
  }

  /*
  *  no_videos_response
  *
  *  Used by sync_videos()
  *  Return a formatted response that there are no videos currently on hosts.
  *
  *  @type  function
  *  @date  18/07/23
  *  @since  1.4.0
  *
  *  @param   $update_hosts
  *  @return  array( html $messages, array $current_video_ids )
  */

  function no_videos_response( $update_hosts ) {

    $zero_message = '';
    $current_video_ids = $host_list = array();
    foreach( $update_hosts as $host ){
      $host_list[] = $host['host_name'];
    }
    $host_list = implode( ', ', $host_list );
    $zero_message = esc_attr__( "No new videos found on " . $host_list . "." );
    $zero_message = SP_EV_Admin::wrap_admin_notice( $zero_message, 'info' );

    return array(
      'messages'          => $zero_message,
      'current_video_ids' => $current_video_ids
    );
  }

  /*
  *  fetch_hosted_videos
  *
  *  Used by sync_videos()
  *  This calls the various API interfaces. Fetches all current videos from a
  *  registered, externally hosted channel, or from all channels.
  *  The various API functions are defined in separate classes for each host.
  *  $this->save_video() checks if it exists in WP or not.
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param   $update_hosts, $update_author
  *  @return  $current_videos (array of videos)
  */

  function fetch_hosted_videos( $update_hosts, $update_author ) {

    // error_log( '$update_hosts: ' .  print_r( $update_hosts, true ) );
    // error_log( '$update_author: ' .  print_r( $update_author, true ) );

    $current_videos = array();

    foreach ( $update_hosts as $host ) {

      $host_name = $host['host_name'];
      $ClassName = "SP_EV_".$host_name;
      // error_log( '$ClassName: ' .  print_r( $ClassName, true ) );

      if( $update_author == null ){
        // fetch all hosts, all authors
        foreach( $host['authors'] as $author ){
          $author_videos = $ClassName::fetch( $author );
          $current_videos = array_merge( $author_videos, $current_videos );
        }
      } else {
        // fetch single author's videos
        $current_videos = $ClassName::fetch( $update_author );
      }
    }

    // error_log( print_r( $current_videos, true ) );

    return $current_videos;

  }

  /*
  *  save_video
  *
  *  Used by sync_videos() and update_videos_handler()
  *  Checks if video exists (by `video_id`), because the API returns all videos.
  *  Maybe updates `poster_url` as of 1.4.0
  *  If not exists, creates a post of type `external-videos` and saves it.
  *  The passed $video array contains the fields we need to make the post,
  *  all except `embed_url` (provided by embed_url()).
  *
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param   $video
  *  @return  boolean
  */

  function save_video( $video ) {

    // get options
    $options = SP_External_Videos::get_options();

    // See if video exists, update accordingly and stop execution
    // or TODO return messages about updates.
    if( $this->update_existing_post( $options, $video ) ) return false;

    // If we're here, it's a new post.
    $post_id = $this->create_video_post( $options, $video );
    $post = get_post( $post_id );

    // set post format
    if ( current_theme_supports( 'post-formats' ) &&
      post_type_supports( $post->post_type, 'post-formats' )) {
      set_post_format( $post, $video['ev_post_format'] );
    }

    // add or update post meta (update will add if not exists)
    $this->update_post_meta( $post_id, $video );

    // set the categories and tags
    $this->set_post_taxonomies( $post_id, $video );

    return true;
  }

  /*
  *  update_existing_post
  *
  *  Used by save_video()
  *  Check for an existing ev post (by video_id)
  *  and if found, sync w/ host API's current $video data.
  *  Options may store whether to sync title and content
  *
  *  @type  function
  *  @date  18/07/23
  *  @since  1.4.0
  *
  *  @param   $options
  *  @param   $video
  *  @return  boolean
  */
  function update_existing_post( $options, $video ) {

    $ev_query = new WP_Query( array(
      'post_type' => 'external-videos',
      'post_status' => 'any',
      'meta_key' => 'video_id',
      'meta_value' => $video['video_id'],
      'meta_compare' => 'LIKE' // because of old vimeo uris
    ) );

    // If it does, do some housekeeping
    while( $ev_query->have_posts() ) {

      $query_video = $ev_query->next_post();

      // Just to be sure, check if the video has a host_id
      if( get_post_meta( $query_video->ID, 'host_id', true ) ) {

        // Check if video is marked embeddable at the host, in the first place.
        // Moves video to trash if not. (up to v1.4.0 there were failed embeds)
        // TODO: Build messages about all of this
        $this->check_if_embeddable( $query_video->ID, $video );

        // In case updated on host, update the details here.
        $this->update_post_meta( $query_video->ID, $video );

        // Could also sync title, content, cats n tags with new options.
      }

      // Post exists already; skip creating a post for this one
      return true;
    }

    return false;
  }

  /*
  *  check_if_embeddable
  *
  *  Used by update_if_exists()
  *  Check one existing ev post to find if host API's current $video data says
  *  embeddable. Not only to respect the setting on the host, but also because
  *  prior to 1.4, unembeddable videos created a post, but had blank previews,
  *  and didn't play. If it's not currently embeddable, move it to the trash.
  *  If embed setting changes, a new ev post will be created.
  *
  *  @type  function
  *  @date  18/07/23
  *  @since  1.4.0
  *
  *  @param   $ev_post_id, $video_data
  *  @return  boolean
  */

  function check_if_embeddable( $ev_post_id, $video ) {

    if( $video['embeddable'] === false ) {
      $post = get_post( $ev_post_id );
      $post->post_status = 'trash';
      wp_update_post( $post );
      // TODO: return a message about trashed videos!
      // $count_trashed += 1;
      return false;
    } else {
      return true;
    };
  }

  /*
  *  create_video_post
  *
  *  Used by save_video()
  *  Inserts a new external-video post into the db, using
  *  host API's current $video data.
  *
  *  @type  function
  *  @date  18/07/23
  *  @since  1.4.0
  *
  *  @param   $options
  *  @param   $video
  *  @return  integer $post_id
  */

  function create_video_post( $options, $video ) {

    // put post_content together
    $post_content = $this->build_post_content( $options, $video );

    // prepare post
    $video_post = array();
    $video_post['post_type']      = 'external-videos';
    $video_post['post_title']     = sanitize_text_field( $video['title'] );
    $video_post['post_content']   = $post_content;
    $video_post['post_status']    = sanitize_text_field( $video['ev_post_status'] );
    $video_post['post_author']    = sanitize_user( $video['ev_author'] );
    $video_post['post_date']      = gmdate( "Y-m-d H:i:s", strtotime( $video['published'] ) );
    $video_post['tags_input']     = array_map( 'esc_attr', $video['tags'] );
    $video_post['post_mime_type'] = 'import';
    $video_post['post_excerpt']   = sanitize_text_field( $video['description'] );

    // save to DB
    $post_id = wp_insert_post( $video_post );

    return $post_id;
  }

  /*
  *  build_post_content()
  *
  *  Utility for create_video_post() to assemble the post_content.
  *  Whole thing gets the WP 'the_content' filter to process the embed.
  *  As of 1.3.1: Makes the video oembed in content optional too
  *
  *  @type  function
  *  @date  18/07/23
  *  @since  1.4.0
  *
  *  @param   $options - from SP_External_Videos::get_options
  *  @param   $video - updated array of video attributes from API
  *  @return  string $post_content
  */

  function build_post_content( $video, $options ) {

    $post_content = "";

    // embed the video at the top if option selected
    if( $options['embed'] == true ) {
      $post_content = "\n";
      $post_content .= esc_url( $video['embed_url'] );
      $post_content .= "\n\n";
    }

    // add the description no matter what
    $post_content .= '<p>' .
                      sanitize_text_field( trim( $video['description'] ) ) .
                      '</p>';

    // add the attribution if that option is selected
    if( $options['attrib'] == true ) {
      $post_content .= '<p><small>';
      if ( $video['category'] != '' ) {
        $post_content .= '<i>' . esc_attr__( "Category:" , 'external-videos' ) . ' </i>';
        $categories = array_map( 'esc_attr', (array) $video['ev_category'] );
        $post_content .= implode( ', ', $categories );
        $post_content .= '<br/>';
      }
      $post_content .= '<i>' . esc_attr__( "Uploaded by:" , 'external-videos' ) . ' </i>';
      $post_content .= '<a href="' . esc_url( $video['author_url'] ) . '">';
      $post_content .= sanitize_text_field( $video['author_name'] ) . '</a>';
      $post_content .= '<br/>';
      $post_content .= '<i>' . esc_attr__( "Hosted:" , 'external-videos' ) . ' </i>';
      $post_content .= '<a href="' . esc_url( $video['video_url'] ) . '">';
      $post_content .= sanitize_text_field( $video['host_id'] ) . '</a>';
      $post_content .= '</small></p>';
    }

    return apply_filters( 'the_content', $post_content );
  }

  /*
  *  update_post_meta()
  *
  *  Used by save_video() in case there's new `post_meta` since post created.
  *  First used for `poster_url`. Could also be used to update all attributes
  *
  *  @type  function
  *  @date  11/07/23
  *  @since  1.4.0
  *
  *  @param   $query_video - existing ev post from the db
  *  @param   $video - updated array of attributes from API
  *  @return  Could return an array of messages about the updates
  */

  function update_post_meta( $post_id, $video ) {

    // New in 1.4.0 - Check if the new post_meta `poster_url` exists.
    // If not, and if we did get a `poster_url` from the API, add it.
    // if( !get_post_meta( $ev_post_id, 'poster_url', true ) &&
    //     $video['poster_url'] ) {
    //   add_post_meta( $ev_post_id, 'poster_url',
    //                  esc_url( $video['poster_url'] ) );
    //   return true;
    // } else {
    //   return false;
    // }
    update_post_meta( $post_id, 'host_id',
                      sanitize_text_field( $video['host_id'] ) );
    update_post_meta( $post_id, 'author_id',
                      sanitize_text_field( $video['author_id'] ) );
    update_post_meta( $post_id, 'video_id',
                      sanitize_text_field( $video['video_id'] ) );
    update_post_meta( $post_id, 'duration',
                      $video['duration'] ); // how to sanitize?
    update_post_meta( $post_id, 'author_url',
                      esc_url( $video['author_url'] ) );
    update_post_meta( $post_id, 'video_url',
                      esc_url( $video['video_url'] ) );
    update_post_meta( $post_id, 'thumbnail_url',
                      esc_url( $video['thumbnail_url'] ) );
    update_post_meta( $post_id, 'poster_url',
                      esc_url( $video['poster_url'] ) );
    // Cheat here with a dummy image so we can show thumbnails properly
    update_post_meta( $post_id, '_wp_attached_file', 'dummy.png' );
    update_post_meta( $post_id, 'description',
                      sanitize_text_field( $video['description'] ) );
    // video embed code.
    // TODO: meta key could be converted to "embed_url" for consistency
    update_post_meta( $post_id, 'embed_code',
                      esc_url( $this->embed_url( $video['host_id'],
                                                 $video['video_id'] ) ) );
  }

  /*
  *  set_post_taxonomies()
  *
  *  Used by save_video() in case there's new `taxonomies` since post created.
  *
  *  @type  function
  *  @date  18/07/23
  *  @since  1.4.0
  *
  *  @param   $post_id - existing ev post from the db
  *  @param   $video - updated array of attributes from API
  *  @return  Could return an array of messages about the updates
  */

  function set_post_taxonomies( $post_id, $video ) {

    // category id & tag attribution
    if( !is_array( $video['ev_category'] ) ) {
      $video['ev_category'] = (array) $video['ev_category'];
    }
    wp_set_post_categories( $post_id,
                            array_map( 'esc_attr', $video['ev_category'] ) );

    if( !is_array( $video['tags'] ) ) {
      $video['tags'] = (array) $video['tags'];
    }
    wp_set_post_tags( $post_id,
                      array_map( 'esc_attr', $video['tags'] ), 'post_tag' );

  }
  /*
  *  embed_url
  *
  *  Used by save_video()
  *  Embed url is stored as postmeta in external-video posts.
  *  Format is specific to each host site's embed API.
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  0.23
  *
  *  @param   $host, $video_id
  *  @return  <iframe>
  */

  function embed_url( $host, $video_id ) {

    $HOSTS = SP_EV_Admin::get_hosts();
    $host_name = $HOSTS[$host]['host_name'];
    $ClassName = "SP_EV_" . $host_name;

    $embed_url = $ClassName::embed_url( $video_id );

    return $embed_url;

  }

  /*
  *  build_sync_messages()
  *
  *  Used by sync_videos()
  *
  *  @type  function
  *  @date  18/07/23
  *  @since  1.4.0
  *
  *  @param   $update_hosts
  *  @param   $count_added - updated array of attributes from API
  *  @return  $messages
  */

  function build_sync_messages( $update_hosts, $count_added ) {

    $messages = $add_messages = $no_messages = "";

    // build messages about added videos, or no videos, per host
    foreach ( $count_added as $host_id=>$num ) {
      $host_name = $update_hosts[$host_id]['host_name'];
      if ( $num > 0 ) {
        $add_messages .= sprintf( _n( 'Found %1$s new video on %2$s. ', 'Found %1$s new videos on %2$s. ', $num, 'external-videos' ), $num, $host_name );
      }
      else {
        $no_messages .= "No new videos found on " . $host_name . '. ';
      }
    }
    // after looping to get all add/no messages, wrap them up for delivery
    if( $add_messages ){
      $add_messages = SP_EV_Admin::wrap_admin_notice( $add_messages, 'success' );
      $messages .= $add_messages;
    }
    if( $no_messages ){
      $no_messages = SP_EV_Admin::wrap_admin_notice( $no_messages, 'info' );
      $messages .= $no_messages;
    }

    return $messages;
  }


  /*
  *  trash_deleted_videos()
  *
  *  Used by update_videos_handler() and daily_function()
  *  Trashes any videos on WordPress that have been deleted from host channels.
  *  Returns messages about number of video posts trashed.
  *  We're iterating by host for more specific messages, since we have a list
  *  of $current_video_ids to compare with existing posts.
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param   $update_hosts, $current_video_ids - passed from sync_videos()
  *  @return  html $trash_messages
  */

  function trash_deleted_videos( $update_hosts,
                                 $update_author,
                                 $current_video_ids ) {

    // we're going to count how many were deleted at each host
    // must fill out this array with zeros, or error
    $count_deleted = array();

    foreach( $update_hosts as $host ){
      $host_id = $host['host_id'];
      $count_deleted[$host_id] = 0;
    }

    if( $update_author == null ){

      $existing_videos = $this->get_video_posts();

    } else {

      $host_id = $update_hosts[0]['host_id'];
      $author_id = $update_author['author_id'];

      $existing_videos = $this->get_video_posts( $host_id, $author_id );
    }

    // error_log( print_r( $current_video_ids, true ) );

    // Check this is working. Value could be an array
    while( $existing_videos->have_posts() ) {

      $existing_video = $existing_videos->next_post();
      $video_id = get_post_meta( $existing_video->ID, 'video_id', true );
      $host = get_post_meta( $existing_video->ID, 'host_id', true );

      // Move external-video to trash if not in array of $current_video_ids
      // passed from the sync_videos() function
      if ( $video_id != NULL && !in_array( $video_id, $current_video_ids ) ) {
        $post = get_post( $existing_video->ID );
        $post->post_status = 'trash';
        wp_update_post( $post );
        // update count of deleted videos on this host
        $count_deleted[$host]++;
      }
    }

    // build message about deleted videos
    foreach( $count_deleted as $host=>$num ) {
      if ( $num > 0 ) {
        $trash_messages = sprintf( _n( 'Note: %1$d video was deleted on %2$s and moved to trash on WordPress.', 'Note: %1$d videos were deleted on %2$s and moved to trash on WordPress.', $num, 'external-videos'), $num, $host );
      }
    }

    if( isset( $trash_messages ) ) {
      // All trash messages in one wrap
      $trash_messages = SP_EV_Admin::wrap_admin_notice( $trash_messages, 'warning' );
      // return the messages
      return $trash_messages;
    }

    return '';
  }

  /*
  *  unembed_videos_handler
  *
  *  AJAX handler for
  *  "Remove embedded videos from all external-videos post content"
  *  Used by settings page
  *
  *  @type  function
  *  @date  03/07/23
  *  @since  1.3.2
  *
  *  @param
  *  @return html $messages
  */

  function unembed_videos_handler() {

    check_ajax_referer( 'ev_settings' );

    $count_updated = $this->unembed_videos();
    $messages = sprintf( _n( 'Removed embedded video from %d post.',
                             'Removed embedded video from %d posts.',
                             $count_updated, 'external-videos' ),
                             $count_updated );
    $messages = esc_attr( $messages );
    $messages = SP_EV_Admin::wrap_admin_notice( $messages, 'info' );

    wp_send_json( $messages );

  }

  /*
  *  unembed_videos
  *
  *  Used by unembed_videos_handler()
  *  Removes embedded videos from all external-videos post content
  *
  *  @type  function
  *  @date  03/07/23
  *  @since  1.3.2
  *
  *  @param
  *  @return $count_updated
  */

  function unembed_videos() {

    $count_updated = 0;
    // query all of post_type: external-videos
    $ev_posts = $this->get_video_posts();

    if( $ev_posts->have_posts() ) {
      while ( $ev_posts->have_posts() ) : $ev_posts->the_post();

        $post = get_post( get_the_ID() );
        $post_content = $post->post_content;
        // Only checking embeds at the very beginning of post_content.
        preg_match_all('/\A<p><iframe[^>]*>.*?<\/iframe><\/p>\n(.*?)$/',
                       $post_content, $matches);

        if( !is_array( $matches ) ) {
          continue;
        } elseif( count($matches) == 2 ) {
          $content_without_embed = $matches[1];
        // } elseif( count($matches) > 2 ) {
          // error_log( "complicated: " . $post->ID . " has " . count($matches) );
        }

        if( !is_array( $content_without_embed ) || !$content_without_embed ) {
          continue;
        } else {
          // error_log( "Post " . $post->ID . ":");
          // error_log( print_r( $content_without_embed, true ) );
          $post->post_content = array_shift( $content_without_embed );
          wp_update_post( $post );
        }
        $count_updated += 1;

      endwhile;
      wp_reset_postdata();
    }

    return $count_updated;

  }

  /*
  *  delete_all_videos_handler
  *
  *  AJAX handler for "Delete videos from all channels" form
  *  Used by settings page
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param
  *  @return html $messages
  */

  function delete_all_videos_handler() {

    check_ajax_referer( 'ev_settings' );

    $count_deleted = $this->delete_all_videos();
    $messages = sprintf( _n( 'Moved %d video into trash.',
                             'Moved %d videos into trash.',
                             $count_deleted, 'external-videos' ),
                             $count_deleted );
    $messages = esc_attr( $messages );
    $messages = SP_EV_Admin::wrap_admin_notice( $messages, 'info' );

    wp_send_json( $messages );

  }

  /*
  *  delete_all_videos
  *
  *  Used by delete_all_videos_handler()
  *  Moves all external-videos posts to the trash
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param
  *  @return $count_deleted
  */

  function delete_all_videos() {

    $count_deleted = 0;
    // query all of post_type: external-videos
    $ev_posts = $this->get_video_posts();

    if( $ev_posts->have_posts() ) {
      while ( $ev_posts->have_posts() ) : $ev_posts->the_post();

        $post = get_post( get_the_ID() );
        $post->post_status = 'trash';
        wp_update_post( $post );
        $count_deleted += 1;

      endwhile;
      wp_reset_postdata();
    }

    return $count_deleted;

  }

  /*
  *  get_video_posts
  *
  *  Used by unembed_all_videos(), delete_all_videos() and
  *  trash_deleted_videos()
  *  Query for all external video posts, or by author with params
  *
  *  @type  function
  *  @date  03/07/23
  *  @since  1.3.2
  *
  *  @param $host_id
  *  @param $author_id
  *  @return $ev_posts
  */

  function get_video_posts( $host_id = null, $author_id = null ) {

    $args = array(
      'post_type' => 'external-videos',
      'nopaging' => 1
    );

    if( $host_id && $author_id ) {
      $args['meta_query'] = array(
        array(
          'key'     => 'host_id',
          'value'   => $host_id
        ),
        array(
            'key'     => 'author_id',
            'value'   => $author_id
        )
      );
    }

    // query all of post_type: external-videos
    $ev_posts = new WP_Query( $args );

    return $ev_posts;

  }

  /*
  *  daily_function
  *
  *  cron job to check video channels and add them as posts to database if found
  *  sync_videos() also runs trash_deleted_videos()
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  0.26
  *
  *  @param
  *  @return
  */

  function daily_function() {

    $HOSTS = SP_EV_Admin::get_hosts();
    if( !isset( $HOSTS ) ) return;

    $this->sync_videos( $HOSTS, null ); // all hosts, all authors

  }


} // end class
endif;

/*
* Instantiate the class
*/

global $SP_EV_SyncPosts;
$SP_EV_SyncPosts = new SP_EV_SyncPosts();
?>
