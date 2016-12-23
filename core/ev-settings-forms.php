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
$VIDEO_HOSTS = SP_External_Videos::$VIDEO_HOSTS;

// some re-usable settings:
function sp_ev_author_settings(){ ?>
	<tr>
		<th scope="row">
			<?php _e('Default WP User', 'external-videos'); ?>
		</th>
		<td>
			<?php wp_dropdown_users(); ?>
		</td>
	</tr>
	<tr>
		<th scope="row">
			<?php _e('Default Post Format'); ?>
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
			<?php _e('Default Post Category'); ?>
		</th>
		<td>
			<?php $selected_cats = array( get_cat_ID('External Videos', 'external-videos') ); ?>
			<ul style="">
				<?php wp_category_checklist(0, 0, $selected_cats, false, null, true); ?>
			</ul>
		</td>
	</tr>
	<tr>
		<th scope="row">
			<?php _e('Set Post Status', 'external-videos'); ?>
		</th>
		<td>
			<select name='post_status' id='ev_post_status'>
				<option value='publish' selected><?php _e('Published') ?></option>
				<option value='pending'><?php _e('Pending Review') ?></option>
				<option value='draft'><?php _e('Draft') ?></option>
				<option value='private'><?php _e('Privately Published') ?></option>
				<option value='future'><?php _e('Scheduled') ?></option>
			</select>
		</td>
	</tr>

	<?php
}

?>

<pre><?php //print_r( $AUTHORS ); ?></pre>

<style type="text/css">
	section {
		display:none;
	}
	section:first-of-type {
		display:block;
	}
	.no-js h2.nav-tab-wrapper {
		display:none;
	}
	.no-js section {
		border-top: 1px dashed #aaa;
		margin-top:22px;
		padding-top:22px;
	}
	.no-js section:first-child {
		margin:0px;
		padding:0px;
		border:0px;
	}
	.ml-3 {
		margin-left: 3em !important;
	}
	.limited-width {
		max-width: 45rem;
	}
	.float-right {
		float: right;
	}
	.v-top {
		vertical-align: top;
	}
</style>



