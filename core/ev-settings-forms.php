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
$AUTHORS = $options['authors'];
$HOSTS = $options['hosts'];

// some re-usable settings:
function sp_ev_author_settings(){ ?>
  <tr>
    <th scope="row">
      <?php esc_attr_e('Set Post Status', 'external-videos'); ?>
    </th>
    <td>
      <select name='post_status' id='ev_post_status'>
        <option value='publish' selected><?php esc_attr_e('Published') ?></option>
        <option value='pending'><?php esc_attr_e('Pending Review') ?></option>
        <option value='draft'><?php esc_attr_e('Draft') ?></option>
        <option value='private'><?php esc_attr_e('Privately Published') ?></option>
        <option value='future'><?php esc_attr_e('Scheduled') ?></option>
      </select>
    </td>
  </tr>
  <tr>
    <th scope="row">
      <?php esc_attr_e('Default WP Author', 'external-videos'); ?>
    </th>
    <td>
      <?php wp_dropdown_users(); ?>
    </td>
  </tr>
  <tr>
    <th scope="row">
      <?php esc_attr_e('Default Post Format'); ?>
    </th>
    <td>
      <?php
      $post_formats = get_post_format_strings();
      unset( $post_formats['video'] );
      ?>
      <select name="post_format" id="ev_post_format">
        <option value="video"><?php echo get_post_format_string( 'video' ); ?></option>
        <?php foreach ( $post_formats as $format_slug => $format_name ): ?>
          <option<?php selected( get_option( 'post_format' ), $format_slug ); ?> value="<?php echo esc_attr( $format_slug ); ?>"><?php echo esc_html( $format_name ); ?></option>
        <?php endforeach; ?>
      </select>
    </td>
  </tr>
  <tr>
    <th scope="row">
      <?php esc_attr_e('Default Post Category'); ?>
    </th>
    <td>
      <?php $selected_cats = array( get_cat_ID('External Videos', 'external-videos') ); ?>
      <ul style="">
        <?php wp_category_checklist(0, 0, $selected_cats, false, null, true); ?>
      </ul>
    </td>
  </tr>

  <?php
}

?>

<pre><?php // print_r( $VIDEO_HOSTS ); ?></pre>

