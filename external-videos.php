<?php
/*
* Plugin Name: External Videos
* Plugin URI: http://wordpress.org/extend/plugins/external-videos/
* Description: Automatically syncs your videos from YouTube, Vimeo, Dotsub, Wistia or Dailymotion to your WordPress site as new posts.
* Author: Silvia Pfeiffer and Andrew Nimmo
* Version: 1.1
* Author URI: http://www.gingertech.net/
* License: GPL2
* Text Domain: external-videos
* Domain Path: /localization
*/

/*
  Copyright 2010+  Silvia Pfeiffer  (email : silviapfeiffer1@gmail.com)

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

  @package    external-videos
  @author     Silvia Pfeiffer <silviapfeiffer1@gmail.com>, Andrew Nimmo <andrnimm@fastmail.fm>
  @copyright  Copyright 2010+ Silvia Pfeiffer
  @license    http://www.gnu.org/licenses/gpl.txt GPL 2.0
  @version    1.1
  @link       http://wordpress.org/extend/plugins/external-videos/

*/

if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if( ! class_exists( 'SP_External_Videos' ) ) :

class SP_External_Videos {

  public function __construct() {

    require_once( ABSPATH . 'wp-admin/includes/taxonomy.php' );

    require( plugin_dir_path( __FILE__ ) . 'core/ev-admin.php' );
    require( plugin_dir_path( __FILE__ ) . 'core/ev-helpers.php' );
    require( plugin_dir_path( __FILE__ ) . 'core/ev-widget.php' );
    require( plugin_dir_path( __FILE__ ) . 'core/ev-media-gallery.php' );
    require( plugin_dir_path( __FILE__ ) . 'core/simple_html_dom.php' );

    foreach( glob( plugin_dir_path( __FILE__ ) . '/hosts/*/ev-*.php' ) as $host ) {
      require $host;
    }

    require_once( plugin_dir_path( __FILE__ ) . 'mexp/media-explorer.php' );

    // includes do not bring methods into the class! they're standalone functions
    register_activation_hook( __FILE__, array( $this, 'activation' ) );
    register_deactivation_hook( __FILE__, array( $this, 'deactivation' ) );
    register_activation_hook( __FILE__, array( $this, 'rewrite_flush' ) );

    add_action( 'init', array( $this, 'initialize' ) );

    add_action( 'admin_head', array( $this, 'menu_icon' ) );
    add_action( 'admin_menu', array( $this, 'admin_settings' ) );
    add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );

    /// *** Setup of Videos Gallery: implemented in ev-media-gallery.php *** ///
    add_shortcode( 'external-videos', array( $this, 'gallery' ) );

    /// *** Setup of Widget: implemented in ev-widget.php file *** ///
    add_action( 'widgets_init',  array( $this, 'load_widget' ) );

