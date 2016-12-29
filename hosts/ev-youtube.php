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
    $hostname = preg_split( "/SP_EV_/", $class, 2, PREG_SPLIT_NO_EMPTY );
    $hostname = $hostname[0];

    $options = SP_External_Videos::get_options();

    $options['hosts']['youtube'] = array(
      'host_id' => 'youtube',
      'host_name' => $hostname,
      'api_keys' => array(
        array(
          'id' => 'author_id',
          'label' => 'Channel Name',
          'required' => true,
          'explanation' => 'This is the part after //www.youtube.com/user/ in your channel\'s full (not custom) URL'
        ),
        array(
          'id' => 'developer_key',
          'label' => 'API Key',
          'required' => true,
          'explanation' => 'This needs to be generated in your API console at YouTube'
        )
      ),
      'introduction' => "YouTube's API v3 requires you to generate an API key from your account, in order to access your videos from another site (like this one).",
      'api_url' => 'https://console.developers.google.com/apis/credentials',
      'api_link_title' => 'YouTube API'
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

    return "//www.youtube.com/embed/$video_id";

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

  static function fetch( $author ) {

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
        // extract fields
        $video = array();
        $video['host_id']     = 'youtube';
        $video['author_id']   = strtolower( $author_id );
        $video['video_id']    = $vid['id'];
        $video['title']       = $vid['snippet']['title'];
        $video['description'] = $vid['snippet']['description'];
        $video['authorname']  = $vid['snippet']['channelTitle'];
        $video['videourl']    = 'https://www.youtube.com/watch?v=' . $vid['snippet']['resourceId']['videoId'];
        $video['published']   = date( "Y-m-d H:i:s", strtotime( $vid['snippet']['publishedAt'] ) );
        $video['author_url']  = "https://www.youtube.com/user/".$video['author_id'];
        $video['category']    = '';
        $video['keywords']    = array();
        $video['thumbnail']   = $vid['snippet']['thumbnails']['default']['url'];
        $video['duration']    = '';
        $video['ev_author']   = isset( $author['ev_author'] ) ? $author['ev_author'] : '';
        $video['ev_category'] = isset( $author['ev_category'] ) ? $author['ev_category'] : '';
        $video['ev_post_format'] = isset( $author['ev_post_format'] ) ? $author['ev_post_format'] : '';
        $video['ev_post_status'] = isset( $author['ev_post_status'] ) ? $author['ev_post_status'] : '';

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
