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

    // host_name must be the last part of the Class Name
    $class = get_class();
    $hostname = preg_split( "/SP_EV_/", $class, 2, PREG_SPLIT_NO_EMPTY );
    $hostname = $hostname[0];

    $options = SP_External_Videos::admin_get_options();

    if( !isset( $options['hosts']['dotsub'] ) ):

      $options['hosts']['dotsub'] = array(
        'host_id' => 'dotsub',
        'host_name' => $hostname,
        'api_keys' => array(
          array(
            'id' => 'author_id',
            'label' => 'User ID',
            'required' => true,
            'explanation' => ''
          )
        ),
        'introduction' => "DotSub only requires a User ID in order to access your videos from another site.",
        'url' => 'https://dotsub.com',
        'link_title' => 'DotSub'
      );

      update_option( 'sp_external_videos_options', $options );

    endif;

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
    $body = json_decode( wp_remote_retrieve_body( $response ) );
    $result = $body->result;

    // return false on empty media result. This is a Dotsub quirk.
    if( !$result ) {
      return false;
    }

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
  *  @param   $video_id
  *  @return  <iframe>
  */

  public static function embed_code( $video_id ) {

    return "//dotsub.com/media/$video_id/embed/";

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

  static function fetch( $author ) {

    $author_id = $author['author_id'];

    $baseurl = "https://dotsub.com/api/user/" . $author_id . "/media";
    $pagesize = 20;  // pagesize is automatically 20 at dotsub
    $offset = 0; // which is fine, because the API is slow. Don't increase
    $currentPage = 1;
    $url = $baseurl . '?pagesize=' . $pagesize .'&offset=' . $offset; // At first. Then we start adding offsets

    // for the request
    $headers = array(
      'Authorization' => 'Basic',
      'Content-Type' => 'application/json'
    );
    $args = array(
      'headers'     => $headers,
      'timeout' => 25
    );

    $new_videos = array();

    do {
      try {
        $response = wp_remote_get( $url, $args );
        $code = wp_remote_retrieve_response_code( $response );
        $message = wp_remote_retrieve_response_message( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ) );
        $result = $body->result;
        $count = count( $result );
        $totalPages = $body->totalPages;
      }
      catch ( Exception $e ) {
        echo "Encountered an API error -- code {$e->getCode()} - {$e->getMessage()}";
      }

      foreach ( $result as $vid )
      {
        // extract fields
        $video = array();
        $video['host_id']     = 'dotsub';
        $video['author_id']   = strtolower( $author_id );
        $video['video_id']    = $vid->uuid;
        $video['title']       = $vid->title;
        $video['description'] = $vid->description;
        $video['authorname']  = $vid->user;
        $video['videourl']    = $vid->displayURI;
        $video['published']   = date("Y-m-d H:i:s", strtotime( $vid->dateCreated ) );
        $video['author_url']  = $vid->externalIdentifier;
        $video['category']    = '';
        $video['keywords']    = array();
        $video['thumbnail']   = $vid->screenshotURI;
        $video['duration']    = $vid->duration;
        $video['ev_author']   = isset( $author['ev_author'] ) ? $author['ev_author'] : '';
        $video['ev_category'] = isset( $author['ev_category'] ) ? $author['ev_category'] : '';
        $video['ev_post_format'] = isset( $author['ev_post_format'] ) ? $author['ev_post_format'] : '';
        $video['ev_post_status'] = isset( $author['ev_post_status'] ) ? $author['ev_post_status'] : '';

        // add $video to the end of $new_videos
        array_push( $new_videos, $video );

      }

      // next page
      $currentPage++;
      // update request url to next page - it's offset by #videos, not pages
      $offset = $offset + $pagesize;
      $url = $baseurl . '?pagesize=' . $pagesize .'&offset=' . $offset;

    } while ( $totalPages > $currentPage );

    return $new_videos;

  }

} // end class

endif;

$SP_EV_Dotsub = new SP_EV_Dotsub;

?>
