<?php

if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if( ! class_exists( 'SP_EV_YouTube' ) ) :

class SP_EV_YouTube {

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
    // No, YouTube oEmbed support is built in to WordPress

    // host_name must be the last part of the Class Name
    $class = get_class();
    $host_name = preg_split( "/SP_EV_/", $class, 2, PREG_SPLIT_NO_EMPTY );
    $host_name = $host_name[0];

    $options = SP_External_Videos::get_options();
    if( !isset( $options['hosts']['youtube']['authors'] ) ) {
      $authors = array();
    } else {
      $authors = $options['hosts']['youtube']['authors'];
    }

    $options['hosts']['youtube'] = array(
      'host_id' => 'youtube',
      'host_name' => $host_name,
      'api_keys' => array(
        array(
          'id' => 'author_id',
          'label' => __( "Channel Name", "external-videos" ),
          'required' => true,
          'explanation' => ''
        ),
        array(
          'id' => 'developer_key',
          'label' => __( "API Key", "external-videos" ),
          'required' => true,
          'explanation' => __( "This needs to be generated in your API console at YouTube", "external-videos" )
        )
      ),
      'introduction' => __( "YouTube's API v3 requires you to generate an API key from your account, in order to access your videos from another site (like this one).", "external-videos" ),
      'api_url' => 'https://console.developers.google.com/apis/credentials',
      'api_link_title' => 'YouTube API',
      'authors' => $authors
    );

