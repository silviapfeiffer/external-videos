<?php

if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if( ! class_exists( 'SP_EV_Vimeo' ) ) :

class SP_EV_Vimeo {

  public function __construct() {
    add_action( 'init', array( $this, 'initialize' ) );
  }

  function initialize() {
    $options = SP_External_Videos::admin_get_options();

    if( !isset( $options['hosts']['vimeo'] ) ) :

      $options['hosts']['vimeo'] = array(
        'host_id' => 'vimeo',
        'host_name' => 'Vimeo',
        'api_keys' => array(
          array(
            'id' => 'author_id',
            'label' => 'User ID',
            'required' => true,
            'explanation' => 'Required'
          ),
          array(
            'id' => 'developer_key',
            'label' => 'Client Identifier',
            'required' => true,
            'explanation' => 'Required - this needs to be generated in your Vimeo API Apps'
          ),
          array(
            'id' => 'secret_key',
            'label' => 'Client Secret',
            'required' => true,
            'explanation' => 'Required - this needs to be generated in your Vimeo API Apps'
          ),
          array(
            'id' => 'auth_token',
            'label' => 'Personal Access Token',
            'required' => false,
            'explanation' => 'Optional - this needs to be generated in your Vimeo API Apps. It gives you access to both your public and private videos.'
          )
        ),
        'introduction' => "Vimeo's API v3.0 requires you to generate an oAuth2 Client Identifier, Client Secret and Personal Access Token from your account, in order to access your videos from another site (like this one). ",
        'url' => 'https://developer.vimeo.com/apps',
        'link_title' => 'Vimeo API'
      );

      update_option( 'sp_external_videos_options', $options );

    endif;

  }

	/*
	*  fetch
	*
	*  NEW VIMEO API 3.0 oAuth2
	*  Requires client identifier (developer_key) and client secret (secret_key)
  *  Optional personal access token gives you access to private videos
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
		$secret_key = $author['secret_key'];
		$access_token = $author['auth_token'];

    if( ! $access_token ) {
      // send request
      $url = 'https://api.vimeo.com/oauth/authorize/client?grant_type=client_credentials';
      // $url = 'https://api.vimeo.com/oauth/authorize/client';
      $auth = base64_encode( $developer_key . ':' . $secret_key );
      // $data = array(
      //  'grant_type' => 'client_credentials',
      //  'scope' => 'public private'
      // );
      // $data = json_encode( $data );
      $headers = array(
        'Authorization' => 'Basic ' . $auth,
        'Content-Type' => 'application/json'
      );
      $args = array(
        'headers'     => $headers//,
        // 'data'        => $data
      );

      $response = wp_remote_post( $url, $args );
      $code = wp_remote_retrieve_response_code( $response );
      $message = wp_remote_retrieve_response_message( $response );
      $body = json_decode( wp_remote_retrieve_body( $response ) );

      echo '<pre>POST: '; print_r( $response ); echo '</pre>';
      echo '<pre>CODE: '; print_r( $code ); echo '</pre>';
      echo '<pre>MESSAGE: '; print_r( $message ); echo '</pre>';

      $access_token = $body->access_token;
      echo '<pre>TOKEN: '; print_r( $body->access_token ); echo '</pre>';
    }

    // Now we're legit, supposedly.
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
        $videoreq = wp_remote_get( $url, $args );
        // Adjust array to get to the data
        $videofeed = json_decode( wp_remote_retrieve_body( $videoreq ) );
        $pagefeed = $videofeed->data; //$videofeed->paging->next
        $feedarray = json_decode( json_encode( $pagefeed ), true );
        $next = $videofeed->paging->next;
        // $page = $videofeed->page;
        // $per_page = $videofeed->per_page;
        // $total = $videofeed->total;
      }
      catch ( Exception $e ) {
        echo "Encountered an API error -- code {$e->getCode()} - {$e->getMessage()}";
      }

      foreach ( $feedarray as $vid )
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
