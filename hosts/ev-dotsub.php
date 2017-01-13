<?php

if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if( ! class_exists( 'SP_EV_Dotsub' ) ) :

class SP_EV_Dotsub {

  public function __construct() {
    add_action( 'init', array( $this, 'initialize' ) );
  }

  /*
  *  initialize
  *
  *  Set up the sp_external_videos_options table for this host
  *  so that host functions can be modularized and accessed from the db
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param
  *  @return
  */

  function initialize() {

    // Do we need to add oEmbed support for this host?
    // Oembed support for dotsub
    wp_oembed_add_provider( '#https://(www\.)?dotsub\.com/view/.*#i', 'https://dotsub.com/services/oembed?url=', true );

    // host_name must be the last part of the Class Name
    $class = get_class();
    $hostname = preg_split( "/SP_EV_/", $class, 2, PREG_SPLIT_NO_EMPTY );
    $hostname = $hostname[0];

    $options = SP_External_Videos::get_options();
    if( !isset( $options['hosts']['dotsub']['authors'] ) ) {
      $authors = array();
    } else {
      $authors = $options['hosts']['dotsub']['authors'];
    }

    $options['hosts']['dotsub'] = array(
      'host_id' => 'dotsub',
      'host_name' => $hostname,
      'api_keys' => array(
        array(
          'id' => 'author_id',
          'label' => __( "User ID", "external-videos" ),
          'required' => true,
          'explanation' => ''
        )
      ),
      'introduction' => __( "Dotsub only requires a User ID in order to access your videos from another site. Note: the Dotsub server is quite slow - if you get an error adding an author, try again. To display your videos properly in your theme, you may also need to install the plugin FitVids for Wordpress.", "external-videos" ),
      'api_url' => 'https://dotsub.com',
      'api_link_title' => 'Dotsub',
      'authors' => $authors
    );

    update_option( 'sp_external_videos_options', $options );

  }

  /*
  *  remote_author_exists
  *
  *  Requires appropriate method for THIS HOST
  *  Used by SP_External_Videos::remote_author_exists()
  *  Checks if remote author exists on this host
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param   $host_id, $author_id, $developer_key
  *  @return  boolean
  */

  public static function remote_author_exists( $host_id, $author_id, $developer_key ){

    // Note: basic URL "https://dotsub.com/api/user/$author_id" always returns 200 OK.
    // URL theoretically should work, but returns 200 OK even for non-users
    // while the title gives "Internal Error | Dotsub" even for valid users!
    // So we need to test media endpoint if there's media for the given user ID

    $url = "https://dotsub.com/api/user/" . $author_id . "/media";
    $args = array();

    $response = wp_remote_request( $url, $args );
    $code = wp_remote_retrieve_response_code( $response );
    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    $result = $body['result'];

    // return false on empty media result. This is a Dotsub quirk.
    if( !$result ) {
      return false;
    }

    return true;

  }

  /*
  *  embed_url
  *
  *  Used by fetch() and SP_EV_Admin::save_video()
  *  Embed url is stored as postmeta in external-video posts.
  *  Url is specific to each host site's embed API.
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  0.23
  *
  *  @param   $video_id
  *  @return  <iframe>
  */

  public static function embed_url( $video_id ) {

    return esc_url( sprintf( "https://dotsub.com/view/%s", $video_id ) );

  }

  /*
  *  compose_video
  *
  *  Used by fetch(), and eventually passed to SP_EV_Admin::save_video()
  *  Composes standardized $video array from idiosyncratic host data
  *  Data correspondence is specific to each host site's embed API
  *
  *  @type  function
  *  @date  12/1/17
  *  @since  1.0
  *
  *  @param   $vid array
  *  @return  $video array
  */

  public static function compose_video( $vid, $author ) {

    $video = array();
    // extract fields
    $video['host_id']        = 'dotsub';
    $video['author_id']      = sanitize_text_field( strtolower( $author['author_id'] ) );
    $video['video_id']       = sanitize_text_field( $vid['uuid'] );
    $video['title']          = sanitize_text_field( $vid['title'] );
    $video['description']    = sanitize_text_field( $vid['description'] );
    $video['author_name']    = sanitize_text_field( $vid['user'] );
    $video['video_url']      = esc_url( $vid['displayURI'] );
    $video['embed_url']      = SP_EV_Dotsub::embed_url( $vid['uuid'] );
    $video['published']      = gmdate( "Y-m-d H:i:s", $vid['dateCreated']/1000 );
    $video['author_url']     = esc_url( $vid['externalIdentifier'] );
    $video['category']       = array();
    $video['tags']           = array();
    $video['thumbnail_url']  = esc_url( $vid['screenshotURI'] );
    $video['duration']       = gmdate( "H:i:s", $vid['duration']/1000 );
    $video['ev_author']      = isset( $author['ev_author'] ) ? $author['ev_author'] : '';
    $video['ev_category']    = isset( $author['ev_category'] ) ? $author['ev_category'] : array();
    $video['ev_post_format'] = isset( $author['ev_post_format'] ) ? $author['ev_post_format'] : '';
    $video['ev_post_status'] = isset( $author['ev_post_status'] ) ? $author['ev_post_status'] : '';

    return $video;

  }

  /*
  *  fetch
  *
  *  DOTSUB API v1
  *  check https://github.com/dotsub/api-samples
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param   $author
  *  @return  $new_videos
  */

  public static function fetch( $author ) {

    $author_id = $author['author_id'];

    $baseurl = "https://dotsub.com/api/user/" . $author['author_id'] . "/media";
    $pagesize = 20;  // pagesize is automatically 20 at dotsub
    $offset = 0; // which is fine, because the API is slow. Don't increase
    $url = $baseurl . '?pagesize=' . $pagesize .'&offset=' . $offset; // At first. Then we start adding offsets

    // for the request
    $headers = array(
      'Authorization' => 'Basic',
      'Content-Type'  => 'application/json'
    );
    $args = array(
      'headers' => $headers,
      'timeout' => 25
    );

    $currentPage = 1;
    $new_videos = array();

    do {
      try {
        $response = wp_remote_get( $url, $args );
        $code = wp_remote_retrieve_response_code( $response );
        $message = wp_remote_retrieve_response_message( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        // Adjust array to get to the "result"
        $result = $body['result'];
        // $count = count( $result );
        $totalPages = $body['totalPages'];
      }
      catch ( Exception $e ) {
        echo "Encountered an API error -- code {$e->getCode()} - {$e->getMessage()}";
      }

      foreach ( $result as $vid ) {
        $video = SP_EV_Dotsub::compose_video( $vid, $author );
        // add $video to the end of $new_videos
        array_push( $new_videos, $video );
      }

      // update request url to next page - it's offset by #videos, not pages
      $offset = $offset + $pagesize;
      $url = $baseurl . '?pagesize=' . $pagesize .'&offset=' . $offset;
      // next page
      $currentPage++;

    } while ( $totalPages > $currentPage );

    return $new_videos;

  }

} // end class

endif;

$SP_EV_Dotsub = new SP_EV_Dotsub;

?>