    update_option( 'sp_external_videos_options', $options );

  }

  /*
  *  remote_author_exists
  *
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

    // SEARCH FOR CHANNEL_ID
    $url = "https://www.googleapis.com/youtube/v3/search";
    $url .= "?type=channel&part=snippet&fields=items(id/channelId)&maxResults=1";
    $url .= "&key=" . $developer_key;
    $url .= "&q=" . $author_id;

    $response = wp_remote_get( $url );
    $code = wp_remote_retrieve_response_code( $response );
    $message = wp_remote_retrieve_response_message( $response );
    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    // The first result has the channel.
    $channelId = $body["items"][0]["id"]["channelId"];

    // return false on error
    if( !$channelId || preg_match('/^[45]/', $code ) ) {
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

    return esc_url( sprintf( "https://www.youtube.com/embed/%s", $video_id ) );

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

    // Google Get marathon. Let's fill this out.
    $endpoint = "https://www.googleapis.com/youtube/v3/videos";
    $url = $endpoint . "?part=contentDetails,snippet&key=" . $author['developer_key'] . "&id=" . $vid['contentDetails']['videoId'];
    $response = wp_remote_get( $url );
    $code = wp_remote_retrieve_response_code( $response );
    $message = wp_remote_retrieve_response_message( $response );
    $body = json_decode( wp_remote_retrieve_body( $response ), true ); // true to return array, not object

    $items = $body['items'][0];
    $tags = (array) $items['snippet']['tags'];
    $duration = $items['contentDetails']['duration'];
    // echo '<pre>$tags: <br />'; print_r( $tags ); echo '</pre>';
    // echo '<pre>$duration: <br />'; print_r( $duration ); echo '</pre>';

    $video = array();
    // extract fields
    $video['host_id']        = 'youtube';
    $video['author_id']      = sanitize_text_field( strtolower( $author['author_id'] ) );
    $video['video_id']       = sanitize_text_field( $vid['contentDetails']['videoId'] ); // not ID!!!
    $video['title']          = sanitize_text_field( $vid['snippet']['title'] );
    $video['description']    = sanitize_text_field( $vid['snippet']['description'] );
    $video['author_name']    = sanitize_text_field( $vid['snippet']['channelTitle'] );
    $video['video_url']      = esc_url( "https://www.youtube.com/watch?v=" . $vid['contentDetails']['videoId'] );
    $video['embed_url']      = SP_EV_YouTube::embed_url( $vid['contentDetails']['videoId'] );
    $video['published']      = gmdate( "Y-m-d H:i:s", strtotime( $vid['snippet']['publishedAt'] ) );
    $video['author_url']     = esc_url( "https://www.youtube.com/user/".$video['author_id'] );
    $video['category']       = array();
    $video['tags']           = array_map( 'esc_attr', $tags );
    $video['thumbnail_url']  = esc_url( $vid['snippet']['thumbnails']['default']['url'] );
    $video['duration']       = sp_ev_convert_youtube_time( $duration );
    $video['ev_author']      = isset( $author['ev_author'] ) ? $author['ev_author'] : '';
    $video['ev_category']    = isset( $author['ev_category'] ) ? $author['ev_category'] : array();
    $video['ev_post_format'] = isset( $author['ev_post_format'] ) ? $author['ev_post_format'] : '';
    $video['ev_post_status'] = isset( $author['ev_post_status'] ) ? $author['ev_post_status'] : '';

    return $video;

  }

  /*
  *  fetch
  *
  *  NEW YOUTUBE DATA API 3.0
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

    // Setup YouTube API access. Ultimately we need a YouTube playlist_id,
    // which is hard for a user to find, and to get that we need the user's
    // channelId, which they're also unlikely to know. So we start by getting
    // the channelId from the username, through a channels?forUsername query.
    // http://stackoverflow.com/questions/14925851/how-do-i-use-youtube-data-api-v3-to-fetch-channel-uploads-using-chanels-usernam?rq=1
    // https://developers.google.com/youtube/v3/code_samples/php#search_by_keyword
    // YouTube doesn't accept the way wp_remote_get forms args,
    // so we have to stringify the args ourselves.

    // SEARCH FOR CHANNEL_ID
    $url = "https://www.googleapis.com/youtube/v3/search";
    $url .= "?type=channel&part=snippet&fields=items(id/channelId)&maxResults=1";
    $url .= "&key=" . $developer_key;
    $url .= "&q=" . $author_id;

    $response = wp_remote_get( $url );
    $code = wp_remote_retrieve_response_code( $response );
    $message = wp_remote_retrieve_response_message( $response );
    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    // The first result has the channel.
    $channelId = $body["items"][0]["id"]["channelId"];

    // SEARCH FOR PLAYLIST ID
    $url = "https://www.googleapis.com/youtube/v3/channels";
    $url .= "?part=snippet,contentDetails";
    $url .= "&id=" . $channelId;
    $url .= "&key=" . $developer_key;

    $response = wp_remote_get( $url );
    $code = wp_remote_retrieve_response_code( $response );
    $message = wp_remote_retrieve_response_message( $response );
    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    $playlistId = $body['items'][0]['contentDetails']['relatedPlaylists']['uploads'];

    // And now we need those videos
    $per_page = 10;
    $pageToken = '1';

    $baseurl = "https://www.googleapis.com/youtube/v3/playlistItems";
    $baseurl .= "?part=contentDetails,snippet";
    $baseurl .= "&key=" . $developer_key;
    $baseurl .= "&channelId=" . $channelId;
    $baseurl .= "&playlistId=" . $playlistId;
    $baseurl .= "&maxResults=" . $per_page;

    $url = $baseurl;

    $new_videos = array();

    // /*
    do {
      // fetch videos
      try {
        $response = wp_remote_get( $url );
        $code = wp_remote_retrieve_response_code( $response );
        $message = wp_remote_retrieve_response_message( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true ); // true to return array, not object
        $items = isset( $body['items'] ) ? $body['items'] : array();
        $pageToken = isset( $body['nextPageToken'] ) ? true : null;
      }
      catch ( Exception $e ) {
        echo "Encountered an API error -- code {$e->getCode()} - {$e->getMessage()}";
      }

      foreach ( $items as $vid )
      {
        $video = SP_EV_YouTube::compose_video( $vid, $author );
        // add $video to the end of $new_videos
        array_push( $new_videos, $video );
      }
      // next page
      $url = $baseurl . "&pageToken=" . $pageToken;

    } while ( $pageToken );

    return $new_videos;

  }

} // end class

endif;

$SP_EV_YouTube = new SP_EV_YouTube;

?>
