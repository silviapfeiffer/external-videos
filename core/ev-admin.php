<?php

if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if( ! class_exists( 'SP_EV_Admin' ) ) :

class SP_EV_Admin {

  public function __construct() {

    add_action( 'init', array( $this, 'initialize' ) );

    add_action( 'wp_ajax_plugin_settings_handler', array( $this, 'plugin_settings_handler' ) );
    add_action( 'wp_ajax_update_videos_handler', array( $this, 'update_videos_handler' ) );
    add_action( 'wp_ajax_delete_all_videos_handler', array( $this, 'delete_all_videos_handler' ) );
    add_action( 'wp_ajax_author_list_handler', array( $this, 'author_list_handler' ) );
    add_action( 'wp_ajax_delete_author_handler', array( $this, 'delete_author_handler' ) );
    add_action( 'wp_ajax_add_author_handler', array( $this, 'add_author_handler' ) );

    add_filter( 'manage_edit-external-videos_columns', array( $this, 'admin_columns' ) );
    add_action( 'manage_posts_custom_column', array( $this, 'admin_custom_columns' ) );

    add_action( 'ev_daily_event', array( $this, 'daily_function' ) );

  }

  /*
  *  initialize
  *
  *  actions that need to go on the init hook
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param
  *  @return
  */

  function initialize() {

    // nothing yet
  }

  /*
  *  admin_get_authors
  *
  *  Settings page
  *  Used by
  *  Returns full array of authors from options['authors'];
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param
  *  @returns $AUTHORS
  */

  static function admin_get_authors(){

    $options = SP_External_Videos::get_options();
    $AUTHORS = $options['authors'];

    return $AUTHORS;
  }

  /*
  *  admin_get_hosts
  *
  *  Settings page
  *  Used by
  *  Returns full array of hosts from options['hosts'];
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param
  *  @returns $HOSTS
  */

  static function admin_get_hosts(){

    $options = SP_External_Videos::get_options();
    $HOSTS = $options['hosts'];

    return $HOSTS;
  }

  /*
  *  admin_get_hosts_quick
  *
  *  Settings page
  *  Used by ev-settings-forms.php and AJAX handlers
  *  Returns quick associative array of host_id => name from options['hosts'];
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param
  *  @returns $VIDEO_HOSTS
  */

  static function admin_get_hosts_quick(){

    $HOSTS = SP_EV_Admin::admin_get_hosts();
    $VIDEO_HOSTS = array();

    foreach( $HOSTS as $host ){
      $id = $host['host_id'];
      $VIDEO_HOSTS[$id] = $host['host_name'];
    }

    return $VIDEO_HOSTS;

  }

  /*
  *  plugin_settings_handler
  *
  *  Used by settings page
  *  AJAX handler for plugin settings form
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param
  *  @return
  */

  function plugin_settings_handler() {

    // Handle the ajax request
    check_ajax_referer( 'ev_settings' );

    $options = SP_External_Videos::get_options();
    $message = '';

    $fields = array();
    parse_str($_POST['data'], $fields);

    // error_log( print_r( $fields, 1 ) );

    $options['rss'] = ( array_key_exists( 'ev-rss', $fields ) ? true : false );
    $options['delete'] = ( array_key_exists( 'ev-delete', $fields ) ? true : false );
    $options['attrib'] = ( array_key_exists( 'ev-attrib', $fields ) ? true : false );

    if( update_option( 'sp_external_videos_options', $options ) ) $message = "Settings saved.";
    ob_clean();

    wp_send_json( $message );

  }

