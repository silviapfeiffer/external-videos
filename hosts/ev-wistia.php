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
          'label' => __( "Account Name", "external-videos" ),
          'required' => true,
          'explanation' => ''
        ),
        array(
          'id' => 'developer_key',
          'label' => __( "API Token", "external-videos" ),
          'required' => true,
          'explanation' => __( "This needs to be generated in your Wistia account", "external-videos" )
        )
      ),
      'introduction' => __( "Wistia's API requires you to generate an API token from your account, in order to access your videos from another site (like this one). To display your videos properly in your theme, you may also need to install the plugin FitVids for Wordpress.", "external-videos" ),
      'api_url' => 'https://wistia.com',
      'api_link_title' => 'Wistia',
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

    return esc_url( sprintf( "https://fast.wistia.net/embed/iframe/%s", $video_id ) );

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
    $video['host_id']        = 'wistia';
    $video['author_id']      = sanitize_text_field( strtolower( $author['author_id'] ) );
    $video['video_id']       = sanitize_text_field( $vid['hashed_id'] );
    $video['title']          = sanitize_text_field( $vid['name'] );
    $video['description']    = sanitize_text_field( $vid['description'] );
    $video['author_name']    = sanitize_text_field( $author['author_id'] );
    $video['video_url']      = esc_url( "https://" . $author['author_id'] . ".wistia.com/medias/" . $vid['hashed_id'] );
    $video['embed_url']      = SP_EV_Wistia::embed_url( $vid['hashed_id'] );
    $video['published']      = date( "Y-m-d H:i:s", strtotime( esc_attr( $vid['created'] ) ) );
    $video['author_url']     = esc_url( "https://" . $author['author_id'] . ".wistia.com/projects" );
    $video['category']       = array();
    $video['tags']           = array();
    // $video['thumbnail']   = $vid['thumbnail']['url'];

    // Wistia API delivers a huge thumbnail by default. But also an endpoint.
    // If we want a smaller thumbnail, we have to trim off the url after the "="
    // then append "$widthx$height". Size could alternatively be set in options.
    // get_option( "thumbnail_size_w" ) checks WordPress Media "thumbnail" size setting, defaults 180x135
    $thumb_w = 180;
    $thumb_h = 135;
    $thumbnail_url           = esc_url( $vid['thumbnail']['url'] );
    $equipos                 = strripos( $thumbnail_url, "=" ) ? strripos( $thumbnail_url, "=" ) : 0;
    $thumbnail_url           = substr( $thumbnail_url, 0, $equipos ) . "=" . $thumb_w . "x" . $thumb_h;

    $video['thumbnail_url']  = $thumbnail_url;
    $video['duration']       = sp_ev_sec2hms( $vid['duration'] );
    $video['ev_author']      = isset( $author['ev_author'] ) ? $author['ev_author'] : '';
    $video['ev_category']    = isset( $author['ev_category'] ) ? $author['ev_category'] : array();
    $video['ev_post_format'] = isset( $author['ev_post_format'] ) ? $author['ev_post_format'] : '';
    $video['ev_post_status'] = isset( $author['ev_post_status'] ) ? $author['ev_post_status'] : '';

    return $video;

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

  public static function fetch( $author ) {

    $author_id = $author['author_id'];
    $developer_key = $author['developer_key'];

    // set other options
    $page = 0;
    $per_page = 1;  // One by one because we get no next-page info from Wistia

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

      foreach ($body as $vid) {
        $video = SP_EV_Wistia::compose_video( $vid, $author );
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
