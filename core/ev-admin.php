<?php

/*
*  SP_EV_Admin
*
*  Class for External Videos settings admin page functions
*
*  @type  class
*  @date  31/10/16
*  @since  1.0
*
*/

if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if( ! class_exists( 'SP_EV_Admin' ) ) :

class SP_EV_Admin {

  public function __construct() {

    add_action( 'init', array( $this, 'initialize' ) );

    add_action( 'wp_ajax_plugin_settings_handler', array( $this, 'plugin_settings_handler' ) );
    add_action( 'wp_ajax_update_videos_handler', array( $this, 'update_videos_handler' ) );
    add_action( 'wp_ajax_delete_all_videos_handler', array( $this, 'delete_all_videos_handler' ) );
    add_action( 'wp_ajax_author_list_handler', array( $this, 'author_list_handler' ) );
    add_action( 'wp_ajax_author_host_list_handler', array( $this, 'author_host_list_handler' ) );
    add_action( 'wp_ajax_delete_author_handler', array( $this, 'delete_author_handler' ) );
    add_action( 'wp_ajax_add_author_handler', array( $this, 'add_author_handler' ) );

    add_filter( 'manage_edit-external-videos_columns', array( $this, 'admin_columns' ) );
    add_filter( 'manage_edit-external-videos_sortable_columns', array( $this, 'admin_sortable_columns' ) );
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
  *  wrap_admin_notice
  *
  *  Settings page
  *  Returns html WP-admin-notice-style message with appropriate notice class
  *  Currently not dismissible because these are set to fadeOut via admin.js
  *  types are info, success, warning, error
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param $message, $type
  *  @returns $message html
  */

  function wrap_admin_notice( $message, $type ){

    $new_message = '<div class="notice notice-' . esc_attr( $type ) . '">'; //  is-dismissible
    $new_message .= '<p><strong>' . esc_attr( $message ) . '</strong></p>';
    // Keep this in case we want to make notices dismissible and use the admin notice hook
    // $new_message .= '<button type="button" class="notice-dismiss">';
    // $new_message .= '<span class="screen-reader-text">Dismiss this notice.</span>';
    // $new_message .= '</button>';
    $new_message .= '</div>';

    return $new_message;

  }

  /*
  *  get_hosts
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

  static function get_hosts(){

    $options = SP_External_Videos::get_options();
    $HOSTS = $options['hosts'];

    return $HOSTS;
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
    $old_slug = $options['slug'];
    $message = '';

    $fields = array();
    parse_str( $_POST['data'], $fields );

    // error_log( print_r( $fields, 1 ) );

    $options['rss'] = ( array_key_exists( 'ev-rss', $fields ) ? true : false );
    $options['delete'] = ( array_key_exists( 'ev-delete', $fields ) ? true : false );
    $options['attrib'] = ( array_key_exists( 'ev-attrib', $fields ) ? true : false );
    $options['loop'] = ( array_key_exists( 'ev-loop', $fields ) ? true : false );
    $options['slug'] = ( array_key_exists( 'ev-slug', $fields ) ? sanitize_title_with_dashes( $fields['ev-slug'] ) : '' );

    if( update_option( 'sp_external_videos_options', $options ) ) $message = __( "Settings saved. ", "external-videos" );
    if( ( '' != $options['slug'] ) && ( $old_slug != $options['slug'] ) ) {
      $message .= __( "Please go to Permalink Settings page and re-save for changes to take effect.", "external-videos" );
    }

    ob_clean();

    $data = array( 'message' => $message, 'slug' => $options['slug'] );

    wp_send_json( $data );

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

    // post_new_videos() gets everything new and returns messages about it
    $post_results = $this->post_new_videos( $update_hosts, $update_author );
    $new_messages = $post_results['messages'];
    $new_video_ids = $post_results['new_video_ids'];

    // trash_deleted_videos() checks for videos deleted on host and returns messages about it
    if( $delete ) {
      $trash_messages = $this->trash_deleted_videos( $update_hosts, $update_author, $new_video_ids );
    }

    $messages = $new_messages . $trash_messages;

    wp_send_json( $messages );

  }

  /*
  *  post_new_videos
  *
  *  Uses save_video()
  *  Used by update_videos_handler() and daily_function()
  *  Saves any new videos from host channels to the database.
  *  Returns messages about number of video posts added.
  *  Works for single-author and update-all
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param   $update_hosts, $update_author
  *  @return  array( html $messages, array $new_video_ids )
  */

  function post_new_videos( $update_hosts, $update_author = null ) {

    $new_video_ids = array();
    $new_videos = $this->fetch_new_videos( $update_hosts, $update_author );
    $messages = $add_messages = $no_messages = $zero_message = '';

    // If there's nothing new, return with message and the empty array of $new_video_ids
    if ( !$new_videos ) {
      $hostlist = array();
      foreach( $update_hosts as $host ){
        $hostlist[] = $host['host_name'];
      }
      $hostlist = implode( ', ', $hostlist );
      $zero_message = esc_attr__( "No new videos found on " . $hostlist . "." );
      $zero_message = $this->wrap_admin_notice( $zero_message, 'info' );

      return array(
        'messages'      => $zero_message,
        'new_video_ids' => $new_video_ids
      );
    }

    // If we're still here there's news on the channels.
    // we're going to count how many we add at each host
    $count_added = array();
    // have to fill out this array with zeros, or error
    foreach( $update_hosts as $host ){
      $host_id = $host['host_id'];
      $count_added[$host_id] = 0;
    }

    // save new videos & build list of all new video_ids
    foreach ( $new_videos as $video ) {
      // $new_video_ids is an array of the added video ids
      array_push( $new_video_ids, $video['video_id'] );
      // save_video() checks if is new, and saves video post
      $is_new = $this->save_video( $video );
      if ( $is_new ) {
        $host_id = $video['host_id'];
        $count_added[$host_id]++;
      }
    }

    // build messages about added videos, or no videos, per host
    foreach ( $count_added as $host_id=>$num ) {
      $host_name = $update_hosts[$host_id]['host_name'];
      if ( $num > 0 ) {
        $add_messages .= sprintf( _n( 'Found %1$s new video on %2$s. ', 'Found %1$s new videos on %2$s. ', $num, 'external-videos' ), $num, $host_name );
      }
      else {
        $no_messages .= "No new videos found on " . $host_name . '.';
      }
    }
    // after looping to get all add/no messages, wrap them up for delivery
    if( $add_messages ){
      $add_messages = $this->wrap_admin_notice( $add_messages, 'success' );
      $messages .= $add_messages;
    }
    if( $no_messages ){
      $no_messages = $this->wrap_admin_notice( $no_messages, 'info' );
      $messages .= $no_messages;
    }

    // return the messages and the array of new video ids, needed by trash_deleted_videos()
    return array(
      'messages'      => $messages,
      'new_video_ids' => $new_video_ids
    );

  }

  /*
  *  trash_deleted_videos()
  *
  *  Used by post_new_videos() and implicitly by update_videos_handler() and daily_function()
  *  Trashes any videos on WordPress that have been deleted from host channels.
  *  Returns messages about number of video posts trashed.
  *  We're only iterating by host, since we have a list of $new_video_ids to compare with existing posts.
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param   $update_hosts, $new_video_ids - passed from post_new_videos()
  *  @return  html $trash_messages
  */

  function trash_deleted_videos( $update_hosts, $update_author, $new_video_ids ) {

    // we're going to count how many were deleted at each host
    // must fill out this array with zeros, or error
    $count_deleted = array();

    foreach( $update_hosts as $host ){
      $host_id = $host['host_id'];
      $count_deleted[$host_id] = 0;
    }

    if( $update_author == null ){

      $existing_videos = new WP_Query( array(
        'post_type'  => 'external-videos',
        'nopaging' => 1
      ) );

    } else {

      $host_id = $update_hosts[0]['host_id'];
      $author_id = $update_author['author_id'];

      $existing_videos = new WP_Query( array(
        'post_type'  => 'external-videos',
        'nopaging' => 1,
        'meta_query' => array(
            array(
                'key'     => 'host_id',
                'value'   => $host_id
            ),
            array(
                'key'     => 'author_id',
                'value'   => $author_id
            )
        )
      ) );
    }

    while( $existing_videos->have_posts() ) {

      $existing_video = $existing_videos->next_post();
      $video_id = get_post_meta( $existing_video->ID, 'video_id', true );
      $host = get_post_meta( $existing_video->ID, 'host_id', true );

      // Move external-video to trash if not in array of $new_video_ids passed from the post_new_videos() function
      if ( $video_id != NULL && !in_array( $video_id, $new_video_ids ) ) {
        $post = get_post( $existing_video->ID );
        $post->post_status = 'trash';
        wp_update_post( $post );
        //update count of deleted videos on this host
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
      $trash_messages = $this->wrap_admin_notice( $trash_messages, 'warning' );
      // return the messages
      return $trash_messages;
    }

    return '';
  }


  /*
  *  fetch_new_videos
  *
  *  Used by post_new_videos()
  *  Fetch new videos from a registered, externally hosted channel, or from all.
  *  The various API functions are defined in separate classes for each host.
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param   $update_hosts, $update_author
  *  @return  $new_videos (array of videos)
  */

  function fetch_new_videos( $update_hosts, $update_author ) {

    // error_log( '$update_hosts: ' .  print_r( $update_hosts, true ) );
    // error_log( '$update_author: ' .  print_r( $update_author, true ) );

    $new_videos = array();

    foreach ( $update_hosts as $host ) {

      $host_name = $host['host_name'];
      $ClassName = "SP_EV_".$host_name;
      // error_log( '$ClassName: ' .  print_r( $ClassName, true ) );

      if( $update_author == null ){
        // fetch all hosts, all authors
        foreach( $host['authors'] as $author ){
          $author_videos = $ClassName::fetch( $author );
          $new_videos = array_merge( $author_videos, $new_videos );
        }
      } else {
        // fetch single author's videos
        $new_videos = $ClassName::fetch( $update_author );
      }
    }

    return $new_videos;

  }

  /*
  *  save_video
  *
  *  Used by update_videos() and update_videos_handler()
  *  Creates a post of type "external-videos" and saves it.
  *  The passed $video array contains the fields we need to make the post,
  *  all except "embed_url" (provided by embed_url()).
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
    $video_content .= esc_url( $video['embed_url'] );
    $video_content .= "\n\n";
    $video_content .= '<p>' . sanitize_text_field( trim( $video['description'] ) ) . '</p>';

    // get options, to check if user wants the rest of content
    $options = SP_External_Videos::get_options();

    if( $options['attrib'] == true ) {
      $video_content .= '<p><small>';
      if ( $video['category'] != '' ) {
        $video_content .= '<i>' . esc_attr__( "Category:" , 'external-videos' ) . ' </i>';
        $categories = array_map( 'esc_attr', (array) $video['ev_category'] );
        $video_content .= implode( ', ', $categories );
        $video_content .= '<br/>';
      }
      $video_content .= '<i>' . esc_attr__( "Uploaded by:" , 'external-videos' ) . ' </i>';
      $video_content .= '<a href="' . esc_url( $video['author_url'] ) . '">';
      $video_content .= sanitize_text_field( $video['author_name'] ) . '</a>';
      $video_content .= '<br/>';
      $video_content .= '<i>' . esc_attr__( "Hosted:" , 'external-videos' ) . ' </i>';
      $video_content .= '<a href="' . esc_url( $video['video_url'] ) . '">';
      $video_content .= sanitize_text_field( $video['host_id'] ) . '</a>';
      $video_content .= '</small></p>';
    }

    // prepare post
    $video_post = array();
    $video_post['post_type']      = 'external-videos';
    $video_post['post_title']     = sanitize_text_field( $video['title'] );
    $video_post['post_content']   = apply_filters( 'the_content', $video_content );
    $video_post['post_status']    = sanitize_text_field( $video['ev_post_status'] );
    $video_post['post_author']    = sanitize_user( $video['ev_author'] );
    $video_post['post_date']      = gmdate( "Y-m-d H:i:s", strtotime( $video['published'] ) );
    $video_post['tags_input']     = array_map( 'esc_attr', $video['tags'] );
    $video_post['post_mime_type'] = 'import';
    $video_post['post_excerpt']   = sanitize_text_field( $video['description'] );

    // save to DB
    $post_id = wp_insert_post( $video_post );
    $post = get_post( $post_id );

    // set post format
    if ( current_theme_supports( 'post-formats' ) &&
      post_type_supports( $post->post_type, 'post-formats' )) {
      set_post_format( $post, $video['ev_post_format'] );
    }

    // add post meta
    add_post_meta( $post_id, 'host_id',       sanitize_text_field( $video['host_id'] ) );
    add_post_meta( $post_id, 'author_id',     sanitize_text_field( $video['author_id'] ) );
    add_post_meta( $post_id, 'video_id',      sanitize_text_field( $video['video_id'] ) );
    add_post_meta( $post_id, 'duration',      $video['duration'] ); // how to sanitize?
    add_post_meta( $post_id, 'author_url',    esc_url( $video['author_url'] ) );
    add_post_meta( $post_id, 'video_url',     esc_url( $video['video_url'] ) );
    add_post_meta( $post_id, 'thumbnail_url', esc_url( $video['thumbnail_url'] ) );
    // Cheat here with a dummy image so we can show thumbnails properly
    add_post_meta( $post_id, '_wp_attached_file', 'dummy.png' );
    add_post_meta( $post_id, 'description',   sanitize_text_field( $video['description'] ) );
    // video embed code. To do: meta key should be converted to "embed_url" for consistency
    add_post_meta( $post_id, 'embed_code',    esc_url( $this->embed_url( $video['host_id'], $video['video_id'] ) ) );

    // category id & tag attribution
    if( !is_array( $video['ev_category'] ) ) $video['ev_category'] = (array) $video['ev_category'];
    wp_set_post_categories( $post_id,         array_map( 'esc_attr', $video['ev_category'] ) );
    if( !is_array( $video['tags'] ) ) $video['tags'] = (array) $video['tags'];
    wp_set_post_tags( $post_id,               array_map( 'esc_attr', $video['tags'] ), 'post_tag' );

    return true;
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
    $messages = sprintf( _n( 'Moved %d video into trash.', 'Moved %d videos into trash.', $count_deleted, 'external-videos' ), $count_deleted );
    $messages = esc_attr( $messages );
    $messages = $this->wrap_admin_notice( $messages, 'info' );

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
    $ev_posts = new WP_Query( array(
      'post_type' => 'external-videos',
      'nopaging' => 1
    ) );

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
  *  author_list_handler
  *
  *  Used by settings page "Update Videos" section
  *  AJAX handler to reload the main author list form with fresh db info
  *  Should exactly mirror the html in ev-settings-forms.php
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param $_POST serialized form
  *  @return html form
  */

  function author_list_handler() {

    check_ajax_referer( 'ev_settings' );

    // faster with one query
    $HOSTS = SP_EV_Admin::get_hosts();

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
      $host_name = $host['host_name'];
      $host_authors = isset( $host['authors'] ) ? $host['authors'] : array();

      // if we have a channel on this host, build a row
      if( $host_authors ) {

        $html .= '<tr>';
        $html .= '<th scope="row" class="w-25 row-title">';
        $html .= '<strong>' . $host_name . '</strong>';
        $html .= '</th>';
        $html .= '<td>';
        $html .= '<table class="form-table ev-table" style="margin-top:0;">';

        foreach( $host_authors as $author ) {
          $html .= '<tr>';
          $html .= '<td class="w-33">';// id="' . $author['host_id'] . '-' . $author['author_id'] . '">';
          // $html .= '<p>';
          $html .= '<span>' . $author['author_id'] . '</span>';
          $html .= '</td>';
          $html .= '<td class="w-25 ev-table-check text-align-right">';
          $html .= '<input type="submit" class="button-update button" value="' . __( 'Update Videos' ) . '" data-host="' .  $author['host_id'] . '" data-author="' . $author['author_id'] . '" /><div class="spinner"></div>';
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
  *  author_host_list_handler
  *
  *  Used by host tab on settings page
  *  AJAX handler to reload the host's author list form with fresh db info
  *  Should exactly mirror the html in ev-settings-forms.php
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param $_POST serialized form
  *  @return html form
  */

  function author_host_list_handler() {

    check_ajax_referer( 'ev_settings' );

    $html = $edit_html = $add_html = '';
    $options = SP_External_Videos::get_options();
    $HOSTS = $options["hosts"];
    $host_id = $_POST["data"]["host_id"];
    $host = $HOSTS[$host_id];

    if( isset( $host["authors"] ) ) :
      foreach( $host["authors"] as $author ){
        // $edit_html .= "<pre>AUTHOR: " . print_r( $author, true ) . "</pre>";
        $edit_html .= $this->author_host_list_html( 'edit', $author, $host, $edit_html );
      }
    endif;

    // Send this blank "author" to the html form callback for an add author form
    $author = array(
      'host_id' => $host_id,
      'author_id' => '',
      'developer_key' => '',
      'secret_key' => '',
      'auth_token' => '',
      'ev_author' => '',
      'ev_category' => array(),
      'ev_post_format' => '',
      'ev_post_status' => ''
    );
    $add_html = $this->author_host_list_html( 'add', $author, $host, '' );

    $html = $edit_html . $add_html;
    wp_send_json( $html );

  }

  /*
  *  author_host_list_html
  *
  *  Used by author_host_list_handler
  *  Outputs either an ev_edit_author HTML form for a given $author,
  *          or an ev_add_author form.
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param $type string ('edit', 'add'), $author array, $host array, $html (previous)
  *  @return additional html
  */

  function author_host_list_html( $type, $author, $host, $html ) {

    if( $type != 'edit' && $type != 'add' ) return;
    // Fill out all $author keys even if not set, so no error
    // eg, $author_id = isset( $author['author_id'] ) ? $author['author_id'] : '';
    foreach( $author as $key=>$value ){
      $$key = isset( $value ) ? $value : '';
    }

    if( $type == 'edit' ) {
      $html = '<form id="ev_edit_' . esc_attr( $author_id ) . '" class="ev_edit_author ev-host-table" method="post" action="">';
    } else {
      $html = '<form id="ev_add_' . esc_attr( $host_id ) . '" class="ev_add_author ev-host-table" method="post" action="">';
    }
    $html .= '<input type="hidden" name="host_id" value="' . esc_attr( $host_id ) . '" />';
    $html .= '<input type="hidden" name="edit_form" value="true" />';
    $html .= '<table class="wp-list-table widefat">';

    // AUTHOR TABLE HEAD
    $html .= '<thead>';
    if( $type == 'edit' ) {
      $html .= '<tr>';
      $html .= '<th scope="row">';
      $html .= '<strong>' . esc_attr( $author_id ) . '</strong>';
    } else {
      $html .= '<tr class="alternate">';
      $html .= '<th scope="row">';
      $html .= '<strong>' . esc_attr__( "Add New Channel", "external-videos" ) . '</strong>';
    }
    $html .= '</th>';
    $html .= '<td class="text-align-right">';
    if( $type == 'edit' ) {
      $html .= '<button class="button delete-author" data-host="' . $author['host_id'] . '" data-author="' . $author['author_id'] . '">';
      $html .= esc_attr__( 'Delete', 'external-videos' ) . '</button>';
      $html .= '<button type="submit" name="submit" class="button button-primary save-author">';
      $html .= esc_attr__( 'Save', 'external-videos' ) . '</button>';
      $html .= '<a href="#" class="edit-author button" data-toggle="collapse">';
      $html .= '<span class="closed">' . esc_attr__( 'Edit', 'external-videos' ) . '</span>';
      $html .= '<span class="open">' . esc_attr__( 'Cancel', 'external-videos' ) . '</span></a>';
    } else {
      $html .= '<button type="submit" name="submit" class="button button-primary save-author">';
      $html .= esc_attr__( 'Add', 'external-videos' ) . '</button>';
      $html .= '<a href="#" class="edit-author button" data-toggle="collapse">';
      $html .= '<span class="closed">' . esc_attr__( '+', 'external-videos' ) . '</span>';
      $html .= '<span class="open">' . esc_attr__( 'Cancel', 'external-videos' ) . '</span></a>';
  }
    $html .= '</td>';
    $html .= '</tr>';
    $html .= '</thead>';

    if( $type == 'edit' ) {
      $html .= '<tbody class="info-author collapse" aria-expanded="false">';
    } else {
      $html .= '<tbody class="info-author collapse" aria-expanded="false">';
    }

    // API KEYS ROWS
    foreach( $host["api_keys"] as $key ){
      $key_id = $key['id'];
      $required = ( isset( $key['required'] ) && $key['required'] == true ) ? 'Required' : 'Optional';
      $explanation = $key['explanation'];
      if( $required && $explanation ) $explanation = " - " . $explanation;
      $html .= '<tr>';
      $html .= '<th scope="row">';
      $html .= '<span>' . esc_attr( $key['label'] ) . '</span>';
      $html .= '</th>';
      $html .= '<td>';
      $html .= '<input type="text large-text" name="' . esc_attr( $key_id ) . '" value="' . esc_attr( $$key_id ) . '" name="' . esc_attr( $key_id ) . '"/>';
      $html .= '<span class="description">' . esc_attr( $required . $explanation ) . '</span>';
      $html .= '</td>';
      $html .= '</tr>';
    }

    // POST STATUS ROW
    $html .= '<tr>';
    $html .= '<th scope="row">';
    $html .= esc_attr__('Set Post Status', 'external-videos');
    $html .= '</th>';
    $html .= '<td>';
    $html .= '<select name="post_status" id="ev_post_status">';
    $post_stati = get_post_stati( array( 'internal' => false, '_builtin' => true ), 'object' );
    foreach ( $post_stati as $post_status ) {
      $html .= '<option ' . selected( $ev_post_status, $post_status->name, false ) . ' value="' . esc_attr( $post_status->name ). '">' . esc_html( $post_status->label ) . '</option>';
    }
    $html .= '</select>';
    $html .= '</td>';
    $html .= '</tr>';

    // DEFAULT USER ROW
    $html .= '<tr>';
    $html .= '<th scope="row">';
    $html .= esc_attr__("Default WP Author", "external-videos");
    $html .= '</th>';
    $html .= '<td>';
    $html .= wp_dropdown_users( array( 'echo' => false, 'selected' => $ev_author ) );
    $html .= '</td>';
    $html .= '</tr>';

    // POST FORMAT ROW
    $html .= '<tr>';
    $html .= '<th scope="row">';
    $html .= esc_attr__("Default Post Format");
    $html .= '</th>';
    $html .= '<td>';
    $post_formats = get_post_format_strings();
    unset( $post_formats["video"] );
    $html .= '<select name="post_format" id="ev_post_format">';
    $html .= '<option value="video">' . get_post_format_string( 'video' ) . '</option>';
    foreach ( $post_formats as $format_slug => $format_name ) {
      $html .= '<option ' . selected( $ev_post_format, $format_slug, false ) . ' value="' . esc_attr( $format_slug ). '">' . esc_html( $format_name ) . '</option>';
    }
    $html .= '</select>';
    $html .= '</td>';
    $html .= '</tr>';

    // POST CATEGORY ROW
    $html .= '<tr>';
    $html .= '<th scope="row" class="v-top">';
    $html .=  esc_attr__('Default Post Category');
    $html .= '</th>';
    $html .= '<td>';
    $html .= '<ul class="category-box">';
    $html .= wp_terms_checklist( 0, array( 'selected_cats' => $ev_category, 'echo' => false ) );
    $html .= '</ul>';
    $html .= '</td>';
    $html .= '</tr>';

    $html .= '</tbody>';
    $html .= '</table>';
    $html .= '</form>';

    return $html;

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
  *  @param $_POST['host_id'], $_POST['author_id']
  *  @return $message about deleted author and videos, or error message
  */

  function delete_author_handler() {

    // Handle the ajax request
    check_ajax_referer( 'ev_settings' );

    $options = SP_External_Videos::get_options();
    $HOSTS = $options['hosts'];
    $this_host = $_POST['host_id'];
    $this_author = $_POST['author_id'];
    $message = '';

    // Does author even exist?
    if ( !$this->local_author_exists( $this_host, $this_author, $options ) ) {
      $message = __( "Can't delete a channel that doesn't exist.", 'external-videos' );
      $message = $this->wrap_admin_notice( $message, 'warning' );
    }

    else {
      // Start clearing channel options
      unset( $options['hosts'][$this_host]['authors'][$this_author] );

      // also move the channel's videos to the trash. count how many we're moving
      $count_trash = 0;

      // we need both host and author in query, in case author name repeats across different hosts
      $author_posts = new WP_Query( array(
        'post_type'  => 'external-videos',
        'meta_key'   => 'host_id',
        'meta_value' => $_POST['host_id'],
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
          $count_trash += 1;
        }
      }

      // Save the options without this channel
      if( update_option( 'sp_external_videos_options', $options ) ) {

        $message = sprintf( _n( 'Deleted channel %s from %s and moved %d video to trash.', 'Deleted channel %s from %s and moved %d videos to trash.', $count_trash, 'external-videos' ), $this_author, $this_host, $count_trash );

        $message = $this->wrap_admin_notice( $message, 'info' );
      }

    }

    wp_send_json( $message );

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

    // get existing options
    $options = SP_External_Videos::get_options();
    $messages = '';

    $author = array();
    $author['post_category'] = ''; // this one doesn't necessarily set
    parse_str($_POST['data'], $author);

    // error_log( print_r( $author, true ) );

    // Clean up surplus white space from entered data
    $author['author_id'] = isset( $author['author_id'] ) ? trim( sanitize_text_field( $author['author_id'] ) ) : '';
    $author['secret_key'] = isset( $author['secret_key'] ) ? trim( sanitize_text_field( $author['secret_key'] ) ) : '';
    $author['developer_key'] = isset( $author['developer_key'] ) ? trim( sanitize_text_field( $author['developer_key'] ) ) : '';
    $author['auth_token'] = isset( $author['auth_token'] ) ? trim( sanitize_text_field( $author['auth_token'] ) ) : '';

    if ( !array_key_exists( $author['host_id'], $options['hosts'] ) ) {
      $message = __( 'Invalid video host.', 'external-videos' );
      $message = $this->wrap_admin_notice( $message, 'error' );
      $messages .= $message;
    }

    // Check if local author already exists
    elseif ( ( $author['edit_form'] != "true" ) && $this->local_author_exists( $author['host_id'], $author['author_id'], $options ) ) {
      $message = __( 'Channel already exists.', 'external-videos' );
      $message = $this->wrap_admin_notice( $message, 'error' );
      $messages .= $message;
    }

    // Check if we don't have authentication with video service
    elseif ( !$this->authorization_exists( $author['host_id'], $author['author_id'], $author['developer_key'], $author['secret_key'], $author['auth_token'] ) ) {
      $message =  __( 'Missing required API key.', 'external-videos' );
      $message = $this->wrap_admin_notice( $message, 'error' );
      $messages .= $message;
    }

    // Check if author doesn't exist on video service
    elseif ( !$this->remote_author_exists( $author['host_id'], $author['author_id'], $author['developer_key'] ) ) {
      $host_id = $author['host_id'];
      $name = $options['hosts'][$host_id]['api_keys'][0]['label'];
      $message = sprintf( __( 'Invalid %s - check spelling.', 'external-videos' ), $name );
      $message = $this->wrap_admin_notice( $message, 'error' );
      $messages .= $message;
    }

    // If we pass these tests, set up the author/channel
    else {
      $author_id = $author['author_id'];
      $host_id = $author['host_id'];

      $options['hosts'][$host_id]['authors'][$author_id] = array(
        'host_id' => $host_id,
        'author_id' => $author_id,
        'developer_key' => $author['developer_key'],
        'secret_key' => $author['secret_key'],
        'auth_token' => $author['auth_token'],
        'ev_author' => $author['user'],
        'ev_category' => isset( $author['post_category'] ) ? $author['post_category'] : array(),
        'ev_post_format' => $author['post_format'],
        'ev_post_status' => $author['post_status']
      );

      // error_log( print_r( $options, true ) );

      if( update_option( 'sp_external_videos_options', $options ) ){
        if( $author['edit_form'] == "true" ){
          $message = sprintf( __( 'Edited %s channel settings', 'external-videos' ), $author['author_id'] );
        } else {
          $host_name = $options['hosts'][$host_id]['host_name'];
          $message = sprintf( __( 'Added %s channel from %s', 'external-videos' ), $author['author_id'], $host_name );
        }
        $message = $this->wrap_admin_notice( $message, 'success' );
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
  *  @param    $host_id, $author_id, $options
  *  @return  boolean
  */

  function local_author_exists( $host_id, $author_id, $options ) {

    if ( isset( $options['hosts'][$host_id]['authors'][$author_id] ) ) {
      return true;
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

    $HOSTS = SP_EV_Admin::get_hosts();
    $host_name = $HOSTS[$host_id]['host_name'];
    $ClassName = "SP_EV_" . $host_name;

    $response = $ClassName::remote_author_exists( $host_id, $author_id, $developer_key );

    return $response;

  }

  /*
  *  authorization_exists
  *
  *  Settings page validation function
  *  Used by add_author_handler()
  *  Test if added author included enough credentials for a particular host
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param    $host_id, $author_id, $developer_key, $secret_key, $auth_token
  *  @return  boolean
  */

  function authorization_exists( $host_id, $author_id, $developer_key, $secret_key, $auth_token ) {

    // get required keys for this host
    $HOSTS = SP_EV_Admin::get_hosts();
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
        echo "<img src='".$thumbnail."' style='width:100%'/>";
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
  *  admin_sortable_columns
  *
  *  External Videos posts page
  *  Makes custom columns sortable in list view
  *
  *  @type  function
  *  @date  12/01/17
  *  @since  1.1
  *
  *  @param    $sortable_columns
  *  @return
  */

  function admin_sortable_columns( $sortable_columns ) {

    $sortable_columns['duration'] = 'duration';
    $sortable_columns['published'] = 'published';
    $sortable_columns['host'] = 'host';

    return $sortable_columns;

  }

  /*
  *  daily_function
  *
  *  cron job to check video channels and add them as posts to database if found
  *  post_new_videos also runs trash_deleted_videos
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

    $this->post_new_videos( $HOSTS, null ); // all hosts, all authors

  }


} // end class
endif;

/*
* Instantiate the class
*/

global $SP_EV_Admin;
$SP_EV_Admin = new SP_EV_Admin();
?>
