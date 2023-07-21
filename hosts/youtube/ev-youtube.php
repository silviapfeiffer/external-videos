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
    // error_log( 'options: ' . print_r( $options, true ) );

    if( !isset( $options['hosts']['youtube']['authors'] ) ) {
      $authors = array();
    } else {
      $authors = $options['hosts']['youtube']['authors'];

      foreach( $authors as $author ){
        // Check for necessary API keys
        if( !isset( $author['developer_key'] ) ||
            empty( $author['developer_key'] ) ){
          // return a WP Error message so they know to update the author.
          error_log( 'no developer key for ' . $author['author_id'] );

        } else { // Get hidden user fields from YouTube API xxx from author ID
          $channel_info = $this->get_channel_and_playlist_id( $author );
          if( !isset( $author['channel_id'] ) ){
            $author['channel_id'] = $channel_info["channel_id"];
          }
          if( !isset( $author['playlist_id'] ) ){
            $author['playlist_id'] = $channel_info["uploads"];
          }
        }
        // To index by author_id not integer
        $updated_authors[$author['author_id']] = $author;
      }
    }

    // rebuild options
    $options['hosts']['youtube'] = array(
      'host_id' => 'youtube',
      'host_name' => $host_name,
      'api_keys' => array(
        // array(
        //   'id' => 'author_id',
        //   'label' => __( "Channel Name", "external-videos" ),
        //   'required' => false,
        //   'explanation' => ''
        // ),
        array(
          'id' => 'author_id',
          'label' => __( "YouTube username", "external-videos" ),
          'required' => true,
          'explanation' => "Copy this from your account's url at YouTube (youtube.com/yourusername)", "external-videos"
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
      'authors' => $updated_authors
    );

    update_option( 'sp_external_videos_options', $options );

    // error_log( print_r( $options['hosts']['youtube']['authors'], true ) );

  }

  /*
  *  remote_author_exists
  *
  *  Used by SP_External_Videos::remote_author_exists()
  *  Checks if remote author exists on this host
  *  YouTube API change: id is now like UCwb4eAJ2HbpOO3rYUQF7b5g
  *  so we're back to searching by forUsername
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param   $host_id, $author_id, $developer_key
  *  @return  boolean
  */

  public static function remote_author_exists( $host_id, $author_id, $developer_key ){

    /*
    // CHECK FOR AUTHOR
    $url = "https://www.googleapis.com/youtube/v3/search";
    $url .= "?type=channel&part=snippet&fields=items(id/channelId)&maxResults=1";
    $url .= "&key=" . $developer_key;
    $url .= "&q=" . $author_id;
    */
    // SEARCH FOR CHANNEL BY USERNAME since 1.3
    // You could use the snippet for to get channel description, but we're not.
    $url = "https://www.googleapis.com/youtube/v3/channels";
    $url .= "?&part=snippet&maxResults=1";
    $url .= "&key=" . $developer_key;
    $url .= "&forUsername=" . $author_id;

    // error_log( '$url' . print_r( $url, true ) );

    $response = wp_remote_get( $url );
    $code = wp_remote_retrieve_response_code( $response );
    // $message = wp_remote_retrieve_response_message( $response );
    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    // error_log( '$body' . print_r( $body, true ) );
    // The first result has the channel id we're taking
    $channelId = $body["items"][0]["id"];

    // return false on error
    if( !$channelId || preg_match('/^[45]/', $code ) ) {
      return false;
    }

    return true;

  }

  /*
  *  These functions help setup YouTube API access, different from other hosts.
  *  Ultimately we need their YouTube playlistId, which users don't know,
  *  and to get that we need the user's channelId, which users also don't know.
  */

  /*
  *  get_channel_and_playlist_id
  *
  *  Finds the author's channelId (different from username)
  *  as well as the channel's default playlist_id, which is for "uploads"
  *  Note that "uploads" is not returned by a Youtube API playlist query!
  *  That's only for custom playlists.
  *  The channel_id is needed in YouTube API video queries
  *  Sets the youtube-only field $author['channel_id'] for this author
  *
  *  get it from the username, through a channels?forUsername query.
  *  http://stackoverflow.com/questions/14925851/how-do-i-use-youtube-data-api-v3-to-fetch-channel-uploads-using-chanels-usernam?rq=1
  *  https://developers.google.com/youtube/v3/code_samples/php#search_by_keyword
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param   $author
  *  @return  $channelId
  */

  function get_channel_and_playlist_id( $author ){

    if( !isset( $author['developer_key'] ) ) return;
    $developer_key = $author['developer_key'];
    $author_id = $author['author_id'];

    // SEARCH FOR CHANNEL_ID
    $url = "https://www.googleapis.com/youtube/v3/channels";
    $url .= "?part=contentDetails&maxResults=1";
    $url .= "&key=" . $developer_key;
    $url .= "&forUsername=" . $author_id;

    $response = wp_remote_get( $url );
    // $code = wp_remote_retrieve_response_code( $response );
    // $message = wp_remote_retrieve_response_message( $response );
    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    // error_log( '$body' . print_r( $body, true ) );
    // The first result has the channel.
    // if( !is_array( $body ) ) {
    //   error_log( print_r($body, true) );
    //   return;
    // }

    $channelId = $body["items"][0]["id"];
    $uploadsPlaylistId = $body["items"][0]["contentDetails"]["relatedPlaylists"]["uploads"];

    // $options = get_option( 'sp_external_videos_options' );

    return ["channel_id" => $channelId, "uploads" => $uploadsPlaylistId];

  }

  /*
  *  get_playlist_id — 2023 RETIRED, gotten above with channel id
  *
  *  Finds the playlistId for a channelId's "uploads"
  *  This additional field is needed in YouTube API video queries
  *  Sets the youtube-only field $author['playlist_id'] for this author
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param   $author
  *  @return  $playlistId
  */

  function get_playlist_id( $author ){

    if( !isset( $author['developer_key'] ) || !isset( $author['author_id'] ) ) return;
    $developer_key = $author['developer_key'];
    $author_id = $author['author_id'];

    // NEW 2023: GET UPLOADS PLAYLIST ID FROM CHANNELS
    $url = "https://www.googleapis.com/youtube/v3/channels";
    $url .= "?part=contentDetails&maxResults=1";
    $url .= "&key=" . $developer_key;
    $url .= "&forUsername=" . $author_id;
    /*
    $url = "https://www.googleapis.com/youtube/v3/playlists";
    $url .= "?part=snippet,contentDetails";
    $url .= "&channelId=" . $channel_id;
    $url .= "&key=" . $developer_key;
    */
    $response = wp_remote_get( $url );
    $code = wp_remote_retrieve_response_code( $response );
    $message = wp_remote_retrieve_response_message( $response );
    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    // NOTE you could let them pick the playlist here, if you get more than one
    error_log( 'playlist search result $body ' . print_r( $body, true ) );

    // $playlistId = $body['items'][0]['id'];
    $uploadsPlaylistId = $body["items"][0]["contentDetails"]["relatedPlaylists"]["uploads"];

    return $uploadsPlaylistId;

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
  *  @return  string that WP converts to an <iframe> via the_content
  */

  public static function embed_url( $video_id ) {

    return esc_url( sprintf( "https://www.youtube.com/embed/%s", $video_id ) );

  }

  /*
  *  video_url
  *
  *  Used by fetch() and SP_EV_Admin::save_video()
  *  Video url is stored as postmeta in external-video posts.
  *  Url is specific to each host site's embed API.
  *
  *  @type  function
  *  @date  18/07/23
  *  @since  2.0.0
  *
  *  @param   $video_id
  *  @return  string that WP converts to an <iframe> via the_content
  */

  public static function video_url( $video_id ) {

    return esc_url( sprintf( "https://www.youtube.com/watch?v=%s",
                             $video_id ) );

  }

  /*
  *  video_detail
  *
  *  Used by compose_video()
  *  We have to re-query the YouTube API for **each video** to get more details
  *  using id=$vid['contentDetails']['videoId'] from the playlist results
  *  because playlist items does not have everything we need.
  *
  *  @type  function
  *  @date  18/07/23
  *  @since  2.0.0
  *
  *  @param   $vid array
  *  @param   $author array
  *  @return  $video array
  */

  public static function video_detail( $vid, $author ) {

    $url = "https://www.googleapis.com/youtube/v3/videos";
    $url .= "?part=contentDetails,snippet,status";

    $url .= "&key=" . $author['developer_key'];
    $url .= "&id=" . $vid['contentDetails']['videoId'];

    $response = wp_remote_get( $url );
    $code = wp_remote_retrieve_response_code( $response );
    $message = wp_remote_retrieve_response_message( $response );
    // true to return array, not object
    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    // Redefine $vid as this new, more detailed object
    $vid = $body['items'][0];

    return $vid;
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
  *  @param   $author array
  *  @return  $video array
  */

  public static function compose_video( $vid, $author ) {

    $vid = SP_EV_YouTube::video_detail( $vid, $author );
    // error_log('updated $vid:' . "\n" . print_r( $vid, true ) );
    // $tags = (array) $vid['snippet']['tags'];
    $tags = array();
    // echo '<pre>$tags: <br />'; print_r( $tags ); echo '</pre>';

    $video = array();
    // extract fields
    $video['host_id']        = 'youtube';
    $video['author_id']      = sanitize_text_field( strtolower( $author['author_id'] ) );
    $video['video_id']       = sanitize_text_field( $vid['id'] ); // now ID!!!
    // embeddable is boolean on YT
    $video['embeddable']     = (boolean) $vid['status']['embeddable'];
    // $video['privacy']        = sanitize_text_field( $vid['status']['privacyStatus'] );
    $video['title']          = sanitize_text_field( $vid['snippet']['title'] );
    $video['description']    = sanitize_text_field( $vid['snippet']['description'] );
    $video['author_name']    = sanitize_text_field( $vid['snippet']['channelTitle'] );
    $video['video_url']      = SP_EV_YouTube::video_url( $vid['id'] );
    $video['embed_url']      = SP_EV_YouTube::embed_url( $vid['id'] );
    $video['published']      = gmdate( "Y-m-d H:i:s", strtotime( $vid['snippet']['publishedAt'] ) );
    $video['author_url']     = esc_url( "https://www.youtube.com/user/".$video['author_id'] );
    $video['category']       = array();
    $video['tags']           = array_map( 'esc_attr', $tags );
    $video['thumbnail_url']  = esc_url( $vid['snippet']['thumbnails']['medium']['url'] );
    $video['poster_url']     = esc_url( $vid['snippet']['thumbnails']['maxres']['url'] ) ?: esc_url( $vid['snippet']['thumbnails']['high']['url'] );
    $duration                = $vid['contentDetails']['duration'];
    $video['duration']       = SP_EV_Helpers::ytduration2hms( $duration );
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
  *  @return  $current_videos
  */

  public static function fetch( $author ) {

    // $author_id = $author['author_id'];
    $developer_key = $author['developer_key'];
    // $channelId = $author['channel_id'];
    $playlistId = $author['playlist_id'];

    // And now we need those videos
    $per_page = 30;
    $pageToken = '1';

    // Using $baseurl because we may append pageToken later
    $baseurl = "https://www.googleapis.com/youtube/v3/playlistItems";
    $baseurl .= "?part=contentDetails,snippet,status";

    $baseurl .= "&key=" . $developer_key;
    // $baseurl .= "&channelId=" . $channelId; // API doesn't need this anymore
    $baseurl .= "&playlistId=" . $playlistId;
    $baseurl .= "&maxResults=" . $per_page;

    $url = $baseurl;

    $current_videos = array();

    // /*
    do {
      // fetch videos
      try {
        $response = wp_remote_get( $url );
        $code = wp_remote_retrieve_response_code( $response );
        $message = wp_remote_retrieve_response_message( $response );
        // set json_decode `true` to return array, not object
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $items = isset( $body['items'] ) ? $body['items'] : array();
        $pageToken = isset( $body['nextPageToken'] ) ? true : null;
      }
      catch ( Exception $e ) {
        echo "Encountered an API error -- code " .
             "{$e->getCode()} - {$e->getMessage()}";
      }

      foreach ( $items as $vid ) {
        // error_log('$vid:' . "\n" . print_r( $vid, true ) );
        $video = SP_EV_YouTube::compose_video( $vid, $author );
        // add $video to the end of $current_videos
        array_push( $current_videos, $video );
      }
      // next page
      $url = $baseurl . "&pageToken=" . $pageToken;

    } while ( $pageToken );

    // error_log(print_r($current_videos, true));

    return $current_videos;

  }

} // end class

endif;

$SP_EV_YouTube = new SP_EV_YouTube;

?>