<div class="wrap">

	<h1><?php _e( 'External Videos' ); ?></h1>

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

		<p>External Videos connects to your accounts at video hosting sites, fetches uploaded videos to your WordPress site, and creates a new "External Videos" post for each video ("automatic cross-posting"). </p>

		<p>For example, EV can find all the videos of the user "Fred" on YouTube and add each of them as a new "external-videos" post. The plugin automatically checks once per day for new videos, and multiple video accounts are supported. Note that some hosts require strict authentication credentials known only to the account owner.</p>

		<hr />

		<h3><?php _e('Plugin Settings', 'external-videos'); ?></h3>
		<form id="ev_plugin_settings" method="post" action="">
			<!-- <input type="hidden" name="external_videos" value="Y" /> -->
			<input type="hidden" name="action" value="ev_plugin_settings" />

			<?php // get saved options
				$ev_rss = $options['rss'];
				$ev_del = $options['delete'];
				$ev_attrib = $options['attrib'];
			?>
			<p>
				<input type="checkbox" name="ev-rss" value="rss" <?php if ($ev_rss == true) echo "checked"; ?>/>
				<?php _e('Add video posts to Website RSS feed', 'external-videos'); ?>
			</p>
			<p>
				<input type="checkbox" name="ev-delete" value="delete" <?php if ($ev_del == true) echo "checked"; ?>/>
				<?php _e('Move videos locally to trash when deleted on external site', 'external-videos'); ?>
			</p>
			<p>
				<input type="checkbox" name="ev-attrib" value="attrib" <?php if ($ev_attrib == true) echo "checked"; ?>/>
				<?php _e('Add category, author and hosting site links to bottom of video post content', 'external-videos'); ?>
			</p>
			<p>
				<input type="submit" name="Submit" class="button button-primary" value="<?php _e('Save Settings'); ?>" /><strong class="feedback ml-3"></strong>
			</p>
	  </form>

		<!-- end of Plugin Settings -->
    <hr />


		<h3><?php _e( 'Update Videos (newly added/deleted videos)', 'external-videos' ); ?></h3>
    <p>
      <?php _e( 'Videos are automatically updated every 24 hours. Next automatic update scheduled for:', 'external-videos' ); ?>
			<i><?php echo date( 'Y-m-d H:i:s', wp_next_scheduled( 'ev_daily_event' ) ) ?></i>
		</p>

		<form id="ev_update_videos" method="post" action="">
			<!-- <input type="hidden" name="external_videos" value="Y" /> -->
			<input type="hidden" name="action" value="ev_update_videos" />
			<p>
				<input type="submit" name="Submit" class="button" value="<?php _e( 'Update Videos from All Channels', 'external-videos' ); ?>" /><strong class="feedback ml-3"></strong>
			</p>
		</form>


		<!-- end of Update Videos -->
    <hr />


		<h3><?php _e('Delete All Videos', 'external-videos'); ?></h3>
		<p>
			<?php _e('Be careful with this option - you will lose all links you have built between blog posts and the video pages. This is really only meant as a reset option.', 'external-videos'); ?>
		</p>

		<form id="ev_delete_all" method="post" action="">
			<!-- <input type="hidden" name="external_videos" value="Y" /> -->
			<input type="hidden" name="action" value="ev_delete_videos" />
			<p>
				<input type="submit" name="Submit" class="button" value="<?php _e('Remove External Videos from All Channels', 'external-videos'); ?>" /><strong class="feedback ml-3"></strong>
			</p>
	  </form>

		<!-- end of Delete All Videos -->
    <hr />


		<h3><?php _e('Connected Accounts', 'external-videos'); ?></h3>

		<form id="ev_author_list" method="post" action="">
			<!-- <input type="hidden" name="external_videos" value="Y" /> -->
			<!-- <input type="hidden" name="action" value="delete_author" /> -->

			<table class="wp-list-table widefat limited-width">
				<thead>
					<tr class="">
						<th scope="col" class="column-title column-primary desc"><?php _e( 'Host', 'external-videos' ); ?></th>
						<th scope="col" class="column-title column-primary desc"><?php _e( 'Channel ID', 'external-videos' ); ?></th>
					</tr>
				</thead>

				<tbody>
					<?php // Keeping the channels organized by host
					foreach ( $VIDEO_HOSTS as $host => $hostname ) {

						$host_authors = array();

						// if we have a channel on this host, build a row
						if( array_search( $host, array_column( $AUTHORS, 'host_id') ) !== false) {

							// channels we want are the ones with this $host in the 'host_id'
							$host_authors = array_filter( $AUTHORS, function( $author ) use ( $host ) {
								return $author['host_id'] == $host;
							} ); ?>

							<tr>
								<th scope="row" class="v-top">
									<?php echo $hostname; ?>
								</th>
								<td>
									<?php foreach( $host_authors as $author ) {  ?>
										<p class="v-top" id="<?php echo $author['host_id'] . '-' . $author['author_id']; ?>">
											<span style="margin-right: 2em;"><?php echo $author['author_id']; ?></span>
											<input type="submit" class="button-delete button float-right ml-3" value="<?php _e( 'Delete' ); ?>" data-host="<?php echo $author['host_id']; ?>" data-author="<?php echo $author['author_id']; ?>" />
                      <input type="submit" class="button-update button float-right ml-3" value="<?php _e( 'Update Videos' ); ?>" data-host="<?php echo $author['host_id']; ?>" data-author="<?php echo $author['author_id']; ?>" />
										</p>
                    <hr />
									<?php } ?>
								</td>
							</tr>

						<?php } ?>

					<?php } ?>
					<tr>
						<th colspan=2 class="feedback"></th>
					</tr>
				</tbody>
	    </table>
			<!-- <input type="hidden" name="host_id" />
			<input type="hidden" name="author_id" /> -->
		</form>

		<!-- end of Connected Accounts -->

	</section>


	<section class="youtube-tab content-tab">

		<p><?php _e( "YouTube's API v3 requires you to generate an API key from your account, in order to access your videos from another site (like this one).", 'external-videos' ); ?><a class="button" title="YouTube API" href="<?php esc_url('https://console.developers.google.com/apis/credentials'); ?>" target="_blank">YouTube API</a></p>

	  <h3><?php _e('Add YouTube Channel', 'external-videos'); ?></h3>
	  <form id="ev_add_youtube" class="ev_add_author" method="post" action="">
	    <!-- <input type="hidden" name="external_videos" value="Y" /> -->
	    <input type="hidden" name="host_id" value="youtube" />
	    <table class="form-table">
	      <tbody>
	        <tr>
	          <th scope="row">
	            <span class="ev-youtube"><?php _e( 'Channel Name:', 'external-videos' ); ?></span>
	          </th>
	          <td>
	            <input type="text" name="author_id"/>
	          </td>
	        </tr>
	        <tr>
	          <th scope="row">
	            <span class="ev-youtube"><?php _e( 'API Key:', 'external-videos' ); ?></span>
	          </th>
	          <td>
	            <input type="text" name="developer_key"/>
	            <span><?php _e( 'Required - this needs to be generated in your API console at YouTube', 'external-videos' ); ?></span>
	          </td>
	        </tr>
	        <tr>
	          <th scope="row">
	            <span class="ev-youtube"><?php _e( 'Application Name:', 'external-videos' ); ?></span>
	          </th>
	          <td>
	            <input type="text" name="secret_key"/>
	            <span><?php _e( 'Required - this needs to be generated in your API console at YouTube', 'external-videos' ); ?></span>
	          </td>
	        </tr>

					<?php sp_ev_author_settings(); ?>

					<tr>
	          <th scope="row">
	          </th>
	          <td class="submit">
	            <input type="submit" name="Submit" class="button" value="<?php _e('Add new YouTube channel', 'external-videos'); ?>" />
							<span class="feedback">
          </td>
	        </tr>
	      </tbody>
	    </table>
	  </form>
	</section>

	<section class="vimeo-tab content-tab">

		<p><?php _e( "Vimeo's API v3.0 requires you to generate an oAuth2 Client Identifier, Client Secret and Personal Access Token from your account, in order to access your videos from another site (like this one).", 'external-videos' ); ?><a class="button" title="Vimeo API" href="<?php esc_url('https://developer.vimeo.com/apps'); ?>" target="_blank">Vimeo API Apps</a></p>

	  <h3><?php _e('Add Vimeo Channel', 'external-videos'); ?></h3>
	  <form id="ev_add_vimeo" class="ev_add_author" method="post" action="">
	    <!-- <input type="hidden" name="external_videos" value="Y" /> -->
	    <input type="hidden" name="host_id" value="vimeo" />
	    <table class="form-table">
	      <tbody>
	        <tr>
	          <th scope="row">
	            <span class="ev-vimeo"><?php _e( 'User ID:', 'external-videos' ); ?></span>
	          </th>
	          <td>
	            <input type="text" name="author_id" /><a class="button" title="Vimeo Account Settings" href="<?php esc_url('https://vimeo.com/settings/account/general'); ?>" target="_blank"><?php _e( 'Vimeo Settings', 'external-videos' ) ?></a>
	          </td>
	        </tr>
	        <tr class="ev-vimeo">
	          <th scope="row">
	            <span class="ev-vimeo"><?php _e( 'Client Identifier:', 'external-videos' ); ?></span>
	          </th>
	          <td>
	            <input type="text" name="developer_key" />
	            <span><?php _e( 'Required - this needs to be generated in your Vimeo API Apps', 'external-videos' ); ?></span>
	          </td>
	        </tr>
	        <tr class="ev-vimeo">
	          <th scope="row">
	            <span class="ev-vimeo"><?php _e( 'Client Secret:', 'external-videos' ); ?></span>
	          </th>
	          <td>
	            <input type="text" name="secret_key" />
	            <span><?php _e( 'Required - this needs to be generated in your Vimeo API Apps', 'external-videos' ); ?></span>
	          </td>
	        </tr>
	        <tr class="ev-vimeo">
	          <th scope="row">
	            <?php _e( 'Personal Access Token:', 'external-videos' ); ?>
	          </th>
	          <td>
	            <input type="text" name="auth_token" />
	            <span><?php _e( 'Optional - this needs to be generated in your Vimeo API Apps. It gives you access to both your public and private videos.', 'external-videos' ); ?></span>
	          </td>
	        </tr>

					<?php sp_ev_author_settings(); ?>

	        <tr>
	          <th scope="row">
	          </th>
	          <td class="submit">
	            <input type="submit" name="Submit" class="button" value="<?php _e('Add new Vimeo channel', 'external-videos'); ?>" />
							<span class="feedback">
          </td>
	        </tr>
	      </tbody>
	    </table>
	  </form>
	</section>

	<section class="dotsub-tab content-tab">

		<p><?php _e( "DotSub only requires a User ID, in order to access your videos from another site (like this one).", 'external-videos' ); ?><a class="button" title="DotSub" href="<?php esc_url('https://dotsub.com'); ?>" target="_blank">DotSub</a></p>

	  <h3><?php _e('Add DotSub Channel', 'external-videos'); ?></h3>
	  <form id="ev_add_dotsub" class="ev_add_author" method="post" action="">
	    <!-- <input type="hidden" name="external_videos" value="Y" /> -->
	    <input type="hidden" name="host_id" value="dotsub" />
	    <table class="form-table">
	      <tbody>
	        <tr>
	          <th scope="row">
	            <span class="ev-dotsub"><?php _e( 'User ID:', 'external-videos' ); ?></span>
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
	            <input type="submit" name="Submit" class="button" value="<?php _e('Add new DotSub channel', 'external-videos'); ?>" />
							<span class="feedback">
          </td>
	        </tr>
	      </tbody>
	    </table>
	  </form>
	</section>

	<section class="wistia-tab content-tab">

		<p><?php _e( "Wistia's API requires you to generate an API token from your account, in order to access your videos from another site (like this one).", 'external-videos' ); ?></p>

		<h3><?php _e('Add Wistia Channel', 'external-videos'); ?></h3>
		<form id="ev_add_wistia" class="ev_add_author" method="post" action="">
			<!-- <input type="hidden" name="external_videos" value="Y" /> -->
			<input type="hidden" name="host_id" value="wistia" />
			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row">
							<span class="ev-wistia"><?php _e( 'Account Name:', 'external-videos' ); ?></span>
						</th>
						<td>
							<input type="text" name="author_id" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<span class="ev-wistia"><?php _e( 'API Token:', 'external-videos' ); ?></span>
						</th>
						<td>
							<input type="text" name="developer_key" />
							<span><?php _e( 'Required - this needs to be generated in your Wistia account', 'external-videos' ); ?></span>
						</td>
					</tr>

					<?php sp_ev_author_settings(); ?>

					<tr>
						<th scope="row">
						</th>
						<td class="submit">
							<input type="submit" name="Submit" class="button" value="<?php _e('Add new Wistia channel', 'external-videos'); ?>" />
							<span class="feedback">
						</td>
					</tr>
				</tbody>
			</table>
		</form>
	</section>


</div><!-- .wrap -->
