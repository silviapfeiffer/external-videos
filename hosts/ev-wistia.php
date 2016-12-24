<?php

if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if( ! class_exists( 'SP_EV_Wistia' ) ) :

class SP_EV_Wistia {

  /*
  *  fetch
  *
  *  WISTIA DATA API v1
  *  check https://wistia.com/doc/data-api#making_requests
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

    $baseurl = "https://api.wistia.com/v1/medias.json?sort_by=created&sort_direction=1";
    // set other options
    $page = 0;
    $per_page = 1;	// One by one because we get no paging info from Wistia
    $thumb_w = get_option( "large_size_w" ); // Size could be set in options at some point
    $thumb_h = get_option( "large_size_h" );

    $url = $baseurl . "&page=" . $page . "&per_page=" . $per_page;

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
        $body = json_decode( wp_remote_retrieve_body( $response ) );
      }
      catch ( Exception $e ) {
        echo "Encountered an API error -- code {$e->getCode()} - {$e->getMessage()}";
      }

      // loop through videos returned & extract fields
      foreach ($body as $vid) {
        $video = array();
        $video['host_id']     = 'wistia';
        $video['author_id']   = strtolower($author_id);
        $video['video_id']    = $vid->hashed_id;
        $video['title']       = $vid->name;
        $video['description'] = $vid->description;
        $video['authorname']  = $author_id;
        $video['videourl']    = "https://$author_id.wistia.com/medias/" . $vid->hashed_id;
        $video['published']   = date( "Y-m-d H:i:s", strtotime( $vid->created ));
        $video['author_url']  = "https://$author_id.wistia.com/projects";
        $video['category']    = '';
        $video['keywords']    = array();
        // $video['thumbnail']   = $vid->thumbnail->url;

        // WISTIA DELIVERS HUGE THUMBNAILS AUTO CROPPED FROM THEIR API.
        // IF YOU WANT A SMALLER CROP OF THE THUMBNAIL,
        // RIGHT TRIM THE URL UNTIL THE EQUALS, THEN ADD WIDTH . X . HEIGHT
        $thumbnail_url	= $vid->thumbnail->url;
        $equipos = strripos( $thumbnail_url, "=" ) ? strripos( $thumbnail_url, "=" ) : 0;
        $thumbnail_url = substr( $thumbnail_url, 0, $equipos ) . "=" . $thumb_w . "X" . $thumb_h;

        $video['thumbnail']   = $thumbnail_url;
        $video['duration']    = $vid->duration;
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
