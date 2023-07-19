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

    self::setup_options();

  }

  /*
  *  setup_options
  *
  *  Set up host and author options, check for missing API keys
  *
  *  @type  function
  *  @date  14/1/17
  *  @since  1.0
  *
  *  @param
  *  @return  boolean
  */

  private function setup_options(){
    // options table host_name is automatically set the last part of this Class Name
    $class = get_class();
    $host_name = preg_split( "/SP_EV_/", $class, 2, PREG_SPLIT_NO_EMPTY );
    $host_name = $host_name[0];
    $updated_authors = array();

    $options = SP_External_Videos::get_options();
    if( !isset( $options['hosts']['vimeo']['authors'] ) ) {
      $authors = array();
    } else {
      $authors = $options['hosts']['vimeo']['authors'];

      foreach( $authors as $author ){
        // Check for necessary API keys
        if( !isset( $author['developer_key'] ) ||
            empty( $author['developer_key'] ) ){
          // to do: return a WP Error message so they know to update the author.
          error_log( 'no client identifier for ' . $author['author_id'] );

        } else { // Only continue if we have a developer_key

          if( !isset( $author['secret_key'] ) ||
              empty( $author['secret_key'] ) ){
            // to do: return a WP Error message so they know to update the author.
            error_log( 'no client secret for ' . $author['author_id'] );

          } else { // Only update if we have developer_key & secret_key
            $updated_authors[$author['author_id']] = $author;
          }
        }
      }
    }

    // rebuild options
    $options['hosts']['vimeo'] = array(
      'host_id' => 'vimeo',
      'host_name' => $host_name,
      'api_keys' => array(
        array(
          'id' => 'author_id',
          'label' => __( "User ID Number", "external-videos" ),
          'required' => true,
          'explanation' => __( "Note this is a number. Available at ...vimeo.com/settings/account/general", "external-videos" )
        ),
        array(
          'id' => 'developer_key',
          'label' => __( "Client Identifier", "external-videos" ),
          'required' => true,
          'explanation' => __( "This needs to be generated in your Vimeo API Apps", "external-videos" )
        ),
        array(
          'id' => 'secret_key',
          'label' => __( "Client Secret", "external-videos" ),
          'required' => true,
          'explanation' => __( "This needs to be generated in your Vimeo API Apps", "external-videos" )
        ),
        array(
          'id' => 'auth_token',
          'label' => __( "Personal Access Token", "external-videos" ),
          'required' => false,
          'explanation' => __( "This gives you access to both your public and private videos", "external-videos" )
        )
      ),
      'introduction' => __( "Vimeo's API v3.0 requires you to generate an oAuth2 Client Identifier and Client Secret from your account, in order to access your videos from another site (like this one).", "external-videos" ),
      'api_url' => 'https://developer.vimeo.com/apps',
      'api_link_title' => 'Vimeo API',
      'authors' => $updated_authors
    );

    update_option( 'sp_external_videos_options', $options );

    // error_log( print_r( $options['hosts']['vimeo']['authors'], true ) );

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
  *  embed_url
  *
  *  Used by fetch() and SP_EV_Admin::save_video()
  *  Embed url is stored as postmeta in external-video posts.
  *  Url is specific to each host site's embed API.
  *
  *  This function was a bit more involved on Vimeo because they give you
  *  a useless "uri" containing the regex /videos/ before the proper videoID.
  *  This regex is not any part of the embedabble uri of the video! Remove!

  *  @type  function
  *  @date  31/10/16
  *  @since  0.23
  *
  *  @param   $video_id
  *  @return  <iframe>
  */

  public static function embed_url( $video_id ) {

    // $parts = explode( "/", $video_id );
    // $video_id = $parts[2];
    return esc_url( sprintf( "https://player.vimeo.com/video/%s", $video_id ) );

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
    $video['host_id']        = 'vimeo';
    $video['author_id']      = sanitize_text_field(
                                 strtolower( $author['author_id'] )
                               );
    // Vimeo doesn't store the video_id separately, we have to extract from uri
    // We assume it still follows the pattern "/videos/24234325"
    $vimeo_uri               = sanitize_text_field( $vid['uri'] );
    $id_pos                  = strpos( $vimeo_uri, "/videos/" );
    $video['video_id']       = ( $id_pos === 0 ) ?
                               substr( $vimeo_uri, 8 ) : $vimeo_uri;
    // error_log(print_r( $id_pos, true ));

    $vimeo_embeddable        = sanitize_text_field( $vid['privacy']['embed'] );
    // The current options are "public", "whitelist", and "private".
    // We'll assume it's embeddable as long as it's not "private".
    // End user will have to allow their own domains to get the embed to
    // actually work, if it's set to "specific domains" in the Vimeo UI.
    $video['embeddable']     = $vimeo_embeddable !== "private";
    // $video['privacy']        = sanitize_text_field( $vid['privacy']['view'] );
    $video['title']          = sanitize_text_field( $vid['name'] );
    $video['description']    = sanitize_text_field( $vid['description'] );
    $video['author_name']    = sanitize_text_field( $vid['user']['name'] );
    $video['video_url']      = esc_url( $vid['link'] );
    $video['embed_url']      = SP_EV_Vimeo::embed_url( $vid['uri'] );
    $video['published']      = gmdate( "Y-m-d H:i:s", strtotime( $vid['release_time'] ) );
    $video['author_url']     = esc_url( $vid['user']['link'] );
    $video['category']       = array();
    if ( isset( $vid['tags'] ) && is_array( $vid['tags'] ) ) {
      $video['tags'] = array();
      foreach ( $vid['tags'] as $tag ) {
        array_push( $video['tags'], esc_attr( $tag['tag'] ) ); // yep, it's $tag['tag'] at vimeo
      }
    }
    // Size 2 is our thumbnail, 295x166.
    $video['thumbnail_url']  = esc_url( $vid['pictures']['sizes'][2]['link'] );
    // Size 4 is 960x540, better for poster preview
    $video['poster_url']     = esc_url( $vid['pictures']['sizes'][4]['link'] );
    $video['duration']       = SP_EV_Helpers::sec2hms( $vid['duration'] );
    $video['ev_author']      = isset( $author['ev_author'] ) ?
                               $author['ev_author'] : '';
    $video['ev_category']    = isset( $author['ev_category'] ) ?
                               $author['ev_category'] : array();
    $video['ev_post_format'] = isset( $author['ev_post_format'] ) ?
                               $author['ev_post_format'] : '';
    $video['ev_post_status'] = isset( $author['ev_post_status'] ) ?
                               $author['ev_post_status'] : '';
    // Could store privacy.embed or privacy.view
    // https://developer.vimeo.com/api/reference/response/video

    return $video;

  }

  /*
  *  get_temporary_token
  *
  *  Helps setup Vimeo API access, different from other hosts
  *  Users should have a personal access token for full access to their channel
  *  but if they don't have it, get a temporary access token for public videos
  *
  *  @type  function
  *  @date  14/1/17
  *  @since  1.1
  *
  *  @param   $author
  *  @return  $access_token
  */

  private static function get_temporary_token( $author ){

    $developer_key = $author['developer_key'];
    $secret_key = $author['secret_key'];

    // send request
    $url = "https://api.vimeo.com/oauth/authorize/client?grant_type=client_credentials";
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

    return $access_token;

  }

  /*
  *  fetch
  *
  *  NEW VIMEO API 3.0 oAuth2
  *  Requires client identifier (developer_key) and client secret (secret_key)
  *  Optional personal access token (auth_token) gives you access to private videos
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param   $author
  *  @return  $current_videos
  */

  public static function fetch( $author ) {

    $author_id = $author['author_id'];
    $developer_key = $author['developer_key'];
    $secret_key = $author['secret_key'];
    $access_token = $author['auth_token'];

    if( ! $access_token ) {
      $access_token = self::get_temporary_token( $author );
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
    $current_videos = array();
    do {
      // Do an authenticated call
      try {
        $response = wp_remote_get( $url, $args );
        $code = wp_remote_retrieve_response_code( $response );
        $message = wp_remote_retrieve_response_message( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true ); // true to return array, not object
        // Adjust array to get to the data
        $data = $body['data'];
        $next = $body['paging']['next'];

        // Interpret errors from the API - should really raise this as an exception
        if ( isset( $body['error'] ) ) {
          // print_r( $body['error'] );
          return [];
        }

      }
      catch ( Exception $e ) {
        echo "Encountered an API error -- code {$e->getCode()} - {$e->getMessage()}";
      }

      foreach ( $data as $vid ) {
        $video = SP_EV_Vimeo::compose_video( $vid, $author );
        // add $video to the end of $current_videos
        array_push( $current_videos, $video );
        $count++;
      }

      // update request url to next page
      $url = $baseurl . $next;

    } while ( $next );

    // echo '<pre>sp_ev_fetch_vimeo_videos: ' . $count . '<br />'; print_r($current_videos); echo '</pre>';

    // error_log(print_r($current_videos, true));

    return $current_videos;

  }

} // end class

endif;

$SP_EV_Vimeo = new SP_EV_Vimeo;

?>
