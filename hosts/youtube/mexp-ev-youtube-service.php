<?php

// Made to work with External Videos functions.
// If we have no authors let's get out of here.
$options = get_option( 'sp_external_videos_options' );
if( empty( $options['hosts']['youtube']['authors'] ) ) return;

// Classname should not conflict with default MEXP Services
// in case MEXP is already installed
class MEXP_EV_YouTube_Service extends MEXP_Service {

  const SERVICE = 'youtube';
  const EV_SERVICE = 'ev_youtube';

	public function __construct() {

		require_once dirname( __FILE__ ) . '/mexp-ev-youtube-template.php';
		# Go!
		$this->set_template( new MEXP_EV_YouTube_Template );
	}

	public function load() {

		add_action( 'mexp_enqueue', array( $this, 'enqueue_statics' ) );
		add_filter( 'mexp_tabs', array( $this, 'tabs' ), 10, 1 );
		add_filter( 'mexp_labels', array( $this, 'labels' ), 10, 1 );

	}

	public function enqueue_statics() {

		$mexp = Media_Explorer::init();

	}

  public function request( array $request ) {

    $params = $request['params'];
    $author_id = $params['author_id'];
    $search = $params['q'];

    $args = array(
      'post_type' => 'external-videos',
      'nopaging' => true,
      'meta_query' => array(
        array(
          'key' => 'host_id',
          'value' => self::SERVICE
        ),
        array(
          'key' => 'author_id',
          'value' => $author_id
        )
      )
    );
    if( $search ) $args['s'] = $search;

    $query_response = get_posts( $args );

    // error_log( '$query_response: ' . print_r( $query_response, true) );

		// Create the response for the API
		$response = new MEXP_Response();

		if ( !isset( $query_response ) )
			return false;

    // Set up items
		foreach ( $query_response as $index => $video ) {
      setup_postdata( $video );

			$item = new MEXP_Response_Item();

      $embed_code = get_post_meta( $video->ID, 'embed_code' );
      $thumbnail_url = get_post_meta( $video->ID, 'thumbnail_url' );
      // error_log( '$embed_code: ' . print_r( $embed_code, true) );

			$item->set_url( $embed_code[0] );
			$item->add_meta( 'user', $author_id );
			// $item->set_id( (int) $params['startIndex'] + (int) $index );
      $item->set_id( (int) $index );
			$item->set_content( $video->post_title );
			$item->set_thumbnail( $thumbnail_url[0] );
			$item->set_date( strtotime( $video->post_date ) );
			$item->set_date_format( 'g:i A - j M y' );
			$response->add_item( $item );
		}

    // maybe do a calculation to figure out whether to send $paged for this batch

		return $response;
	}

  // EV media explorer tabs are by author
	public function tabs( array $tabs ) {
		$tabs[self::EV_SERVICE] = array();

    $options = class_exists( 'SP_External_Videos' ) ? SP_External_Videos::get_options() : array();
    if( !isset( $options['hosts'][self::SERVICE]['authors'] ) ) {
      return $tabs;
    } else {
      $authors = $options['hosts'][self::SERVICE]['authors'];
      $count = 0;
      foreach( $authors as $author ){
        $author_id = $author['author_id'];
        ( $count == 0 ) ? ( $default = true ) : ( $default = false );
        $tabs[self::EV_SERVICE][$author_id] = array(
          'text' => _x( $author_id, 'Tab title', 'mexp'),
          'defaultTab' => $default
        );
        $count++ ;
      }
    }

		return $tabs;
	}

	public function labels( array $labels ) {

		$labels[self::EV_SERVICE] = array(
			'title'     => __( 'Insert YouTube EV', 'mexp' ),
			'insert'    => __( 'Insert', 'mexp' ),
			'noresults' => __( 'No videos matched your search query.', 'mexp' ),
		);

		return $labels;
	}
}

add_filter( 'mexp_services', 'mexp_service_ev_youtube' );

function mexp_service_ev_youtube( array $services ) {
	$services['ev_youtube'] = new MEXP_EV_YouTube_Service;
	return $services;
}
