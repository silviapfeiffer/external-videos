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
    add_action( 'wp_ajax_author_list_handler', array( $this, 'author_list_handler' ) );
    add_action( 'wp_ajax_author_host_list_handler', array( $this, 'author_host_list_handler' ) );
    add_action( 'wp_ajax_delete_author_handler', array( $this, 'delete_author_handler' ) );
    add_action( 'wp_ajax_add_author_handler', array( $this, 'add_author_handler' ) );

    add_filter( 'manage_edit-external-videos_columns', array( $this, 'admin_columns' ) );
    add_filter( 'manage_edit-external-videos_sortable_columns', array( $this, 'admin_sortable_columns' ) );
    add_action( 'manage_posts_custom_column', array( $this, 'admin_custom_columns' ) );

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

  public static function wrap_admin_notice( $message, $type ){

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
    $options['embed'] = ( array_key_exists( 'ev-embed', $fields ) ? true : false );
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
      $html .= '<input type="text" class="regular-text" name="' . esc_attr( $key_id ) . '" value="' . esc_attr( $$key_id ) . '" name="' . esc_attr( $key_id ) . '"/>';
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

    // error_log('$_POST:\n' . print_r( $_POST, true));

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
    // error_log( 'options before: \n' . print_r( $options, true ) );

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
    elseif ( ( $author['edit_form'] != "true" ) &&
             $this->local_author_exists( $author['host_id'],
                                         $author['author_id'],
                                         $options ) ) {
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

      // error_log( 'options after: \n' . print_r( $options, true ) );

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

    // error_log("local_author_exists:\n" . print_r( $options, true ));

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
        if ( $post->post_parent > 0 &&
             $parent = get_post( $post->post_parent ) ) {
          $title =_draft_or_post_title( $parent );
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


} // end class
endif;

/*
* Instantiate the class
*/

global $SP_EV_Admin;
$SP_EV_Admin = new SP_EV_Admin();
?>
