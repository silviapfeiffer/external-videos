<?php

if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if( ! class_exists( 'SP_EV_Dailymotion' ) ) :

class SP_EV_Dailymotion {

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
    // No, Dailymotion oEmbed support is built in to WordPress

    // host_name must be the last part of the Class Name
    $class = get_class();
    $hostname = preg_split( "/SP_EV_/", $class, 2, PREG_SPLIT_NO_EMPTY );
    $hostname = $hostname[0];

    $options = SP_External_Videos::get_options();

    $options['hosts']['dailymotion'] = array(
      'host_id' => 'dailymotion',
      'host_name' => $hostname,
      'api_keys' => array(
        array(
          'id' => 'author_id',
          'label' => 'User ID',
          'required' => true,
          'explanation' => 'Required'
        )
      ),
      'introduction' => "Dailymotion only requires a User ID in order to access your videos from another site.",
      'api_url' => 'http://www.dailymotion.com/settings/developer',
      'api_link_title' => 'Dailymotion API'
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

    $url = "https://api.dailymotion.com/user/" . $author_id . "/videos/";
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
  *  @param   $video_id
  *  @return  <iframe>
  */

  public static function embed_code( $video_id ) {

    return "//www.dailymotion.com/embed/video/$video_id";

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

    // Limit field return - these fields are specific to dailymotion
    $fields_desired = array(
      'id',
      'url',
      'title',
      'description',
      'duration',
      'thumbnail_url',
      'owner.screenname',
      'owner.url',
      'tags',
      'created_time',
    );
    $fields_desired = implode( ',', $fields_desired );
    $page = 1;

    $baseurl = 'https://api.dailymotion.com/user/' . $author_id. '/videos?sort=recent&fields=' . $fields_desired;
    $url = $baseurl . '&page=' . $page;
    // send request
    $headers = array(
      // 'Authorization' => 'Basic', // No authorization needed for video list by user at dailymotion
      'Content-Type' => 'application/json',
    );
    $args = array(
      'headers'     => $headers
    );

    $new_videos = array();

    // /*
    do {
      // fetch videos
      try {
        $response = wp_remote_request( $url, $args );
        $code = wp_remote_retrieve_response_code( $response );
        $message = wp_remote_retrieve_response_message( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ) );
        $list = $body->list;
        $more = $body->has_more;
      }
      catch ( Exception $e ) {
        echo "Encountered an API error -- code {$e->getCode()} - {$e->getMessage()}";
      }

      foreach ( $list as $vid )
      {
        // we have to convert obj to array to access fields named with dots!
        $vid = (array) $vid;
        // extract fields
        $video = array();
        $video['host_id']     = 'dailymotion';
        $video['author_id']   = strtolower($author_id);
        $video['video_id']    = $vid['id'];
        $video['title']       = $vid['title'];
        $video['description'] = $vid['description'];
        $video['authorname']  = $vid['owner.screenname'];
        $video['videourl']    = $vid['url'];
        $video['published']   = date("Y-m-d H:i:s", strtotime($vid['created_time']));
        $video['author_url']  = $vid['owner.url'];
        $video['category']    = '';
        $video['keywords']    = $vid['tags'];
        $video['thumbnail']   = $vid['thumbnail_url'];
        $video['duration']    = $vid['duration'];
        $video['ev_author']   = isset( $author['ev_author'] ) ? $author['ev_author'] : '';
        $video['ev_category'] = isset( $author['ev_category'] ) ? $author['ev_category'] : '';
        $video['ev_post_format'] = isset( $author['ev_post_format'] ) ? $author['ev_post_format'] : '';
        $video['ev_post_status'] = isset( $author['ev_post_status'] ) ? $author['ev_post_status'] : '';

        // add $video to the end of $new_videos
        array_push( $new_videos, $video );

      }

      // next page
      $page++;
      // update request url to next page - it's offset by page on dailymotion
      $url = $baseurl . '&page=' . $page;

    } while ( $more );

    // echo '<pre>'; print_r( $new_videos); echo '</pre>';
    return $new_videos;

  }

} // end class

endif;

$SP_EV_Dailymotion = new SP_EV_Dailymotion;

?>
