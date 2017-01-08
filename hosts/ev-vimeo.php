<?php

if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if( ! class_exists( 'SP_EV_Vimeo' ) ) :

class SP_EV_Vimeo {

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
    // No, Vimeo oEmbed support is built in to WordPress

    // host_name must be the last part of the Class Name
    $class = get_class();
    $host_name = preg_split( "/SP_EV_/", $class, 2, PREG_SPLIT_NO_EMPTY );
    $host_name = $host_name[0];

    $options = SP_External_Videos::get_options();
    if( !isset( $options['hosts']['vimeo']['authors'] ) ) {
      $authors = array();
    } else {
      $authors = $options['hosts']['vimeo']['authors'];
    }

    $options['hosts']['vimeo'] = array(
      'host_id' => 'vimeo',
      'host_name' => $host_name,
      'api_keys' => array(
        array(
          'id' => 'author_id',
          'label' => 'User ID Number',
          'required' => true,
          'explanation' => 'Note this is a number. Available at ...vimeo.com/settings/account/general'
        ),
        array(
          'id' => 'developer_key',
          'label' => 'Client Identifier',
          'required' => true,
          'explanation' => 'This needs to be generated in your Vimeo API Apps'
        ),
        array(
          'id' => 'secret_key',
          'label' => 'Client Secret',
          'required' => true,
          'explanation' => 'This needs to be generated in your Vimeo API Apps'
        ),
        array(
          'id' => 'auth_token',
          'label' => 'Personal Access Token',
          'required' => false,
          'explanation' => 'This gives you access to both your public and private videos'
        )
      ),
      'introduction' => "Vimeo's API v3.0 requires you to generate an oAuth2 Client Identifier and Client Secret from your account, in order to access your videos from another site (like this one). ",
      'api_url' => 'https://developer.vimeo.com/apps',
      'api_link_title' => 'Vimeo API',
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

    $url = "https://vimeo.com/user" . $author_id;
    $args = array();

    $response = wp_remote_request( $url, $args );
    $code = wp_remote_retrieve_response_code( $response );

    // return false on error
    if( !$response || is_wp_error( $response ) || preg_match('/^[45]/', $code ) ) {
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
  *  @param   $video_id is in format "/videos/123255" - this is useless because
  *           /videos/ is not any part of the embedabble uri of the video!
  *  @return  <iframe>
  */

  public static function embed_code( $video_id ) {

    $parts = explode( "/", $video_id );
    $actual_id = $parts[2];
    return esc_url( sprintf( "https://player.vimeo.com/video/%s", $video_id ) );

  }

  /*
  *  fetch
  *
  *  NEW VIMEO API 3.0 oAuth2
  *  Requires client identifier (developer_key) and client secret (secret_key)
  *  Optional personal access token gives you access to private videos
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
    $secret_key = $author['secret_key'];
    $access_token = $author['auth_token'];

    if( ! $access_token ) {
      // send request
      $url = 'https://api.vimeo.com/oauth/authorize/client?grant_type=client_credentials';
      $auth = base64_encode( $developer_key . ':' . $secret_key );
      $headers = array(
        'Authorization' => 'Basic ' . $auth,
        'Content-Type' => 'application/json'
      );
      $args = array(
        'headers'     => $headers
      );

      $response = wp_remote_post( $url, $args );
      $code = wp_remote_retrieve_response_code( $response );
      $message = wp_remote_retrieve_response_message( $response );
      $body = json_decode( wp_remote_retrieve_body( $response ), true ); // true to return array, not object

      $access_token = $body['access_token'];
    }

    // Now we should have an access token.
    // Limit field return.
    $fields_desired = array(
      'uri',
      'name',
      'link',
      'description',
      'duration',
      'pictures',
      'user.name',
      'user.link',
      'privacy',
      'tags',
      'release_time',
    );
    $fields_desired = implode( ',', $fields_desired );

    // Put the parameters in the URL directly.
    // wp_remote_get doesn't stringify params in URL like Vimeo likes it
    $baseurl = 'https://api.vimeo.com';
    // This whole end of the url is modified by the $next field at Vimeo. All parts needed
    $url = $baseurl . '/users/' . $author_id . '/videos?sort=date&page=1&per_page=50&fields=' . $fields_desired;
    $headers = array(
      'Authorization' => 'Bearer ' . $access_token, // was $token
      'Content-Type' => 'application/json',
      'Accept' => 'application/vnd.vimeo.*+json;version=3.2'
    );
    $args = array(
      'headers'     => $headers
    );

    // loop through all feed pages
    $count = 0;
    $new_videos = array();
    do {
      // Do an authenticated call
      try {
        $response = wp_remote_get( $url, $args );
        $code = wp_remote_retrieve_response_code( $response );
        $message = wp_remote_retrieve_response_message( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true ); // true to return array, not object

        // Interpret errors from the API - should really raise this as an exception
        if ( isset($body['error']) ) {
          // print_r( $body['error'] );
          return [];
        }

        // Adjust array to get to the data
        $data = $body['data'];
        $next = $body['paging']['next'];
      }
      catch ( Exception $e ) {
        echo "Encountered an API error -- code {$e->getCode()} - {$e->getMessage()}";
      }

      foreach ( $data as $vid )
      {
        // extract fields
        $video = array();
        $video['host_id']     = 'vimeo';
        $video['author_id']   = strtolower( $author_id );
        $video['video_id']    = $vid['uri'];
        $video['title']       = $vid['name'];
        $video['description'] = $vid['description'];
        $video['authorname']  = $vid['user']['name'];
        $video['videourl']    = $vid['link'];
        $video['published']   = $vid['release_time'];
        $video['author_url']  = $vid['user']['link'];
        $video['category']    = '';
        $video['keywords']    = array();
        if ( $vid['tags'] ) {
          foreach ( $vid['tags'] as $tag ) {
            array_push( $video['keywords'], $tag['tag'] );
          }
        }
        $video['thumbnail']   = $vid['pictures']['sizes'][2]['link'];
        $video['duration']    = sp_ev_sec2hms( $vid['duration'] );
        $video['ev_author']   = isset( $author['ev_author'] ) ? $author['ev_author'] : '';
        $video['ev_category'] = isset( $author['ev_category'] ) ? $author['ev_category'] : '';
        $video['ev_post_format'] = isset( $author['ev_post_format'] ) ? $author['ev_post_format'] : '';
        $video['ev_post_status'] = isset( $author['ev_post_status'] ) ? $author['ev_post_status'] : '';

        // add $video to the end of $new_videos
        array_push( $new_videos, $video );
        $count++;
      }

      // update request url to next page
      $url = $baseurl . $next;

    } while ( $next );

    // echo '<pre>sp_ev_fetch_vimeo_videos: ' . $count . '<br />'; print_r($new_videos); echo '</pre>';
    return $new_videos;

  }

} // end class

endif;

$SP_EV_Vimeo = new SP_EV_Vimeo;

?>
