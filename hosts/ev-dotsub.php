<?php

if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if( ! class_exists( 'SP_EV_Dotsub' ) ) :

class SP_EV_Dotsub {

  public function __construct() {
    add_action( 'init', array( $this, 'initialize' ) );
  }

  function initialize() {
    $options = SP_External_Videos::admin_get_options();

    if( !isset( $options['hosts']['dotsub'] ) ):

      $options['hosts']['dotsub'] = array(
        'host_id' => 'dotsub',
        'host_name' => 'DotSub',
        'api_keys' => array(
          array(
            'id' => 'author_id',
            'label' => 'User ID',
            'required' => true,
            'explanation' => ''
          )
        ),
        'introduction' => "DotSub only requires a User ID in order to access your videos from another site.",
        'url' => 'https://dotsub.com',
        'link_title' => 'DotSub'
      );

      update_option( 'sp_external_videos_options', $options );

    endif;

  }

  /*
  *  fetch
  *
  *  DOTSUB API v1
  *  check https://github.com/dotsub/api-samples
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

    $baseurl = "https://dotsub.com/api/user/" . $author_id . "/media";
    $pagesize = 20;	// pagesize is automatically 20 at dotsub
    $offset = 0; // which is fine, because the API is slow. Don't increase
    $currentPage = 1;
    $url = $baseurl . '?pagesize=' . $pagesize .'&offset=' . $offset; // At first. Then we start adding offsets

    // for the request
    $headers = array(
      'Authorization' => 'Basic',
      'Content-Type' => 'application/json'
    );
    $args = array(
      'headers'     => $headers,
      'timeout' => 25
    );

    $new_videos = array();

    do {
      try {
        $response = wp_remote_get( $url, $args );
        $code = wp_remote_retrieve_response_code( $response );
        $message = wp_remote_retrieve_response_message( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ) );
        $result = $body->result;
        $count = count( $result );
        $totalPages = $body->totalPages;
      }
      catch ( Exception $e ) {
        echo "Encountered an API error -- code {$e->getCode()} - {$e->getMessage()}";
      }

      foreach ( $result as $vid )
      {
        // extract fields
        $video = array();
        $video['host_id']     = 'dotsub';
        $video['author_id']   = strtolower( $author_id );
        $video['video_id']    = $vid->uuid;
        $video['title']       = $vid->title;
        $video['description'] = $vid->description;
        $video['authorname']  = $vid->user;
        $video['videourl']    = $vid->displayURI;
        $video['published']   = date("Y-m-d H:i:s", strtotime( $vid->dateCreated ) );
        $video['author_url']  = $vid->externalIdentifier;
        $video['category']    = '';
        $video['keywords']    = array();
        $video['thumbnail']   = $vid->screenshotURI;
        $video['duration']    = $vid->duration;
        $video['ev_author']   = isset( $author['ev_author'] ) ? $author['ev_author'] : '';
        $video['ev_category'] = isset( $author['ev_category'] ) ? $author['ev_category'] : '';
        $video['ev_post_format'] = isset( $author['ev_post_format'] ) ? $author['ev_post_format'] : '';
        $video['ev_post_status'] = isset( $author['ev_post_status'] ) ? $author['ev_post_status'] : '';

        // add $video to the end of $new_videos
        array_push( $new_videos, $video );

      }

      // next page
      $currentPage++;
      // update request url to next page - it's offset by #videos, not pages
      $offset = $offset + $pagesize;
      $url = $baseurl . '?pagesize=' . $pagesize .'&offset=' . $offset;

    } while ( $totalPages > $currentPage );

    return $new_videos;

  }

} // end class

endif;

$SP_EV_Dotsub = new SP_EV_Dotsub;

?>