  /*
  *  update_videos_handler
  *
  *  Used by settings page
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

    $options = SP_External_Videos::get_options();
    $AUTHORS = SP_EV_Admin::admin_get_authors();
    $messages = '';

    if( isset( $_POST['author_id'] ) && isset( $_POST['host_id'] ) ) {
      $eligibles = array();

      foreach( $AUTHORS as $authorkey => $authordata ){
        if( in_array( $_POST['host_id'], $authordata ) ) {
          $eligibles[] = $authorkey;
        }
      }

      if( $eligibles ) {
        $thisindex = array();

        foreach( $eligibles as $eligible => $originalkey ){
          if( in_array( $_POST['author_id'], $AUTHORS[$originalkey] ) ) {
            $thisindex[] = $originalkey;
          }
        }
        $thisindex = array_shift( $thisindex );
        $update_authors = array( $AUTHORS[$thisindex] );
        $single = true;
      }

    } else {
      $update_authors = $AUTHORS;
      $single = null;
    }

    $delete = isset( $options['delete'] ) ? $options['delete'] : null;
    // post_new_videos() gets everything new or deleted and returns messages about it
    $messages = $this->post_new_videos( $update_authors, $delete, $single );

    wp_send_json( $messages );

  }

  /*
  *  post_new_videos
  *
  *  Used by update_videos_handler() and daily_function()
  *  Saves any new videos from host channels to the database.
  *  Returns number of video posts added.
  *  Works for single-author and update-all via $single param
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param   $authors, $delete, $single
  *  @return  html $messages
  */

  function post_new_videos( $authors, $delete, $single ) {

    $HOSTS = SP_EV_Admin::admin_get_hosts();

    $new_videos = $this->fetch_new_videos( $authors, $HOSTS );

    if ( !$new_videos ) return 0;

    $messages = $add_messages = $no_messages = $zero_message = '';

    // save new videos & build list of all current video_ids
    // $added_videos is an array of totals indexed by host
    // $video_ids is an array of unique video ids
    $added_videos = $deleted_videos = array();
    // we gotta fill out this array with zeros, or error
    foreach( $HOSTS as $host ){
      $id = $host['host_id'];
      $added_videos[$id] = $deleted_videos[$id] = 0;
    }
    $video_ids = array();

    foreach ( $new_videos as $video ) {
      array_push( $video_ids, $video['video_id'] );
      $is_new = $this->save_video( $video );

      if ( $is_new ) {
        $host = $video['host_id'];
        $added_videos[$host]++;
      }
    }

    // build messages about added videos per host
    foreach ( $added_videos as $host=>$num ) {
      $hostname = $HOSTS[$host]['host_name'];
      if ( $num > 0 ) {
        $add_messages .= sprintf( _n( 'Found %1$s video on %2$s.', 'Found %1$s videos on %2$s.', $num, 'external-videos' ), $num, $hostname );
      }
      else {
        $no_messages .= "No videos found on " . $hostname . '.';
      }
    }

    // reset the no message to single
    if( $single && $no_messages ){
      $no_messages = "No videos found on " . $authors[0]['host_id'] . '.';
    }
    // could have both, or neither
    if( !$add_messages && !$no_messages ) {
      $zero_message = "No videos found.";
      $zero_message = sp_ev_wrap_admin_notice( $zero_message, 'info' );
      $messages = $zero_message;
    } else {
      if( $add_messages ) {
        $add_messages = sp_ev_wrap_admin_notice( $add_messages, 'success' );
        $messages .= $add_messages;
      }
      if( $no_messages ) {
        $no_messages = sp_ev_wrap_admin_notice( $no_messages, 'info' );
        // only add $no_messages if we're doing multiple authors,
        // or if we don't have any $add_messages for single author.
        if( $single && !$add_messages ) $messages .= $no_messages;
      }
    }

    // if we're not deleting anything
    if ( !$delete ) {
      // just return the messages
      return $messages;
    }

    // next up: deleted videos
    $all_videos = new WP_Query( array(
      'post_type'  => 'external-videos',
      'nopaging' => 1
    ) );

    while( $all_videos->have_posts() ) {
      $old_video = $all_videos->next_post();
      $video_id = get_post_meta( $old_video->ID, 'video_id', true );
      $host = get_post_meta( $old_video->ID, 'host_id', true );

      // Move external-video to trash if deleted on host channel
      if ( $video_id != NULL && !in_array( $video_id, $video_ids ) ) {
        $post = get_post( $old_video->ID );
        $post->post_status = 'trash';
        wp_update_post( $post );
        $deleted_videos[$host]++;
      }
    }

    // build message about deleted videos
    foreach( $deleted_videos as $host=>$num ) {
      if ( $num > 0 ) {
        $delete_messages = sprintf( _n( 'Note: %1$d video was deleted on %2$s and moved to trash on WordPress.', 'Note: %1$d videos were deleted on %2$s and moved to trash on WordPress.', $num, 'external-videos'), $num, $host );
      }
    }

    if( $delete_messages ) {
      $delete_messages = sp_ev_wrap_admin_notice( $delete_messages, 'warning' );
      $messages .= $delete_messages;
    }

    return $messages;

  }

