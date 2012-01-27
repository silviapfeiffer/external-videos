<?php

/*
  Copyright 2010  Silvia Pfeiffer  (email : silviapfeiffer1@gmail.com)

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

*/

/*
 * Functions related to setting up a shortcode for the videos.
 * [external-videos link="page/overlay"]
 *      - provides a gallery with links to video pages or overlays
 * [external-videos feature="embed" width="600" height="360"]
 *      - provides the embed code 
 *        for new newest video to feature as an embed
 */


/// ***   Short Code   *** ///

// handles [external-videos ...] shortcode
function sp_external_videos_gallery($atts, $content = null) {
  global $wp_query, $post;

  // extract shortcode parameters
  // the "feature" parameter should allow for {thumbnail, embed}
  // as a choice between an embedded video and a thumbnail with overlay
  // - does embed only right now
  extract(shortcode_atts(array(
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
    query_posts($params);
    if ( have_posts() ) {
      // extract the video for the feature
      the_post();
      $videourl  = get_post_meta(get_the_ID(), 'video_url');
      $video = trim($videourl[0]);
      // get oEmbed code
      $oembed = new WP_Embed();
      $html = $oembed->shortcode(null, $video);
      // replace width with 600, height with 360
      $html = preg_replace ('/width="\d+"/', 'width="'.$width.'"', $html);
      $html = preg_replace ('/height="\d+"/', 'height="'.$height.'"', $html);

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
    $old_params = $wp_query->query;
    $params['paged'] = $old_params['paged'];
    query_posts($params);
    
    // display the gallery
    sp_ev_display_gallery($width, $height, $link);
  }
  
  //Reset Query
  wp_reset_query();
  $result = ob_get_clean();
  return $result;
}

function sp_ev_display_gallery ($width, $height, $link) {
  global $wp_query, $post, $features_3_0;
?>

  <?php // if ( $wp_query->max_num_pages > 1 ) : ?>
  <!-- see http://core.trac.wordpress.org/ticket/6453 -->
  <script type="text/javascript">
  //<![CDATA[
  var tb_pathToImage = "wp-includes/js/thickbox/loadingAnimation.gif";
  var tb_closeImage = "wp-includes/js/thickbox/tb-close.png";
  //]]>
  </script>
  <div id="nav-above" class="navigation">
    <div class="nav-previous"><?php next_posts_link( __( '<span class="meta-nav">&larr;</span> Older videos', 'twentyten' ) ); ?></div>
    <div class="nav-next"><?php previous_posts_link( __( 'Newer videos <span class="meta-nav">&rarr;</span>', 'twentyten' ) ); ?></div>
  </div><!-- #nav-above -->
  <?php // endif; ?>

  <div class="gallerycontainer" style="clear:all;">
    <?php
    while ( have_posts() ) {
      the_post();
      $thumbnail = get_post_meta(get_the_ID(), 'thumbnail_url');
      $thumb = $thumbnail[0];
      $videourl  = get_post_meta(get_the_ID(), 'video_url');
      $video = trim($videourl[0]);
      $description = get_post_meta(get_the_ID(), 'description');
      $desc = $description[0];
      $short_title = sp_ev_shorten_text(get_the_title(), 33);
      $thickbox_title = sp_ev_shorten_text(get_the_title(), 90);
      // get oEmbed code
      $oembed = new WP_Embed();
      $html = $oembed->shortcode(null, $video);
      // replace width with 600, height with 360
      $html = preg_replace ('/width="\d+"/', 'width="'.$width.'"', $html);
      $html = preg_replace ('/height="\d+"/', 'height="'.$height.'"', $html);
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
            if ($post->post_parent > 0) {
            ?>
              <a href="<?php echo get_permalink($post->post_parent) ?>">Blog post related to this video</a>
            <?php
            }
            ?>
            <br/>
            <?php
            if ($features_3_0) {
            ?>
            <a href="<?php the_permalink() ?>">Video page</a>
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
      <div class="nav-previous"><?php next_posts_link( __( '<span class="meta-nav">&larr;</span> Older videos', 'twentyten' ) ); ?></div>
      <div class="nav-next"><?php previous_posts_link( __( 'Newer videos <span class="meta-nav">&rarr;</span>', 'twentyten' ) ); ?></div>
    </div><!-- #nav-below -->
  <?php // endif; ?>
  <?php
}

?>