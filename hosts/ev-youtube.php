<?php
/* This function is being tested in a Kiteboat page template first */
// [21-Dec-2016 19:26:05 UTC] PHP Notice:  Undefined variable: page_token in /Applications/MAMP/htdocs/projectkiteboat/wp-content/plugins/external-videos/hosts/ev-youtube.php on line 73
// [21-Dec-2016 19:26:06 UTC] PHP Notice:  Trying to get property of non-object in /Applications/MAMP/htdocs/projectkiteboat/wp-content/plugins/external-videos/hosts/ev-youtube.php on line 86
// [21-Dec-2016 19:26:06 UTC] PHP Warning:  Invalid argument supplied for foreach() in /Applications/MAMP/htdocs/projectkiteboat/wp-content/plugins/external-videos/hosts/ev-youtube.php on line 86
// [21-Dec-2016 19:26:06 UTC] PHP Notice:  Trying to get property of non-object in /Applications/MAMP/htdocs/projectkiteboat/wp-content/plugins/external-videos/hosts/ev-youtube.php on line 112

if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if( ! class_exists( 'SP_EV_YouTube' ) ) :

class SP_EV_YouTube {

  /*
  *  fetch
  *
  *  NEW YOUTUBE DATA API 3.0
  *
  *  @type	function
  *  @date	31/10/16
  *  @since	1.0
  *
  *  @param	 $author
  *  @return	$new_videos
  */

  static function fetch( $author ) {

		$author_id = $author['author_id'];
		$developer_key = $author['developer_key'];
		$app_name = $author['secret_key'];

		// Setup YouTube API access. Ultimately we need a playlist ID,
    // which is hard for a user to find, and to even get that we need the user's
    // channelId, which they're also unlikely to know. So we start by getting
		// the channelId from the username, through a search query. Incredible!
		// http://stackoverflow.com/questions/14925851/how-do-i-use-youtube-data-api-v3-to-fetch-channel-uploads-using-chanels-usernam?rq=1
		// https://developers.google.com/youtube/v3/code_samples/php#search_by_keyword
    // Also! YouTube doesn't accept the way wp_remote_get forms args,
    // so we have to stringify the args ourselves.

		$searchUrl = "https://www.googleapis.com/youtube/v3/search";
    $searchUrl .= "?q=" . $author_id;
    $searchUrl .= "&key=" . $developer_key;
    $searchUrl .= "&type=channel&part=snippet&fields=items(id/channelId)&maxResults=1";

    // The first result has the channel.
    $channelSearch = wp_remote_get( $searchUrl );
    // $code = wp_remote_retrieve_response_code( $channelSearch );
    // $message = wp_remote_retrieve_response_message( $channelSearch );
    $body = json_decode( wp_remote_retrieve_body( $channelSearch ), true );
    $channelId = $body["items"][0]["id"]["channelId"];

    // Next we need the first playlistId
    $channelsUrl = "https://www.googleapis.com/youtube/v3/channels";
    $channelsUrl .= "?id=" . $channelId;
    $channelsUrl .= "&key=" . $developer_key;
    $channelsUrl .= "&part=contentDetails";

    $playlistSearch = wp_remote_get( $channelsUrl );
    // $code = wp_remote_retrieve_response_code( $playlistSearch );
    // $message = wp_remote_retrieve_response_message( $playlistSearch );
    $playlistBody = json_decode( wp_remote_retrieve_body( $playlistSearch ), true );
    $playlistId = $playlistBody['items'][0]['contentDetails']['relatedPlaylists']['uploads'];

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
        $body = json_decode( wp_remote_retrieve_body( $response ) );
        $videofeed = $body->items;
        $pageToken = $body->nextPageToken;
      }
      catch ( Exception $e ) {
        echo "Encountered an API error -- code {$e->getCode()} - {$e->getMessage()}";
      }

      foreach ( $videofeed as $vid )
      {
        // extract fields
        $video = array();
        $video['host_id']     = 'youtube';
        $video['author_id']   = strtolower( $author_id );
        $video['video_id']    = $vid->id;
        $video['title']       = $vid->snippet->title;
        $video['description'] = $vid->snippet->description;
        $video['authorname']  = $vid->snippet->channelTitle;
        $video['videourl']    = 'https://www.youtube.com/watch?v=' . $vid->snippet->resourceId->videoId;
        $video['published']   = date( "Y-m-d H:i:s", strtotime( $vid->snippet->publishedAt ) );
        $video['author_url']  = "https://www.youtube.com/user/".$video['author_id'];
        $video['category']    = '';
        $video['keywords']    = array();
        $video['thumbnail']   = $vid->snippet->thumbnails->default->url;
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
