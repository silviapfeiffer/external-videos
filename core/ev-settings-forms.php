<?php
/*
 * External Videos Admin Settings form template
 *
 * Note: the five forms on this page are being AJAXified
 *
 * 1 delete author
 * 2 update videos
 * 3 add author
 * 4 plugin settings
 * 5 delete all videos
*/

if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// make these variables available
$options = SP_External_Videos::get_options();
$HOSTS = $options['hosts'];

?>


<div class="wrap ev-wrap">

  <h1><?php esc_attr_e( 'External Videos' ); ?></h1>

  <h2 class="nav-tab-wrapper">
    <a class="nav-tab nav-tab-active" href="#" data-div-name="settings-tab">Settings</a>
    <?php foreach( $HOSTS as $host ) {
      echo '<a class="nav-tab" data-div-name="' . $host['host_id'] . '-tab" href="#">' . $host['host_name'] . '</a>';
    } ?>

  </h2>

  <section class="settings-tab content-tab">

    <div id="poststuff">
      <div id="post-body" class="metabox-holder columns-2">
        <!-- main content -->
        <div id="post-body-content">
          <div class="postbox">
            <h2><span><?php esc_attr_e( 'About External Videos', 'external-videos' ); ?></span></h2>
            <div class="inside big">
              <p>
                <?php esc_attr_e(
                  'External Videos (EV) allows your WordPress site to connect to your accounts at video hosting sites. For each video it finds on a channel, it creates a new post in WordPress. For example, EV can find all the videos of the user "Fred" on YouTube, and add each of them as a new "external-videos" post.',
                  'external-videos'
                ); ?>
              </p>
              <p>
                <?php esc_attr_e(
                  'To connect a video channel, click on one of the tabs at the top. Multiple video accounts are supported.',
                  'external-videos'
                ); ?>
              </p>
              <p>
                <?php esc_attr_e(
                  'The plugin automatically checks once per day for new videos.',
                  'external-videos'
                ); ?>
              </p>
            </div><!-- .inside -->
          </div><!-- .postbox -->

          <br class="clear" />

          <div class="postbox">
            <h2><?php esc_attr_e('General Plugin Settings', 'external-videos'); ?></h2>
            <form id="ev_plugin_settings" class="inside" method="post" action="">
              <!-- <input type="hidden" name="external_videos" value="Y" /> -->
              <input type="hidden" name="action" value="ev_plugin_settings" />

              <?php // get saved options
                $ev_rss = $options['rss'];
                $ev_del = $options['delete'];
                $ev_attrib = $options['attrib'];
                $ev_loop = $options['loop'];
                $ev_slug = $options['slug'];
              ?>
              <fieldset>
                <label for="ev-loop">
                  <input type="checkbox" id="ev-loop" name="ev-loop" value="loop" <?php if ( $ev_loop == true ) echo "checked"; ?>/>
                  <span><?php esc_attr_e('Add external-video posts to the Home loop (latest Posts page)', 'external-videos'); ?></span>
                </label>
              </fieldset>
              <fieldset>
                <label for="ev-attrib">
                  <input type="checkbox" id="ev-attrib" name="ev-attrib" value="attrib" <?php if ( $ev_attrib == true ) echo "checked"; ?>/>
                  <span><?php esc_attr_e('Add category, author and hosting site links to bottom of video post content', 'external-videos'); ?></span>
                </label>
              </fieldset>
              <fieldset>
                <label for="ev-rss">
                  <input type="checkbox" id="ev-rss" name="ev-rss" value="rss" <?php if ( $ev_rss == true ) echo "checked"; ?>/>
                  <span><?php esc_attr_e('Add video posts to Website RSS feed', 'external-videos'); ?></span>
                </label>
              </fieldset>
              <fieldset>
                <label for="ev-delete">
                  <input type="checkbox" id="ev-delete" name="ev-delete" value="delete" <?php if ( $ev_del == true ) echo "checked"; ?>/>
                  <span><?php esc_attr_e('Move videos locally to trash when deleted on external site', 'external-videos'); ?></span>
                </label>
              </fieldset>
              <fieldset>
                <p><?php esc_attr_e( 'If you want a custom URL slug for External Videos permalinks and archive, enter it here:', 'external-videos'); ?></p>
                <label for="ev-cpt-slug">
                  <span><?php echo home_url( '/' ); ?></span><input type="text" id="ev-slug" name="ev-slug" placeholder="<?php esc_attr_e( 'external-videos', 'external-videos' ); ?>" value="<?php esc_attr_e( $ev_slug ); ?>"/>
                  <span class="spacer"></span><span><?php esc_attr_e('Default is "external-videos"', 'external-videos'); ?></span><br />
                  <strong><?php esc_attr_e( 'Be careful: this should not conflict with any other rewrite slug.', 'external-videos'); ?></strong>
                </label>
              </fieldset>
              <p class="">
                <input type="submit" name="Submit" class="button button-primary" value="<?php esc_attr_e('Save Settings'); ?>" /><span class="spacer"></span><strong class="feedback ml-3"></strong>
              </p>
            </form>
          </div><!-- .postbox -->
          <!-- end of Plugin Settings -->
          <br class="clear" />

          <h3><?php esc_attr_e( 'Update videos from channels', 'external-videos' ); ?></h3>
          <p>
            <?php esc_attr_e( 'The plugin normally updates videos every 24 hours. Next automatic update scheduled for:', 'external-videos' ); ?>
            <i><?php echo date( 'Y-m-d H:i:s. ', wp_next_scheduled( 'ev_daily_event' ) ) ?></i><br />
            <?php esc_attr_e( 'Click the button below to immediately check all channels for newly added or deleted videos.' ); ?>
          </p>

          <form id="ev_update_videos" method="post" action="">
            <div class="feedback"></div>
            <!-- <input type="hidden" name="external_videos" value="Y" /> -->
            <input type="hidden" name="action" value="ev_update_videos" />
            <p class="">
              <input type="submit" name="Submit" class="button" value="<?php esc_attr_e( 'Update videos from all channels', 'external-videos' ); ?>" />
              <div class="spinner inline"></div>
            </p>
          </form>

          <form id="ev_author_list" method="post" action="">
            <?php /* This is loaded by ajax now. */ ?>
          </form>
          <!-- end of Update Videos -->

          <br class="clear"/>

          <h3><?php esc_attr_e('Delete All Videos', 'external-videos'); ?></h3>
          <p>
            <?php esc_attr_e('Be careful with this option - you will lose all links you have built between blog posts and the video pages. This is really only meant as a reset option.', 'external-videos'); ?>
          </p>

          <form id="ev_delete_all" method="post" action="">
            <div class="feedback"></div>
            <!-- <input type="hidden" name="external_videos" value="Y" /> -->
            <input type="hidden" name="action" value="ev_delete_videos" />
            <p class="">
              <input type="submit" name="Submit" class="button" value="<?php esc_attr_e('Move all external videos to trash', 'external-videos'); ?>" />
              <div class="spinner inline"></div>
            </p>
          </form>
          <!-- end of Delete All Videos -->

          <br class="clear"/>
        </div><!-- post-body-content -->

        <!-- sidebar -->
        <div id="postbox-container-1" class="postbox-container">
          <div class="postbox">
            <h2><span class=""><?php esc_attr_e( 'Authentication', 'wp_admin_style' ); ?></span></h2>

            <div class="inside">
              <p>
                <?php esc_attr_e(
                  'For some hosts, you will have to login to your account and create API keys in order to access your videos.',
                  'external-videos'
                ); ?>
              </p>
            </div><!-- .inside -->

            <h2><span class=""><?php esc_attr_e( 'Links', 'wp_admin_style' ); ?></span></h2>

            <div class="inside">
              <?php foreach( $HOSTS as $host ){
                // print_r( $host['api_keys'][1] );
                if( isset( $host['api_keys'][1] ) ){
                  $url = $host['api_url'];
                  $title = $host['api_link_title'];
                  echo '<p><a target="_blank" href="' . esc_url( $url ) . '">' . $title . '</a></p>';
                }
              } ?>
            </div><!-- .inside -->
          </div><!-- .postbox -->
        </div><!-- #postbox-container-1 .postbox-container -->
      </div><!-- post-body -->
      <br class="clear">
    </div><!-- poststuff -->
  </section>

  <!-- BEGIN TABBED VIDEO HOST SECTIONS -->

  <?php foreach( $HOSTS as $host ){
    // Fill out if blanks
    $host_id = $host['host_id'];
    $host_name = isset( $host['host_name'] ) ? $host['host_name'] : '';
    $api_keys = isset( $host['api_keys'] ) ? $host['api_keys'] : array();
    $introduction = isset( $host['introduction'] ) ? $host['introduction'] : '';
    $api_url = isset( $host['api_url'] ) ? $host['api_url'] : '';
    $api_link_title = isset( $host['api_link_title'] ) ? $host['api_link_title'] : '';
    $authors = isset( $host['authors'] ) ? $host['authors'] : array();
    ?>
    <section class="<?php echo esc_attr( $host_id )?>-tab content-tab">

      <div id="poststuff">
        <div id="post-body" class="metabox-holder">
          <div id="post-body-content">
            <div class="postbox">
              <h2><?php echo esc_attr_e( 'Connecting to ', 'external-videos' ); ?><?php echo esc_attr( $host_name ); ?></h2>
              <div class="inside big">
                <p><?php echo esc_attr( $introduction ); ?><span class="spacer"></span><a title="<?php echo esc_attr( $api_link_title ); ?>" href="<?php echo esc_url( $api_url ); ?>" target="_blank"><?php echo esc_attr( $api_link_title ); ?></a></p>
              </div><!-- .inside -->
            </div><!-- .postbox -->
          </div><!-- post-body-content -->
        </div><!-- post-body -->
        <br class="clear">
      </div><!-- .poststuff -->

      <h2><?php echo sprintf( __( 'Your %s Channels', 'external-videos' ), esc_attr( $host_name ) ); ?></h3>

      <div class="ev_edit_authors_host" data-host="<?php echo esc_attr( $host_id )?>" id="ev_edit_authors_<?php echo esc_attr( $host_id )?>">
        <div class="feedback"></div>
        <div class="host-authors-list">
          <?php // THIS IS WHERE THE HOST AUTHORS LIST GOES ?>
          <?php // PUT THE ADD AUTHOR LIST AFTER EXISTING AUTHORS ?>
        </div>
      </div><!-- .ev_edit_host_authors -->

    </section>

  <?php } ?>

</div><!-- .wrap -->