<div class="wrap">

  <h1><?php esc_attr_e( 'External Videos' ); ?></h1>

  <h2 class="nav-tab-wrapper">
    <a class="nav-tab nav-tab-active" href="#" data-div-name="settings-tab">Settings</a>
    <?php foreach( $HOSTS as $host ) {
      echo '<a class="nav-tab" data-div-name="' . $host['host_id'] . '-tab" href="#">' . $host['host_name'] . '</a>';
    } // This doesn't really save work. what if they are re-ordered? better re-order sections below*/?>

  </h2>

  <section class="settings-tab content-tab">

    <div id="poststuff">
      <div id="post-body" class="metabox-holder columns-2">
        <!-- main content -->
        <div id="post-body-content">
          <div class="postbox">

            <h2><span><?php esc_attr_e( 'About External Videos', 'external-videos' ); ?></span></h2>

            <div class="inside">
              <p>
                <?php esc_attr_e(
                  'External Videos allows your WordPress site to connect to your accounts at video hosting sites. For each video it finds on your channel, it creates a new post in WordPress of post_type "external-videos". ',
                  'external-videos'
                ); ?>
              </p>
              <p>
                <?php esc_attr_e(
                  'For example, EV can find all the videos of the user "Fred" on YouTube, and add each of them as a new "external-videos" post. This is sometimes called "automatic cross-posting".',
                  'external-videos'
                ); ?>
              </p>
              <p>
                <?php esc_attr_e(
                  'To connect a video channel, click on one of the tabs at the top.',
                  'external-videos'
                ); ?>
              </p>
              <p>
                <?php esc_attr_e(
                  'The plugin automatically checks once per day for new videos. Multiple video accounts are supported.',
                  'external-videos'
                ); ?>
              </p>

            </div>
            <!-- .inside -->
          </div>
          <!-- .postbox -->

          <h3><?php esc_attr_e('Connected Accounts', 'external-videos'); ?></h3>

          <form id="ev_author_list" method="post" action="">
            <?php /* This is loaded by ajax now. */ ?>
          </form>

          <br class="clear" />
          <!-- end of Connected Accounts -->

          <h3><?php esc_attr_e( 'Update All Channels', 'external-videos' ); ?></h3>
          <p>
            <?php esc_attr_e( 'Click the button below to immediately check all channels for newly added or deleted videos. The plugin normally updates videos every 24 hours. Next automatic update scheduled for:', 'external-videos' ); ?>
            <i><?php echo date( 'Y-m-d H:i:s', wp_next_scheduled( 'ev_daily_event' ) ) ?></i>
          </p>

          <form id="ev_update_videos" method="post" action="">
            <div class="feedback"></div>
            <!-- <input type="hidden" name="external_videos" value="Y" /> -->
            <input type="hidden" name="action" value="ev_update_videos" />
            <div class="submit">
              <input type="submit" name="Submit" class="button" value="<?php esc_attr_e( 'Update videos from all channels', 'external-videos' ); ?>" />
              <div class="spinner inline"></div>
            </div>
          </form>

          <!-- end of Update Videos -->

          <hr />

          <h3><?php esc_attr_e('Delete All Videos', 'external-videos'); ?></h3>
          <p>
            <?php esc_attr_e('Be careful with this option - you will lose all links you have built between blog posts and the video pages. This is really only meant as a reset option.', 'external-videos'); ?>
          </p>

          <form id="ev_delete_all" method="post" action="">
            <div class="feedback"></div>
            <!-- <input type="hidden" name="external_videos" value="Y" /> -->
            <input type="hidden" name="action" value="ev_delete_videos" />
            <div class="submit">
              <input type="submit" name="Submit" class="button" value="<?php esc_attr_e('Move all external videos to trash', 'external-videos'); ?>" />
              <div class="spinner inline"></div>
            </div>
          </form>

          <!-- end of Delete All Videos -->

          <hr />

          <h3><?php esc_attr_e('General Plugin Settings', 'external-videos'); ?></h3>
          <form id="ev_plugin_settings" method="post" action="">
            <!-- <input type="hidden" name="external_videos" value="Y" /> -->
            <input type="hidden" name="action" value="ev_plugin_settings" />

            <?php // get saved options
              $ev_rss = $options['rss'];
              $ev_del = $options['delete'];
              $ev_attrib = $options['attrib'];
            ?>
            <fieldset>
              <label for="ev-rss">
                <input type="checkbox" id="ev-rss" name="ev-rss" value="rss" <?php if ($ev_rss == true) echo "checked"; ?>/>
                <span><?php esc_attr_e('Add video posts to Website RSS feed', 'external-videos'); ?></span>
              </label>
            </fieldset>
            <fieldset>
              <label for="ev-delete">
                <input type="checkbox" id="ev-delete" name="ev-delete" value="delete" <?php if ($ev_del == true) echo "checked"; ?>/>
                <span><?php esc_attr_e('Move videos locally to trash when deleted on external site', 'external-videos'); ?></span>
              </label>
            </fieldset>
            <fieldset>
              <label for="ev-attrib">
                <input type="checkbox" id="ev-attrib" name="ev-attrib" value="attrib" <?php if ($ev_attrib == true) echo "checked"; ?>/>
                <span><?php esc_attr_e('Add category, author and hosting site links to bottom of video post content', 'external-videos'); ?></span>
              </label>
            </fieldset>
            <p class="submit">
              <input type="submit" name="Submit" class="button button-primary" value="<?php esc_attr_e('Save Settings'); ?>" /><strong class="feedback ml-3"></strong>
            </p>
          </form>

        <!-- end of Plugin Settings -->

        </div>
        <!-- post-body-content -->

        <!-- sidebar -->
        <div id="postbox-container-1" class="postbox-container">

          <div class="postbox">

            <h2><span class=""><?php esc_attr_e( 'Authentication', 'wp_admin_style' ); ?></span></h2>

            <div class="inside">
              <p>
                <?php esc_attr_e(
                  'To allow WordPress to connect to your account on most hosts, you will have to login to those accounts and create authentication credentials ("API keys").',
                  'external-videos'
                ); ?>
              </p>
            </div>
            <!-- .inside -->

          </div>
          <!-- .postbox -->

        </div>
        <!-- #postbox-container-1 .postbox-container -->

      </div>
      <!-- post-body -->
      <br class="clear">

    </div>
    <!-- poststuff -->

  </section>

  <!-- BEGIN TABBED SECTIONS -->

  <?php foreach( $HOSTS as $host ){
    $id = $host['host_id'];
    $name = $host['host_name'];
    $api_keys = $host['api_keys'];
    $intro = $host['introduction'];
    $url = $host['api_url'];
    $link = $host['api_link_title'];
    ?>
    <section class="<?php echo esc_attr( $id )?>-tab content-tab">

      <div id="poststuff">
        <div class="postbox">
          <div class="inside">
            <p><?php echo esc_attr( $intro ); ?><span class="spacer"></span><a title="<?php echo esc_attr( $link ); ?>" href="<?php echo esc_url( $url ); ?>" target="_blank"><?php echo esc_attr( $link ); ?></a></p>
          </div><!-- .inside -->
        </div><!-- .postbox -->
      </div><!-- .poststuff -->

      <h3><?php echo sprintf( __( 'Add %s Channel', 'external-videos' ), esc_attr( $name ) ); ?></h3>
      <form id="ev_add_<?php echo esc_attr( $id )?>" class="ev_add_author" method="post" action="">
        <input type="hidden" name="host_id" value="<?php echo esc_attr( $id )?>" />
        <div class="feedback"></div>
        <table class="form-table">
          <tbody>
            <?php foreach( $api_keys as $key ){ ?>
              <tr>
                <th scope="row">
                  <span><?php echo esc_attr( $key['label'] ); ?></span>
                </th>
                <td>
                  <input type="text" name="<?php echo esc_attr( $key['id'] ); ?>"/>
                  <span class="description"><?php echo esc_attr( $key['explanation'] ); ?></span>
                </td>
              </tr>
            <?php } ?>
            <?php if( function_exists( 'sp_ev_author_settings' ) ) sp_ev_author_settings(); ?>
            <tr>
              <th scope="row">
              </th>
              <td class="submit">
                <input type="submit" name="Submit" class="button" value="<?php echo sprintf( __( 'Add New %s Channel', 'external-videos' ), esc_attr( $name ) ); ?>" />
              </td>
            </tr>
          </tbody>
        </table>
      </form>
    </section>

  <?php } ?>

</div><!-- .wrap -->
