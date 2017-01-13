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
    $host_name = preg_split( "/SP_EV_/", $class, 2, PREG_SPLIT_NO_EMPTY );
    $host_name = $host_name[0];

    $options = SP_External_Videos::get_options();
    if( !isset( $options['hosts']['dailymotion']['authors'] ) ) {
      $authors = array();
    } else {
      $authors = $options['hosts']['dailymotion']['authors'];
    }

    $options['hosts']['dailymotion'] = array(
      'host_id' => 'dailymotion',
      'host_name' => $host_name,
      'api_keys' => array(
        array(
          'id' => 'author_id',
          'label' => __( "User ID", "external-videos" ),
          'required' => true,
          'explanation' => ''
        )
      ),
      'introduction' => __( "Dailymotion only requires a User ID in order to access your videos from another site. To display your videos properly in your theme, you may also need to install the plugin FitVids for Wordpress.", "external-videos" ),
      'api_url' => 'http://www.dailymotion.com/settings/developer',
      'api_link_title' => 'Dailymotion API',
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
  *  embed_url
  *
  *  Used by fetch() and SP_EV_Admin::save_video()
  *  Embed url is stored as postmeta in external-video posts
  *  Url is specific to each host site's embed API
  *
  *  @type  function
  *  @date  31/12/16
  *  @since  1.0
  *
  *  @param   $video_id
  *  @return  <iframe>
  */

  public static function embed_url( $video_id ) {

    return esc_url( sprintf( "https://www.dailymotion.com/embed/video/%s", $video_id ) );

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
    $video['host_id']        = 'dailymotion';
    $video['author_id']      = sanitize_text_field( strtolower( $author['author_id'] ) );
    $video['video_id']       = sanitize_text_field( $vid['id'] );
    $video['title']          = sanitize_text_field( $vid['title'] );
    $video['description']    = sanitize_text_field( $vid['description'] );
    $video['author_name']    = sanitize_text_field( $vid['owner.screenname'] );
    $video['video_url']      = esc_url( $vid['url'] );
    $video['embed_url']      = SP_EV_Dailymotion::embed_url( $vid['id'] );
    $video['published']      = gmdate( "Y-m-d H:i:s", $vid['created_time'] );
    $video['author_url']     = esc_url( $vid['owner.url'] );
    $video['category']       = array();
    if ( $vid['tags'] ) {
      $video['tags'] = array();
      foreach ( $vid['tags'] as $tag ) {
        array_push( $video['tags'], sanitize_text_field( $tag ) );
      }
    }
    $video['thumbnail_url']  = esc_url( $vid['thumbnail_url'] );
    $video['duration']       = gmdate( "H:i:s", $vid['duration'] );
    $video['ev_author']      = isset( $author['ev_author'] ) ? $author['ev_author'] : '';
    $video['ev_category']    = isset( $author['ev_category'] ) ? $author['ev_category'] : array();
    $video['ev_post_format'] = isset( $author['ev_post_format'] ) ? $author['ev_post_format'] : '';
    $video['ev_post_status'] = isset( $author['ev_post_status'] ) ? $author['ev_post_status'] : '';

    return $video;

  }

  /*
  *  fetch
  *
  *  Dailymotion requires author_id and developer_key
  *  https://developer.dailymotion.com/api
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
        $body = json_decode( wp_remote_retrieve_body( $response ), true ); // true to return array, not object
        $list = $body['list'];
        $has_more = $body['has_more'];
      }
      catch ( Exception $e ) {
        echo "Encountered an API error -- code {$e->getCode()} - {$e->getMessage()}";
      }

      foreach ( $list as $vid ) {
        $video = SP_EV_Dailymotion::compose_video( $vid, $author );
        // add $video to the end of $new_videos
        array_push( $new_videos, $video );
      }

      // next page
      $page++;
      // update request url to next page - it's offset by page on dailymotion
      $url = $baseurl . '&page=' . $page;

    } while ( $has_more );

    // echo '<pre>'; print_r( $new_videos); echo '</pre>';
    return $new_videos;

  }

} // end class

endif;

$SP_EV_Dailymotion = new SP_EV_Dailymotion;

?>