  /*
  *  fetch_new_videos
  *
  *  Used by post_new_videos()
  *  Fetch new videos from all registered, externally hosted channels.
  *  The various API functions are defined in separate classes for each host.
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param   $authors, $HOSTS (full hosts array)
  *  @return  $new_videos
  */

  function fetch_new_videos( $authors, $HOSTS ) {

    $new_videos = array();

    foreach ( $authors as $author ) {

      $host = $author['host_id'];
      $hostname = $HOSTS[$host]['host_name'];
      $ClassName = "SP_EV_".$hostname;

      $videos = $ClassName::fetch( $author );

      if ( $videos ) {
        $new_videos = array_merge( $new_videos, $videos );
      }
    }

    return $new_videos;

  }

  /*
  *  save_video
  *
  *  Used by update_videos() and update_videos_handler()
  *  Creates a post of type "external-videos" and saves it.
  *  The passed $video variable contains the fields we need to make the post,
  *  all except "embed_code" (provided by embed_code()).
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

    // See if video exists
    $ev_query = new WP_Query( array(
      'post_type' => 'external-videos',
      'post_status' => 'any',
      'meta_key' => 'video_id',
      'meta_value' => $video['video_id']
    ) );

    while( $ev_query->have_posts() ) {
      $query_video = $ev_query->next_post();
      if( get_post_meta( $query_video->ID, 'host_id', true ) ) {
        return false;
      }
    }

    // put content together
    $video_content = "\n";
    $video_content .= $video['videourl'];
    $video_content .= "\n\n";
    $video_content .= '<p>'.trim( $video['description'] ).'</p>';

    // check options, if user wants the rest of content
    $options = get_option( 'sp_external_videos_options' );
    if( !array_key_exists( 'attrib', $options ) ) $options['attrib'] = false;

    if( $options['attrib'] == true ) {
      $video_content .= '<p><small>';
      if ( $video['category'] != '' ) {
        $video_content .= __( '<i>Category:</i>', 'external-videos' ) . ' ' .$video['category'];
        $video_content .= '<br/>';
      }
      $video_content .= __( '<i>Uploaded by:</i>', 'external-videos' ) . ' <a href="'.$video['author_url'].'">'.$video['authorname'].'</a>';
      $video_content .= '<br/>';
      $video_content .= __( '<i>Hosted:</i>', 'external-videos' ) . ' <a href="'.$video['videourl'].'">'.$video['host_id'].'</a>';
      $video_content .= '</small></p>';
    }

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
    $post_id = wp_insert_post( $video_post );
    $post = get_post( $post_id );

    // set post format
    if ( current_theme_supports( 'post-formats' ) &&
      post_type_supports( $post->post_type, 'post-formats' )) {
      set_post_format( $post, $video['ev_post_format'] );
    }

    // add post meta
    add_post_meta( $post_id, 'host_id',       $video['host_id'] );
    add_post_meta( $post_id, 'author_id',     $video['author_id'] );
    add_post_meta( $post_id, 'video_id',      $video['video_id'] );
    add_post_meta( $post_id, 'duration',      $video['duration'] );
    add_post_meta( $post_id, 'author_url',    $video['author_url'] );
    add_post_meta( $post_id, 'video_url',     $video['videourl'] );
    add_post_meta( $post_id, 'thumbnail_url', $video['thumbnail'] );
    // Cheat here with a dummy image so we can show thumbnails properly
    add_post_meta( $post_id, '_wp_attached_file', 'dummy.png' );
    add_post_meta( $post_id, 'description',   trim( $video['description'] ) );
    // video embed code
    add_post_meta( $post_id, 'embed_code', $this->embed_code( $video['host_id'], $video['video_id'] ) );

    // category id & tag attribution
    wp_set_post_categories( $post_id, $video['ev_category'] );
    wp_set_post_tags( $post_id, $video['keywords'], 'post_tag' );

    return true;
  }

  /*
  *  embed_code
  *
  *  Used by save_video()
  *  Embed code is stored as postmeta in external-video posts.
  *  Code is specific to each host site's embed API.
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  0.23
  *
  *  @param   $host, $video_id
  *  @return  <iframe>
  */