    add_filter( 'pre_get_posts', array( $this, 'filter_query_post_type' ) );
    add_filter( 'pre_get_posts', array( $this, 'add_to_main_query' ) );
    add_filter( 'request', array( $this, 'feed_request' ) );

  }

  /*
  *  initialize
  *
  *  actions that need to go on the init hook
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param
  *  @return
  */

  function initialize() {

    $plugin_dir = basename( dirname( __FILE__ ) );
    load_plugin_textdomain( 'external-videos', false, $plugin_dir . '/localization/' );

    // create a "video" category to store posts against
    wp_create_category(__( 'External Videos', 'external-videos' ) );

    // load the saved options or initialize with default values
    $options = $this->get_options();
    // echo '<pre style="margin-left:150px;">$options: '; print_r($options); echo '</pre>';

    // create "external videos" post type
    register_post_type( 'external-videos', array(
      'label'           => __( 'External Videos', 'external-videos' ),
      'singular_label'  => __( 'External Video', 'external-videos' ),
      'description'     => __( 'Pulls in videos from external hosting sites', 'external-videos' ),
      'public'          => true,
      'publicly_queryable' => true,
      'show_ui'         => true,
      'capability_type' => 'post',
      'hierarchical'    => false,
      'query_var'       => true,
      'supports'        => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'trackbacks', 'custom-fields', 'comments', 'revisions', 'post-formats' ),
      'taxonomies'      => array( 'post_tag', 'category' ),
      'has_archive'     => true,
      'rewrite'         => array( 'slug' => $options['slug'] ),
      'yarpp_support'   => true
    ));

    // enable thickbox use for gallery
    wp_enqueue_style( 'thickbox' );
    wp_enqueue_script( 'thickbox' );

  }

  /*
  *  menu_icon
  *
  *  Icon for External Videos post type in admin menu
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param
  *  @return
  */

  function menu_icon(){  ?>

    <style type="text/css" media="screen">
      #menu-posts-external-videos .dashicons-admin-post:before {
        content: "\f126";
      }
    </style>
    <?php

  }

  /*
  *  admin_settings
  *
  *  Settings page
  *  Add the options page for External Videos Settings
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param
  *  @return
  */

  function admin_settings() {

    add_options_page(
      __( 'External Videos Settings', 'external-videos' ),
      __( 'External Videos', 'external-videos' ),
      'edit_posts',
      __FILE__,
      array( $this, 'settings_page' )
    );

  }

  /*
  *  settings_page
  *
  *  Used by admin_settings()
  *  This separate callback function creates the settings page html
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param
  *  @return
  */

  function settings_page() {

    // activate cron hook if not active
    $this->activation();
    // The form HTML
    include( plugin_dir_path( __FILE__ ) . 'core/ev-settings-forms.php' );

  }

  /*
  *  admin_scripts
  *
  *  Settings page
  *  Script necessary for presenting proper form options per host
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param    $hook
  *  @return
  */

  function admin_scripts( $hook ) {

    if( "settings_page_external-videos/external-videos" != $hook ) return;

    wp_register_style( 'ev-admin', plugin_dir_url( __FILE__ ) . '/css/ev-admin.css', array(), null, 'all' );
    wp_enqueue_style( 'ev-admin' );
    wp_register_script( 'ev-admin', plugin_dir_url( __FILE__ ) . '/js/ev-admin.js', array( 'jquery' ), false, true );
    wp_enqueue_script( 'ev-admin' );

    // for the nonce
    $settings_nonce = wp_create_nonce( 'ev_settings' );

    // Make these variables an object array for the jquery later
    wp_localize_script( 'ev-admin', 'evSettings', array(
      'ajax_url'      => admin_url( 'admin-ajax.php' ),
      'nonce'         => $settings_nonce
    ) );

  }

  /*
  *  get_options
  *
  *  Gets sp_external_videos_options, returns usable array $options
  *  which is stored into the SP_External_Videos class
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param
  *  @return
  */

  static function get_options(){

    // Load existing plugin options
    $options = get_option( 'sp_external_videos_options' );
    // echo '<pre>$raw_options: '; print_r($raw_options); echo '</pre>';

    if( !$options ) {
      $options = array();
    }
    // Upgrade options to v1.0: move author options to hosts
    $options = SP_External_Videos::convert_author_options( $options );

    // Set defaults for the basic options
    // There are new options that may not be set yet
    if( !array_key_exists( 'version', $options ) ) $options['version'] = 1;
    if( !array_key_exists( 'rss', $options ) ) $options['rss'] = false;
    if( !array_key_exists( 'delete', $options ) ) $options['delete'] = false;
    if( !array_key_exists( 'hosts', $options ) ) $options['hosts'] = array();
    if( !array_key_exists( 'attrib', $options ) ) $options['attrib'] = false;
    if( !array_key_exists( 'loop', $options ) ) $options['loop'] = false;
    if( !array_key_exists( 'slug', $options ) ) $options['slug'] = 'external-videos';

    // echo '<pre style="margin-left:150px;">$options: '; print_r($options); echo '</pre>';

    return $options;

  }

  /*
  *  convert_author_options
  *
  *  This is for an update of the plugin to version 1.0.
  *  Move authors array under respective hosts for much easier indexing.
  *  This is a database conversion function that is light and runs automatically
  *  but is really needed once only. Could be moved to settings-page AJAX
  *  to be explicitly run by user only if author options are detected.
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param
  *  @return
  */

  static function convert_author_options( $options ){

    if( !array_key_exists( 'hosts', $options ) ) return $options;
    if( !array_key_exists( 'authors', $options ) ) return $options;

    $AUTHORS = $options['authors'];

    foreach( $AUTHORS as $author ){
      $host_id = $author['host_id'];
      $author_id = $author['author_id'];
      $options['hosts'][$host_id]['authors'][$author_id] = $author;
    }

    unset( $options['authors'] );
    update_option( 'sp_external_videos_options', $options );

    return $options;

  }

  /*
  *  filter_query_post_type
  *
  *  add external-video posts to query on Category and Tag archive pages
  *  FIX by Chris Jean, chris@ithemes.com
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  0.26
  *
  *  @param    $query
  *  @return
  */

  function filter_query_post_type( $query ) {

    if ( ( isset( $query->query_vars['suppress_filters'] ) && $query->query_vars['suppress_filters'] ) || ( ! is_category() && ! is_tag() && ! is_author() ) )
      return $query;

    $post_type = get_query_var( 'post_type' );

    if ( 'any' == $post_type )
      return $query;

    if ( empty( $post_type ) ) {
      $post_type = 'any';
    }

    else {
      if ( ! is_array( $post_type ) )
        $post_type = array( $post_type );

      $post_type[] = 'external-videos';
    }

    $query->set( 'post_type', $post_type );

    return $query;

  }

  /*
  *  add_to_main_query
  *
  *  add external-video posts to Home page (Latest posts) query
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  0.26
  *
  *  @param    $query
  *  @return
  */

  function add_to_main_query( $query ) {

    $options = $this->get_options();
    if( $options['loop'] == false ) return;

    if( is_home() ) {
      $post_type = get_query_var( 'post_type' );
      // Default index page has no post_type set
      if( !isset( $post_type ) || !is_array( $post_type ) ) {
        $post_type = array( 'post' );
      }
      if( !in_array( 'external-videos', $post_type ) ) $post_type[] = 'external-videos';
      set_query_var ( 'post_type', $post_type );
    }
  }

  /*
  *  gallery
  *
  *  Sets up a shortcode for the videos.
  *  [external-videos link="page/overlay"]
  *      - provides a gallery with links to video pages or overlays
  *  [external-videos feature="embed" width="600" height="360"]
  *      - provides the embed code
  *        for new newest video to feature as an embed  *
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  0.26
  *
  *  @param    $atts, $content
  *  @return   shortcode HTML
  */

  function gallery( $atts, $content = null ) {

    global $wp_query, $post;

    // handles [external-videos ...] shortcode
    // extract shortcode parameters
    // the "feature" parameter should allow for {thumbnail, embed}
    // as a choice between an embedded video and a thumbnail with overlay
    // - does embed only right now

    extract( shortcode_atts( array(
      'link'    => 'overlay',
      'feature' => '',
      'width'   => '600',
      'height'  => '360',
      ), $atts));

    // start output buffer collection
    ob_start();

    // depending on shortcode parameters, do different things
    if ( $feature == 'embed' ) {
      $params = array(
        'posts_per_page' => 1,
        'post_type'      => 'external-videos',
        'post_status'    => 'publish',
      );
      query_posts( $params );
      if ( have_posts() ) {
        // extract the video for the feature
        the_post();

        $embed_url  = get_post_meta( get_the_ID(), 'embed_url' );
        $video = trim( $embed_url[0] );
        // get oEmbed code
        $oembed = new WP_Embed();
        $html = $oembed->shortcode( null, $video );
        // replace width with 600, height with 360
        $html = preg_replace ( '/width="\d+"/', 'width="'.$width.'"', $html );
        $html = preg_replace ( '/height="\d+"/', 'height="'.$height.'"', $html );

        // just print the embed code for the newest video
        echo $html;
      }
    } else {
      // extract the videos for the gallery
      $params = array(
        'posts_per_page' => 20,
        'post_type'      => 'external-videos',
        'post_status'    => 'publish',
      );
      $params['paged'] = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;
      query_posts( $params );

      // display the gallery
      $this->display_gallery( $width, $height, $link );
    }

    //Reset Query
    wp_reset_query();
    $result = ob_get_clean();
    return $result;

  }

  /*
  *  display_gallery
  *
  *  HTML for the shortcode.
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  0.26
  *
  *  @param    $width, $height, $link
  *  @return   HTML
  */

  function display_gallery ( $width, $height, $link ) {

    global $wp_query, $post, $features_3_0;

    // if ( $wp_query->max_num_pages > 1 ) : ?>

    <!-- see http://core.trac.wordpress.org/ticket/6453 -->
    <script type="text/javascript">
    //<![CDATA[
    var tb_pathToImage = "wp-includes/js/thickbox/loadingAnimation.gif";
    var tb_closeImage = "wp-includes/js/thickbox/tb-close.png";
    //]]>
    </script>
    <div id="nav-above" class="navigation">
      <div class="nav-previous"><?php next_posts_link( __( '<span class="meta-nav">&larr;</span> Older videos', 'external-videos' ) ); ?></div>
      <div class="nav-next"><?php previous_posts_link( __( 'Newer videos <span class="meta-nav">&rarr;</span>', 'external-videos' ) ); ?></div>
    </div><!-- #nav-above -->
    <?php // endif; ?>

    <div class="gallerycontainer" style="clear:all;">
      <?php
      while ( have_posts() ) {
        the_post();
        $thumbnail = esc_url( get_post_meta( get_the_ID(), 'thumbnail_url' ) );
        $thumb = $thumbnail[0];
        $embed_url  = esc_url( get_post_meta( get_the_ID(), 'embed_url' ) );
        $video = trim( $embed_url[0] );
        $description = esc_attr( get_post_meta( get_the_ID(), 'description' ) );
        $desc = $description[0];
        $short_title = sp_ev_shorten_text( get_the_title(), 33 );
        $thickbox_title = sp_ev_shorten_text( get_the_title(), 90 );
        // get oEmbed code
        $oembed = new WP_Embed();
        $html = $oembed->shortcode( null, $video );
        // replace width with 600, height with 360
        $html = preg_replace ( '/width="\d+"/', 'width="' . $width . '"', $html );
        $html = preg_replace ( '/height="\d+"/', 'height="' . $height . '"', $html );
      ?>
      <div style="margin:2px; height:auto; width:auto; float:left;">
        <?php
        // display overlay if requested
        if ($link == "page") {
        ?>
          <a href="<?php the_permalink() ?>"
             title="<?php echo $thickbox_title ?>">
            <div style="display:box; width:120px; height:90px;">
              <img title="<?php the_title() ?>" src="<?php echo $thumb ?>"
                style="display:inline; margin:0; border:1px solid black; width:120px; height:90px"/>
            </div>
          </a>
          <div style="width:120px; height: 12px; margin-bottom:7px; line-height: 90%">
            <small><i><?php echo get_the_time('F j, Y') ?></i></small>
          </div>
          <div style="width:120px; height: 30px; margin-bottom:20px; line-height: 80%">
            <small><?php echo $short_title ?></small>
          </div>
        <?php
        // display overlay if requested
        } else {
        ?>
          <a href="#TB_inline?height=500&width=700&inlineId=hiddenModalContent_<?php the_ID() ?>"
             title="<?php echo $thickbox_title ?>" class="thickbox">
            <div style="display:box; width:120px; height:90px;">
              <img title="<?php the_title() ?>" src="<?php echo $thumb ?>"
                style="display:inline; margin:0; border:1px solid black; width:120px; height:90px"/>
            </div>
          </a>
          <div style="width:120px; height: 12px; margin-bottom:7px; line-height: 90%">
            <small><i><?php echo get_the_time('F j, Y') ?></i></small>
          </div>
          <div style="width:120px; height: 30px; margin-bottom:20px; line-height: 80%">
            <small><?php echo $short_title ?></small>
          </div>
          <!-- Hidden content for the thickbox -->
          <div id="hiddenModalContent_<?php echo $post->ID ?>" style="display:none;">
            <p align="center"  style="margin-bottom:10px;">
              <?php echo $html ?>
            </p>
            <div style="margin-bottom:10px;">
              <?php
              if ( $post->post_parent > 0 ) {
              ?>
                <a href="<?php echo get_permalink( $post->post_parent ) ?>"><?php _e( 'Blog post related to this video', 'external-videos' ); ?></a>
              <?php
              }
              ?>
              <br/>
              <?php
              if ( $features_3_0 ) {
              ?>
              <a href="<?php the_permalink(); ?>"><?php _e( 'Video page', 'external-videos' ); ?></a>
              <?php
              }
              ?>
            </div>
            <div style="margin-bottom:10px;">
              <?php echo $desc ?>
            </div>
            <div style="text-align: center;">
              <input type="submit" id="Login" value="OK" onclick="tb_remove()"/>
            </div>
          </div>
        <?php
        }
        ?>
      </div>
      <?php
      }
      ?>
      <div style="clear: both;"></div>
    </div>

    <?php // if ( $wp_the_query->max_num_pages > 1 ) : ?>
      <br/>
      <div id="nav-below" class="navigation">
        <div class="nav-previous"><?php next_posts_link( __( '<span class="meta-nav">&larr;</span> Older videos', 'external-videos' ) ); ?></div>
        <div class="nav-next"><?php previous_posts_link( __( 'Newer videos <span class="meta-nav">&rarr;</span>', 'external-videos' ) ); ?></div>
      </div><!-- #nav-below -->
    <?php // endif; ?>
    <?php
  }

  /*
  *  feed_request
  *
  *  add external-video posts to RSS feed
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  0.26
  *
  *  @param    $qv
  *  @return  $qv
  */

  function feed_request( $qv ) {

    $options = $this->get_options();

    if ( $options['rss'] == true ) {
      if ( isset( $qv['feed'] ) && !isset( $qv['post_type'] ) )
        $qv['post_type'] = array( 'external-videos', 'post' );
    }

    return $qv;
  }

  /*
  *  load_widget
  *
  *  load the widget defined in ev-widget.php
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  0.26
  *
  *  @param
  *  @return
  */

  function load_widget() {

    return register_widget( 'WP_Widget_SP_External_Videos' );

  }

  /*
  *  activation
  *
  *  register daily cron on plugin activation
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  0.26
  *
  *  @param
  *  @return
  */

  static function activation() {

    if ( !wp_next_scheduled( 'ev_daily_event' ) ) {
      wp_schedule_event( time(), 'daily', 'ev_daily_event' );
    }

  }

  /*
  *  activation
  *
  *  unregister daily cron on plugin deactivation
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  0.26
  *
  *  @param
  *  @return
  */

  function deactivation() {

    wp_clear_scheduled_hook( 'ev_daily_event' );

  }

  /*
  *  rewrite_flush
  *
  *  Flush rewrite on plugin activation
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  0.26
  *
  *  @param
  *  @return
  */

  function rewrite_flush() {
    // First, we "add" the custom post type via the initialize function.
    $this->initialize();

    // ATTENTION: This is *only* done during plugin activation hook in this example!
    // You should *NEVER EVER* do this on every page load!!
    flush_rewrite_rules();
  }

} // end class
endif;

/*
* Launch the whole plugin
*/

global $SP_External_Videos;
$SP_External_Videos = new SP_External_Videos();

?>
