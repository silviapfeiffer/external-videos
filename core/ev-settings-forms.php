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

// make these variables available
$options = SP_External_Videos::admin_get_options();
$AUTHORS = $options['authors'];
$VIDEO_HOSTS = self::$VIDEO_HOSTS;

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

<pre><?php //print_r( $AUTHORS ); ?></pre>

<div class="wrap">

	<h1><?php esc_attr_e( 'External Videos' ); ?></h1>

	<h2 class="nav-tab-wrapper">
		<a class="nav-tab nav-tab-active" href="#" data-div-name="settings-tab">Settings</a>
		<a class="nav-tab" data-div-name="youtube-tab" href="#">YouTube</a>
		<a class="nav-tab" data-div-name="vimeo-tab" href="#">Vimeo</a>
		<a class="nav-tab" data-div-name="dotsub-tab" href="#">DotSub</a>
		<a class="nav-tab" data-div-name="wistia-tab" href="#">Wistia</a>
    <?php /*foreach( $VIDEO_HOSTS as $host->$name ) {
      echo '<a class="nav-tab" data-div-name="' . $host . '-tab" href="#">' . $name . '</a>';
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
									'To connect a video channel, click on one of the tabs at the top.".',
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
            <?php esc_attr_e( 'Click the button below to immediately check all channels for newly added or deleted videos.' ); ?>
          </p>
          <p>
            <?php esc_attr_e( 'The plugin normally updates videos every 24 hours. Next automatic update scheduled for:', 'external-videos' ); ?>
      			<i><?php echo date( 'Y-m-d H:i:s', wp_next_scheduled( 'ev_daily_event' ) ) ?></i>
      		</p>

      		<form id="ev_update_videos" method="post" action="">
            <div class="feedback"></div>
      			<!-- <input type="hidden" name="external_videos" value="Y" /> -->
      			<input type="hidden" name="action" value="ev_update_videos" />
      			<p class="submit">
      				<input type="submit" name="Submit" class="button" value="<?php esc_attr_e( 'Update videos from all channels', 'external-videos' ); ?>" />
      			</p>
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
      			<p class="submit">
      				<input type="submit" name="Submit" class="button" value="<?php esc_attr_e('Move all external videos to trash', 'external-videos'); ?>" />
      			</p>
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
                'To allow WordPress to connect to your account on YouTube, Vimeo, and Wistia, you will have to login to those accounts and create authentication credentials ("API keys").',
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


	<section class="youtube-tab content-tab">

    <div id="poststuff">
      <div class="postbox">
        <div class="inside">
      		<p><?php esc_attr_e( "YouTube's API v3 requires you to generate an API key from your account, in order to access your videos from another site (like this one). ", 'external-videos' ); ?><a title="YouTube API" href="<?php esc_url('https://console.developers.google.com/apis/credentials'); ?>" target="_blank"> YouTube API</a></p>
        </div><!-- .inside -->
      </div><!-- .postbox -->
    </div><!-- .poststuff -->

	  <h3><?php esc_attr_e('Add YouTube Channel', 'external-videos'); ?></h3>
	  <form id="ev_add_youtube" class="ev_add_author" method="post" action="">
	    <!-- <input type="hidden" name="external_videos" value="Y" /> -->
	    <input type="hidden" name="host_id" value="youtube" />
	    <table class="form-table">
	      <tbody>
	        <tr>
	          <th scope="row">
	            <span class="ev-youtube"><?php esc_attr_e( 'Channel Name:', 'external-videos' ); ?></span>
	          </th>
	          <td>
	            <input type="text" name="author_id"/>
	          </td>
	        </tr>
	        <tr>
	          <th scope="row">
	            <span class="ev-youtube"><?php esc_attr_e( 'API Key:', 'external-videos' ); ?></span>
	          </th>
	          <td>
	            <input type="text" name="developer_key"/>
	            <span><?php esc_attr_e( 'Required - this needs to be generated in your API console at YouTube', 'external-videos' ); ?></span>
	          </td>
	        </tr>
	        <tr>
	          <th scope="row">
	            <span class="ev-youtube"><?php esc_attr_e( 'Application Name:', 'external-videos' ); ?></span>
	          </th>
	          <td>
	            <input type="text" name="secret_key"/>
	            <span><?php esc_attr_e( 'Required - this needs to be generated in your API console at YouTube', 'external-videos' ); ?></span>
	          </td>
	        </tr>

					<?php sp_ev_author_settings(); ?>

					<tr>
	          <th scope="row">
	          </th>
	          <td class="submit">
	            <input type="submit" name="Submit" class="button" value="<?php esc_attr_e('Add new YouTube channel', 'external-videos'); ?>" />
							<span class="feedback">
          </td>
	        </tr>
	      </tbody>
	    </table>
	  </form>
	</section>

	<section class="vimeo-tab content-tab">

    <div id="poststuff">
      <div class="postbox">
        <div class="inside">
      		<p><?php esc_attr_e( "Vimeo's API v3.0 requires you to generate an oAuth2 Client Identifier, Client Secret and Personal Access Token from your account, in order to access your videos from another site (like this one). ", 'external-videos' ); ?><a title="Vimeo API" href="<?php esc_url('https://developer.vimeo.com/apps'); ?>" target="_blank"> Vimeo API Apps</a></p>
        </div><!-- .inside -->
      </div><!-- .postbox -->
    </div><!-- .poststuff -->

	  <h3><?php esc_attr_e('Add Vimeo Channel', 'external-videos'); ?></h3>
	  <form id="ev_add_vimeo" class="ev_add_author" method="post" action="">
	    <!-- <input type="hidden" name="external_videos" value="Y" /> -->
	    <input type="hidden" name="host_id" value="vimeo" />
	    <table class="form-table">
	      <tbody>
	        <tr>
	          <th scope="row">
	            <span class="ev-vimeo"><?php esc_attr_e( 'User ID:', 'external-videos' ); ?></span>
	          </th>
	          <td>
	            <input type="text" name="author_id" /><a title="Vimeo Account Settings" href="<?php esc_url('https://vimeo.com/settings/account/general'); ?>" target="_blank"><?php esc_attr_e( 'Vimeo Settings', 'external-videos' ) ?></a>
	          </td>
	        </tr>
	        <tr class="ev-vimeo">
	          <th scope="row">
	            <span class="ev-vimeo"><?php esc_attr_e( 'Client Identifier:', 'external-videos' ); ?></span>
	          </th>
	          <td>
	            <input type="text" name="developer_key" />
	            <span><?php esc_attr_e( 'Required - this needs to be generated in your Vimeo API Apps', 'external-videos' ); ?></span>
	          </td>
	        </tr>
	        <tr class="ev-vimeo">
	          <th scope="row">
	            <span class="ev-vimeo"><?php esc_attr_e( 'Client Secret:', 'external-videos' ); ?></span>
	          </th>
	          <td>
	            <input type="text" name="secret_key" />
	            <span><?php esc_attr_e( 'Required - this needs to be generated in your Vimeo API Apps', 'external-videos' ); ?></span>
	          </td>
	        </tr>
	        <tr class="ev-vimeo">
	          <th scope="row">
	            <?php esc_attr_e( 'Personal Access Token:', 'external-videos' ); ?>
	          </th>
	          <td>
	            <input type="text" name="auth_token" />
	            <span><?php esc_attr_e( 'Optional - this needs to be generated in your Vimeo API Apps. It gives you access to both your public and private videos.', 'external-videos' ); ?></span>
	          </td>
	        </tr>

					<?php sp_ev_author_settings(); ?>

	        <tr>
	          <th scope="row">
	          </th>
	          <td class="submit">
	            <input type="submit" name="Submit" class="button" value="<?php esc_attr_e('Add new Vimeo channel', 'external-videos'); ?>" />
							<span class="feedback">
          </td>
	        </tr>
	      </tbody>
	    </table>
	  </form>
	</section>

	<section class="dotsub-tab content-tab">

    <div id="poststuff">
      <div class="postbox">
        <div class="inside">
      		<p><?php esc_attr_e( "DotSub only requires a User ID in order to access your videos from another site (like this one). ", 'external-videos' ); ?><a title="DotSub" href="<?php esc_url('https://dotsub.com'); ?>" target="_blank"> DotSub</a></p>
        </div><!-- .inside -->
      </div><!-- .postbox -->
    </div><!-- .poststuff -->

	  <h3><?php esc_attr_e('Add DotSub Channel', 'external-videos'); ?></h3>
	  <form id="ev_add_dotsub" class="ev_add_author" method="post" action="">
	    <!-- <input type="hidden" name="external_videos" value="Y" /> -->
	    <input type="hidden" name="host_id" value="dotsub" />
	    <table class="form-table">
	      <tbody>
	        <tr>
	          <th scope="row">
	            <span class="ev-dotsub"><?php esc_attr_e( 'User ID:', 'external-videos' ); ?></span>
	          </th>
	          <td>
	            <input type="text" name="author_id" />
	          </td>
	        </tr>

					<?php sp_ev_author_settings(); ?>

	        <tr>
	          <th scope="row">
	          </th>
	          <td class="submit">
	            <input type="submit" name="Submit" class="button" value="<?php esc_attr_e('Add new DotSub channel', 'external-videos'); ?>" />
							<span class="feedback">
          </td>
	        </tr>
	      </tbody>
	    </table>
	  </form>
	</section>

	<section class="wistia-tab content-tab">

    <div id="poststuff">
      <div class="postbox">
        <div class="inside">
      		<p><?php esc_attr_e( "Wistia's API requires you to generate an API token from your account, in order to access your videos from another site (like this one). ", 'external-videos' ); ?><a title="Wistia" href="<?php esc_url('https://wistia.com'); ?>" target="_blank"> Wistia</a></p>
        </div><!-- .inside -->
      </div><!-- .postbox -->
    </div><!-- .poststuff -->

		<h3><?php esc_attr_e('Add Wistia Channel', 'external-videos'); ?></h3>
		<form id="ev_add_wistia" class="ev_add_author" method="post" action="">
			<!-- <input type="hidden" name="external_videos" value="Y" /> -->
			<input type="hidden" name="host_id" value="wistia" />
			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row">
							<span class="ev-wistia"><?php esc_attr_e( 'Account Name:', 'external-videos' ); ?></span>
						</th>
						<td>
							<input type="text" name="author_id" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<span class="ev-wistia"><?php esc_attr_e( 'API Token:', 'external-videos' ); ?></span>
						</th>
						<td>
							<input type="text" name="developer_key" />
							<span><?php esc_attr_e( 'Required - this needs to be generated in your Wistia account', 'external-videos' ); ?></span>
						</td>
					</tr>

					<?php sp_ev_author_settings(); ?>

					<tr>
						<th scope="row">
						</th>
						<td class="submit">
							<input type="submit" name="Submit" class="button" value="<?php esc_attr_e('Add new Wistia channel', 'external-videos'); ?>" />
							<span class="feedback">
						</td>
					</tr>
				</tbody>
			</table>
		</form>
	</section>


</div><!-- .wrap -->