  function embed_code( $host, $video_id ) {

    $HOSTS = SP_EV_Admin::admin_get_hosts();
    $hostname = $HOSTS[$host]['host_name'];
    $ClassName = "SP_EV_" . $hostname;
    $width = 560;
    $height = 315;
    $url = "";

    $embed_code = $ClassName::embed_code( $video_id );

    return $embed_code;

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

    $deleted_videos = $this->delete_all_videos();
    $messages = sprintf( _n( 'Moved %d video into trash.', 'Moved %d videos into trash.', $deleted_videos, 'external-videos' ), $deleted_videos );
    $messages = esc_attr( $messages );
    $messages = sp_ev_wrap_admin_notice( $messages, 'info' );

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
  *  @return
  */

  function delete_all_videos() {

    $deleted_videos = 0;
    // query all of post_type: external-videos
    $ev_posts = new WP_Query( array(
      'post_type' => 'external-videos',
      'nopaging' => 1
    ) );

    if( $ev_posts->have_posts() ) {
      while ( $ev_posts->have_posts() ) : $ev_posts->the_post();

        $post = get_post( get_the_ID() );
        $post->post_status = 'trash';
        wp_update_post( $post );
        $deleted_videos += 1;

      endwhile;
      wp_reset_postdata();
    }

    return $deleted_videos;

  }

  /*
  *  author_list_handler
  *
  *  Used by settings page
  *  AJAX handler to reload the author list form with fresh db info
  *  Should exactly mirror the html in ev-settings-forms.php
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param
  *  @return
  */

  function author_list_handler() {

    check_ajax_referer( 'ev_settings' );

    // faster with one query: (not helper functions)
    $options = SP_External_Videos::get_options();
    $AUTHORS = $options['authors'];
    $HOSTS = $options['hosts'];

    $html = '<div class="limited-width"><span class="feedback"></span></div>';
    $html .= '<table class="wp-list-table ev-table widefat">';
    $html .= '<thead>';
    $html .= '<tr class="">';
    $html .= '<th scope="col" class="column-title column-primary desc">' . __( 'Host', 'external-videos' ) . '</th>';
    $html .= '<th scope="col" class="column-title column-primary desc">' . __( 'Channel ID', 'external-videos' ) . '</th>';
    $html .= '</tr>';
    $html .= '</thead>';

    $html .= '<tbody>';
    // Keeping the channels organized by host
    foreach ( $HOSTS as $host ) {

      $id = $host['host_id'];
      $hostname = $host['host_name'];
      $host_authors = array();

      // if we have a channel on this host, build a row
      if( array_search( $id, array_column( $AUTHORS, 'host_id') ) !== false) {

        // channels we want are the ones with this $host in the 'host_id'
        $host_authors = array_filter( $AUTHORS, function( $author ) use ( $id ) {
          return $author['host_id'] == $id;
        } );

        $html .= '<tr>';
        $html .= '<th scope="row" class="w-25 row-title">';
        $html .= '<strong>' . $hostname . '</strong>';
        $html .= '</th>';
        $html .= '<td>';
        $html .= '<table class="form-table" style="margin-top:0;">';
          foreach( $host_authors as $author ) {
            $html .= '<tr>';
            $html .= '<td class="w-33">';// id="' . $author['host_id'] . '-' . $author['author_id'] . '">';
            // $html .= '<p>';
            $html .= '<span>' . $author['author_id'] . '</span>';
            $html .= '</td>';
            $html .= '<td class="w-25 ev-table-check text-align-right">';
            $html .= '<input type="submit" class="button-update button" value="' . __( 'Update Videos' ) . '" data-host="' .  $author['host_id'] . '" data-author="' . $author['author_id'] . '" /><div class="spinner"></div>';
            $html .= '</td>';
            $html .= '<td class="w-17 ev-table-delete text-align-right">';
            $html .= '<input type="submit" class="button-delete button" value="' . __( 'Delete' ) . '" data-host="' .  $author['host_id'] . '" data-author="' . $author['author_id'] . '" />';
            $html .= '</td>';
            $html .= '</tr>';
          }
        $html .= '</table>';
        $html .= '</td>';
        $html .= '</tr>';

      }
    }
    $html .= '</tbody>';
    $html .= '</table>';

    wp_send_json( $html );

  }

  /*
  *  delete_author_handler
  *
  *  Used by settings page
  *  AJAX handler for channel/author delete form
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param
  *  @return
  */

  function delete_author_handler() {

    // Handle the ajax request
    check_ajax_referer( 'ev_settings' );

    $options = SP_External_Videos::get_options();
    $AUTHORS = $options['authors'];
    $messages = '';

    // Does channel even exist?
    if ( !$this->local_author_exists( $_POST['host_id'], $_POST['author_id'], $AUTHORS ) ) {
      $message = __( "Can't delete a channel that doesn't exist.", 'external-videos' );
      $message = sp_ev_wrap_admin_notice( $message, 'warning' );
      $messages .= $message;
    }

    else {
      // Start clearing channel options
      foreach ( $options['authors'] as $key => $author ) {
        if ( $author['host_id'] == $_POST['host_id'] && $author['author_id'] == $_POST['author_id'] ) {
          unset( $options['authors'][$key] );
        }
      }

      $options['authors'] = array_values( $options['authors'] );

      // also remove the channel's videos from the posts table. count how many we're deleting
      $del_videos = 0;

      $author_posts = new WP_Query( array(
        'post_type'  => 'external-videos',
        'meta_key'   => 'author_id',
        'meta_value' => $_POST['author_id'],
        'nopaging' => 1
      ) );

      while( $author_posts->have_posts() ) {
        $query_video = $author_posts->next_post();
        $host = get_post_meta( $query_video->ID, 'host_id', true );
        if ( $host == $_POST['host_id'] ) {
          $post = get_post( $query_video->ID );
          $post->post_status = 'trash';
          wp_update_post($post);
          $del_videos += 1;
        }
      }

      // Save the options without this channel
      if( update_option( 'sp_external_videos_options', $options ) ) {
        // unset( $_POST['host_id'], $_POST['author_id'] );

        // $message .= printf( __( 'Deleted channel and moved %d video to trash.', 'Deleted channel and moved %d videos to trash.', $del_videos, 'external-videos' ), $del_videos );

        $message = "Deleted channel " . $_POST['author_id'] . " from " . $_POST['host_id'] . " and moved " . $del_videos . " videos to trash. ";
        $message = sp_ev_wrap_admin_notice( $message, 'info' );
        $messages .= $message;
      }

    }

    wp_send_json( $messages );

  }

  /*
  *  add_author_handler
  *
  *  Used by settings page
  *  AJAX handler for channel/author add form
  *  Different parameters per host
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param
  *  @return
  */

  function add_author_handler() {

    // Handle the ajax request
    check_ajax_referer( 'ev_settings' );

    // faster with only one query
    $options = SP_External_Videos::get_options();
    $HOSTS = $options['hosts'];
    $AUTHORS = $options['authors'];
    $messages = '';

    $author = array();
    $author['post_category'] = ''; // this one doesn't necessarily set
    parse_str($_POST['data'], $author);

    // Clean up surplus white space from entered data
    $author['author_id'] = isset( $author['author_id'] ) ? trim( sanitize_text_field( $author['author_id'] ) ) : '';
    $author['secret_key'] = isset( $author['secret_key'] ) ? trim( sanitize_text_field( $author['secret_key'] ) ) : '';
    $author['developer_key'] = isset( $author['developer_key'] ) ? trim( sanitize_text_field( $author['developer_key'] ) ) : '';
    $author['auth_token'] = isset( $author['auth_token'] ) ? trim( sanitize_text_field( $author['auth_token'] ) ) : '';

    if ( !array_key_exists( $author['host_id'], $HOSTS ) ) {
      $message = __( 'Invalid video host.', 'external-videos' );
      $message = sp_ev_wrap_admin_notice( $message, 'error' );
      $messages .= $message;
    }

    // Check if local author already exists
    elseif ( $this->local_author_exists( $author['host_id'], $author['author_id'], $AUTHORS ) ) {
      $message = __( 'Author already exists.', 'external-videos' );
      $message = sp_ev_wrap_admin_notice( $message, 'error' );
      $messages .= $message;
    }

    // Check if we don't have authentication with video service
    elseif ( !$this->authorization_exists( $author['host_id'], $author['author_id'], $author['developer_key'], $author['secret_key'], $author['auth_token'] ) ) {
      $message =  __( 'Missing required API key.', 'external-videos' );
      $message = sp_ev_wrap_admin_notice( $message, 'error' );
      $messages .= $message;
    }

    // Check if author doesn't exist on video service
    elseif ( !$this->remote_author_exists ( $author['host_id'], $author['author_id'], $author['developer_key'] ) ) {
      $message = __( 'Invalid author - check spelling.', 'external-videos' );
      $message = sp_ev_wrap_admin_notice( $message, 'error' );
      $messages .= $message;
    }

    // If we pass these tests, set up the author/channel
    else {

      $options['authors'][] = array(
        'host_id' => $author['host_id'],
        'author_id' => $author['author_id'],
        'developer_key' => $author['developer_key'],
        'secret_key' => $author['secret_key'],
        'auth_token' => $author['auth_token'],
        'ev_author' => $author['user'],
        'ev_category' => isset($author['post_category']) ? $author['post_category'] : '',
        'ev_post_format' => $author['post_format'],
        'ev_post_status' => $author['post_status']
      );

      if( update_option( 'sp_external_videos_options', $options ) ){
        $host = $author['host_id'];
        $hostname = $HOSTS[$host]['host_name'];
        $message = sprintf( __( 'Added %s channel from %s', 'external-videos' ), $author['author_id'], $hostname );
        // $message .=  __( 'Added channel from %s', 'external-videos' );
        $message = sp_ev_wrap_admin_notice( $message, 'success' );
        $messages .= $message;
      }

    }

    wp_send_json( $messages );

  }

  /*
  *  local_author_exists
  *
  *  Settings page
  *  Used by delete_author_handler() and add_author_handler()
  *  Check if authors exist on local WP instance
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param    $host_id, $author_id, $AUTHORS
  *  @return  boolean
  */

  function local_author_exists( $host_id, $author_id, $AUTHORS ) {

    foreach ( $AUTHORS as $author ) {
      if ( $author['author_id'] == $author_id && $author['host_id'] == $host_id ) {
        return true;
      }
    }

    return false;

  }

  /*
  *  remote_author_exists
  *
  *  Used by delete_author_handler() and add_author_handler()
  *  Checks if remote author exists on external host
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param    $host_id, $author_id, $developer_key
  *  @return  boolean
  */

  function remote_author_exists( $host_id, $author_id, $developer_key ) {

    $HOSTS = SP_EV_Admin::admin_get_hosts();
    $hostname = $HOSTS[$host_id]['host_name'];
    $ClassName = "SP_EV_" . $hostname;

    $response = $ClassName::remote_author_exists( $host_id, $author_id, $developer_key );

    return $response;

  }

  /*
  *  authorization_exists
  *
  *  Settings page
  *  Used by add_author_handler()
  *  Test if added author included enough credentials for a particular host
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param    $host_id, $developer_key, $secret_key, $auth_token
  *  @return  boolean
  */

  function authorization_exists( $host_id, $author_id, $developer_key, $secret_key, $auth_token ) {

    $HOSTS = SP_EV_Admin::admin_get_hosts();

    // get required keys for this host
    $api_keys = $HOSTS[$host_id]['api_keys'];

    foreach( $api_keys as $api_key ){
      $key = $api_key['id'];
      $required = $api_key['required'];

      if( !$$key && $required ) {
        return false;
      }
    }

    return true;

  }

  /*
  *  admin_columns
  *
  *  External Videos posts page
  *  Produces sortable columns in list view
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param    $columns
  *  @return  $columns
  */

  function admin_columns( $columns ) {

    $columns = array(
      'cb'          => '<input type="checkbox" />',
      'title'       => __('Video Title', 'external-videos'),
      'thumbnail'   => __('Thumbnail', 'external-videos'),
      'host'        => __('Host', 'external-videos'),
      'duration'    => __('Duration', 'external-videos'),
      'published'   => __('Published', 'external-videos'),
      'parent'      => __('Attached to', 'external-videos'),
      'categories'  => __('Categories', 'external-videos'),
      'tags'        => __('Tags', 'external-videos'),
      'comments'    => __('Comments', 'external-videos')
    );

    return $columns;

  }

  /*
  *  admin_custom_columns
  *
  *  External Videos posts page
  *  Adds custom sortable columns in list view
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param    $column_name
  *  @return
  */

  function admin_custom_columns( $column_name ) {

    global $post;
    $duration  = get_post_meta( $post->ID, 'duration' );
    $host      = get_post_meta( $post->ID, 'host_id' );
    $thumbnail = get_post_meta( $post->ID, 'thumbnail_url' );
    if( is_array( $duration ) ) $duration = array_shift( $duration );
    if( is_array( $host ) ) $host = array_shift( $host );
    if( is_array( $thumbnail ) ) $thumbnail = array_shift( $thumbnail );

    switch( $column_name ) {
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

      case 'categories':
        echo $post->post_category;
        break;

      case 'parent':
        if ( $post->post_parent > 0 ) {
          if ( get_post( $post->post_parent ) ) {
              $title =_draft_or_post_title( $post->post_parent );
          }
          ?>
          <strong><a href="<?php echo get_edit_post_link( $post->post_parent ); ?>"><?php echo $title ?></a></strong>, <?php echo get_the_time( __( 'Y/m/d', 'external-videos' ) ); ?><br/>
          <a class="hide-if-no-js" onclick="findPosts.open( 'media[]','<?php echo $post->ID ?>' );return false;" href="#the-list"><?php _e( 'Change' ); ?></a>
          <?php
        } else {
          ?>
          <?php _e( '(Unattached)' ); ?><br />
          <a class="hide-if-no-js" onclick="findPosts.open( 'media[]','<?php echo $post->ID ?>' );return false;" href="#the-list"><?php _e( 'Attach' ); ?></a>
          <?php
        }

      break;
    }
  }

  /*
  *  daily_function
  *
  *  cron job to check video channels and add them as posts to database if found
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  0.26
  *
  *  @param
  *  @return
  */

  function daily_function() {

    $options = SP_External_Videos::get_options();
    $this->post_new_videos( $options['authors'], $options['delete'] );

  }


} // end class
endif;

/*
* Instantiate the class
*/

global $SP_EV_Admin;
$SP_EV_Admin = new SP_EV_Admin();
?>
