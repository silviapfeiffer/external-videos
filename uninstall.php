<?php

if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! current_user_can( 'activate_plugins' ) ) {
	return;
}
check_admin_referer( 'bulk-plugins' );

if ( __FILE__ != WP_UNINSTALL_PLUGIN ) {
	return;
}

$ev_posts = new WP_Query( array(
  'post_type' => 'external-videos',
  'nopaging' => 1
) );

if( $ev_posts->have_posts() ) {
  while ( $ev_posts->have_posts() ) : $ev_posts->the_post();

    $post = get_post( get_the_ID() );
    $post->post_status = 'trash';
    wp_update_post( $post );
    $count_deleted += 1;

  endwhile;
  wp_reset_postdata();
}

delete_option( 'sp_external_videos_options' );
