<?php

if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if( ! class_exists( 'SP_EV_Wistia' ) ) :

class SP_EV_Wistia {

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
    // Oembed support for wistia
    wp_oembed_add_provider( '/https?:\/\/(.+)?(wistia\.(com|net)|wi\.st)\/.*/', 'http://fast.wistia.net/oembed', true );

    // host_name must be the last part of the Class Name
    $class = get_class();
    $host_name = preg_split( "/SP_EV_/", $class, 2, PREG_SPLIT_NO_EMPTY );
    $host_name = $host_name[0];

    $options = SP_External_Videos::get_options();
    if( !isset( $options['hosts']['wistia']['authors'] ) ) {
      $authors = array();
    } else {
      $authors = $options['hosts']['wistia']['authors'];
    }

    $options['hosts']['wistia'] = array(
      'host_id' => 'wistia',
      'host_name' => $host_name,
      'api_keys' => array(
        array(
          'id' => 'author_id',
          'label' => 'Account Name',
          'required' => true,
          'explanation' => ''
        ),
        array(
          'id' => 'developer_key',
          'label' => 'API Token',
          'required' => true,
          'explanation' => 'This needs to be generated in your Wistia account'
        )
      ),
      'introduction' => "Wistia's API requires you to generate an API token from your account, in order to access your videos from another site (like this one).",
      'api_url' => 'https://wistia.com',
      'api_link_title' => 'Wistia',
      'authors' => $authors
    );

    update_option( 'sp_external_videos_options', $options );

  }

  public static function echo(){
    $class = get_class();
    echo 'Class is ' .  $class . '<br />';
    $hostname = preg_split( "/SP_EV_/", $class, 2, PREG_SPLIT_NO_EMPTY );
    print_r($hostname);
    return;
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

    $url = "https://api.wistia.com/v1/account.json";
    $headers = array( 'Authorization' => 'Basic ' . base64_encode( "api:$developer_key" ) );
    $args['headers'] = $headers;

    $response = wp_remote_request( $url, $args );
    $code = wp_remote_retrieve_response_code( $response );
    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    // return false on error
    if( !$response || is_wp_error( $response ) || preg_match('/^[45]/', $code ) ) {
      return false;
    }

    // for wistia: also check that this api key belongs to this user account
    $userUrl = $body['url'];
    $expectUrl = "http://$author_id.wistia.com";

    if ( $userUrl != $expectUrl ) {
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

    return "//fast.wistia.net/embed/iframe/$video_id";

  }

  /*
  *  fetch
  *
  *  WISTIA DATA API v1
  *  check https://wistia.com/doc/data-api#making_requests
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
    $developer_key = $author['developer_key'];

    // set other options
    $page = 0;
    $per_page = 1;  // One by one because we get no next-page info from Wistia
    // Size could alternatively be set in options at some point
    // below is the WordPress Media "thumbnail" image setting, with defaults at 180x135
    $thumb_w = ( null !== get_option( "thumbnail_size_w" ) ) ? get_option( "thumbnail_size_w" ) : 180;
    $thumb_h = ( null !== get_option( "thumbnail_size_h" ) ) ? get_option( "thumbnail_size_h" ) : 135;


    $baseurl = "https://api.wistia.com/v1/medias.json?sort_by=created&sort_direction=1&per_page=" . $per_page;
    // part of the url changes on subsequent page requests:
    $url = $baseurl . "&page=" . $page;

    // send request; wistia is slow, so we're requesting one page at a time
    $headers = array(
      'Authorization' => 'Basic ' . base64_encode( "api:$developer_key" )
    );
    $args = array(
      'headers' => $headers,
      'timeout' => 25
    );

    $new_videos = array();

    do {
      // fetch videos
      try {
        $response = wp_remote_get( $url, $args );
        $code = wp_remote_retrieve_response_code( $response );
        $message = wp_remote_retrieve_response_message( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
      }
      catch ( Exception $e ) {
        echo "Encountered an API error -- code {$e->getCode()} - {$e->getMessage()}";
      }

      // loop through videos returned & extract fields
      foreach ($body as $vid) {
        $video = array();
        $video['host_id']     = 'wistia';
        $video['author_id']   = strtolower($author_id);
        $video['video_id']    = $vid['hashed_id'];
        $video['title']       = $vid['name'];
        $video['description'] = $vid['description'];
        $video['authorname']  = $author_id;
        $video['videourl']    = "https://$author_id.wistia.com/medias/" . $vid['hashed_id'];
        $video['published']   = date( "Y-m-d H:i:s", strtotime( $vid['created'] ));
        $video['author_url']  = "https://$author_id.wistia.com/projects";
        $video['category']    = '';
        $video['keywords']    = array();
        // $video['thumbnail']   = $vid['thumbnail']['url'];

        // WISTIA DELIVERS HUGE THUMBNAILS AUTO CROPPED FROM THEIR API.
        // IF YOU WANT A SMALLER CROP OF THE THUMBNAIL,
        // RIGHT TRIM THE URL UNTIL THE EQUALS, THEN ADD WIDTH . x . HEIGHT
        $thumbnail_url  = $vid['thumbnail']['url'];
        $equipos = strripos( $thumbnail_url, "=" ) ? strripos( $thumbnail_url, "=" ) : 0;
        $thumbnail_url = substr( $thumbnail_url, 0, $equipos ) . "=" . $thumb_w . "x" . $thumb_h;

        $video['thumbnail']   = $thumbnail_url;
        $video['duration']    = $vid['duration'];
        $video['ev_author']   = isset( $author['ev_author'] ) ? $author['ev_author'] : '';
        $video['ev_category'] = isset( $author['ev_category'] ) ? $author['ev_category'] : '';
        $video['ev_post_format'] = isset( $author['ev_post_format'] ) ? $author['ev_post_format'] : '';
        $video['ev_post_status'] = isset( $author['ev_post_status'] ) ? $author['ev_post_status'] : '';

        // add $video to the end of $new_videos array
        array_push( $new_videos, $video );
      }

      // next page
      $page++;
      // update request url to next page
      $url = $baseurl . "&page=" . $page . "&per_page=" . $per_page;

    } while ( $body );

    return $new_videos;

  }

} // end class

endif;

$SP_EV_Wistia = new SP_EV_Wistia;

?>
