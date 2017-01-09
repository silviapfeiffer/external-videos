<?php
/*
*  Class to implement display of a widget in the Wordpress Appearance
*  which displays the most recent external videos.
*  you can give widget a title and define the number of recent videos to display.
*/

if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class WP_Widget_SP_External_Videos extends WP_Widget {

  function __construct() {

    $widget_ops = array( 'classname' => 'widget_recent_videos', 'description' => __( "The most recent external videos on your blog", 'external-videos' ) );
    parent::__construct( 'recent-videos', __( 'Recent Videos' ), $widget_ops );
    $this->alt_option_name = 'widget_recent_entries';

    add_action( 'save_post', array( &$this, 'flush_widget_cache' ) );
    add_action( 'deleted_post', array( &$this, 'flush_widget_cache' ) );
    add_action( 'switch_theme', array( &$this, 'flush_widget_cache' ) );

  }

  function flush_widget_cache() {

    wp_cache_delete( 'widget_recent_videos', 'widget' );

  }

  function form( $instance ) {

    $title = isset( $instance['title'] ) ? esc_attr( $instance['title'] ) : '';
    if ( !isset( $instance['number'] ) || !$number = (int) $instance['number'] )
      $number = 5;
    $thumbnail = isset($instance['thumbnail']) ? (bool) $instance['thumbnail'] : false;
    ?>

    <p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
    <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" /></p>

    <p><label for="<?php echo $this->get_field_id('number'); ?>"><?php _e('Number of posts to show:'); ?></label>
    <input id="<?php echo $this->get_field_id('number'); ?>" name="<?php echo $this->get_field_name('number'); ?>" type="text" value="<?php echo $number; ?>" size="3" /><br />
    <small><?php _e('(at most 15)'); ?></small></p>

    <p><label for="<?php echo $this->get_field_id('thumbnail'); ?>"><?php _e('Show video thumbnails:', 'external-videos'); ?></label>
    <input id="<?php echo $this->get_field_id('thumbnail'); ?>" name="<?php echo $this->get_field_name('thumbnail'); ?>" type="checkbox" <?php if ( $thumbnail ) { ?> checked <?php } ?>/></p>
    <?php

  }

  function update( $new_instance, $old_instance ) {

    $instance = $old_instance;
    $instance['title'] = strip_tags( $new_instance['title'] );
    $instance['number'] = (int) $new_instance['number'];
    $instance['thumbnail'] = (bool) $new_instance['thumbnail'];
    $this->flush_widget_cache();

    $alloptions = wp_cache_get( 'alloptions', 'options' );
    if ( isset( $alloptions['widget_recent_entries'] ) )
      delete_option( 'widget_recent_entries' );

    return $instance;

  }

  function widget( $args, $instance ) {

    $cache = wp_cache_get( 'widget_recent_videos', 'widget' );

    if ( !is_array( $cache ) )
    $cache = array();

    if ( isset( $cache[$args['widget_id']] ) ) {
      echo $cache[$args['widget_id']];
      return;
    }

    ob_start();
    extract( $args );

    $title = apply_filters(
      'widget_title',
      empty( $instance['title']) ? __('Recent Videos', 'external-videos') : $instance['title'],
      $instance,
      $this->id_base
    );

    if ( !$number = (int) $instance['number'] )
      $number = 10;
    else if ( $number < 1 )
      $number = 1;
    else if ( $number > 15 )
      $number = 15;
    $thumbnail = (bool) $instance['thumbnail'];

    $r = new WP_Query( array(
      'showposts' => $number,
      'nopaging' => 0,
      'post_type' => 'external-videos',
      'post_status' => 'publish',
      'caller_get_videos' => 1
    ));

    if ( $r->have_posts() ) :
      ?>
      <?php echo $before_widget; ?>
      <?php if ( $title ) echo $before_title . $title . $after_title; ?>
      <?php if ( !$thumbnail) { ?>
        <ul>
      <?php } ?>
      <?php  while ($r->have_posts()) : $r->the_post(); ?>

        <?php if ( $thumbnail ) {
          $thumb_urls = get_post_meta(get_the_ID(), 'thumbnail_url');
          $thumb = $thumb_urls[0];
        ?>
          <div style="margin-top: 5px;">
            <img src="<?php echo $thumb ?>"
                     style="display:inline; margin:0 5px 0 0; border:1px solid black; width:90px; height:60px; float: left;"/>
          <?php } else { ?>
          <li>
          <?php } ?>
            <a href="<?php the_permalink() ?>" title="<?php echo esc_attr(get_the_title() ? get_the_title() : get_the_ID()); ?>"><?php if ( get_the_title() ) the_title(); else the_ID(); ?></a>
        <?php if ( !$thumbnail) { ?>
          </li>
        <?php } else { ?>
          </div><div style="clear: both;"></div>
        <?php } ?>

      <?php endwhile; ?>

      <?php if ( !$thumbnail ) { ?>
        </ul>
      <?php } ?>

      <?php echo $after_widget; ?>
      <?php
      wp_reset_query();  // Restore global post data stomped by the_post().

    endif;

    $cache[$args['widget_id']] = ob_get_flush();

    wp_cache_add( 'widget_recent_videos', $cache, 'widget' );

  }
}

?>
