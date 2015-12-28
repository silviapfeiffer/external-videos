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
 * Functions related to setting up a gallery with the videos.
 * All related to the admin interface.
 */


add_filter('media_upload_tabs', 'sp_ev_add_media_upload_tab');
function sp_ev_add_media_upload_tab($tabs) {

    $tabs['external_videos'] = __('External Videos', 'external-videos');

    return $tabs;
}


add_action('media_upload_external_videos', 'sp_ev_media_upload_external_videos');
// see media.php media_upload_library() to update
function sp_ev_media_upload_external_videos() {
    $errors = array();
    if ( !empty($_POST) ) {
        $return = media_upload_form_handler();

        if ( is_string($return) )
            return $return;
        if ( is_array($return) )
            $errors = $return;
    }

    return wp_iframe('sp_ev_media_upload_external_videos_form', $errors);
}


// see media.php media_upload_library_form() to update
function sp_ev_media_upload_external_videos_form($errors) {
    global $wpdb, $wp_query, $wp_locale, $type, $tab, $post_mime_types;

    media_upload_header();
    wp_enqueue_style( 'media' );

    $post_id = intval($_REQUEST['post_id']);

    $form_action_url = admin_url("media-upload.php?type=$type&tab=external_videos&post_id=$post_id");
    $form_action_url = apply_filters('media_upload_form_url', $form_action_url, $type);
    $form_class = 'media-upload-form validate';

    $_GET['paged'] = isset( $_GET['paged'] ) ? intval($_GET['paged']) : 0;
    if ( $_GET['paged'] < 1 )
        $_GET['paged'] = 1;
    $start = ( $_GET['paged'] - 1 ) * 10;
    if ( $start < 1 )
        $start = 0;
    add_filter( 'post_limits', create_function( '$a', "return 'LIMIT $start, 10';" ) );

    list($post_mime_types, $avail_post_mime_types) = wp_edit_external_videos_query();

?>

<form id="filter" action="" method="get">
<input type="hidden" name="type" value="<?php echo esc_attr( $type ); ?>" />
<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>" />
<input type="hidden" name="post_id" value="<?php echo (int) $post_id; ?>" />
<input type="hidden" name="post_mime_type" value="<?php echo isset( $_GET['post_mime_type'] ) ? esc_attr( $_GET['post_mime_type'] ) : ''; ?>" />

<p id="media-search" class="search-box">
    <label class="screen-reader-text" for="media-search-input"><?php _e('Search Media');?>:</label>
    <input type="text" id="media-search-input" name="s" value="<?php the_search_query(); ?>" />
    <input type="submit" value="<?php esc_attr_e( 'Search Media' ); ?>" class="button" />
</p>

<div class="tablenav">

<?php
$page_links = paginate_links( array(
    'base' => add_query_arg( 'paged', '%#%' ),
    'format' => '',
    'prev_text' => __('&laquo;'),
    'next_text' => __('&raquo;'),
    'total' => ceil($wp_query->found_posts / 10),
    'current' => $_GET['paged']
));

if ( $page_links )
    echo "<div class='tablenav-pages'>$page_links</div>";
?>

<div class="alignleft actions">
<?php

$arc_query = "SELECT DISTINCT YEAR(post_date) AS yyear, MONTH(post_date) AS mmonth FROM $wpdb->posts WHERE post_type = 'external-videos' ORDER BY post_date DESC";

$arc_result = $wpdb->get_results( $arc_query );

$month_count = count($arc_result);

if ( $month_count && !( 1 == $month_count && 0 == $arc_result[0]->mmonth ) ) { ?>
<select name='m'>
<option<?php selected( @$_GET['m'], 0 ); ?> value='0'><?php _e('Show all dates'); ?></option>
<?php
foreach ($arc_result as $arc_row) {
    if ( $arc_row->yyear == 0 )
        continue;
    $arc_row->mmonth = zeroise( $arc_row->mmonth, 2 );

    if ( isset($_GET['m']) && ( $arc_row->yyear . $arc_row->mmonth == $_GET['m'] ) )
        $default = ' selected="selected"';
    else
        $default = '';

    echo "<option$default value='" . esc_attr( $arc_row->yyear . $arc_row->mmonth ) . "'>";
    echo esc_html( $wp_locale->get_month($arc_row->mmonth) . " $arc_row->yyear" );
    echo "</option>\n";
}
?>
</select>
<?php } ?>

<?php submit_button( __( 'Filter &#187;' ), 'button', 'post-query-submit', false ); ?>

</div>

<br class="clear" />
</div>
</form>

<form enctype="multipart/form-data" method="post" action="<?php echo esc_attr($form_action_url); ?>" class="media-upload-form" id="library-form">

<?php wp_nonce_field('media-form'); ?>
<?php //media_upload_form( $errors ); ?>

<script type="text/javascript">
<!--
jQuery(function($){
    var preloaded = $(".media-item.preloaded");
    if ( preloaded.length > 0 ) {
        preloaded.each(function(){
                    console.log(this.id);

            prepareMediaItem({id:this.id.replace(/[^0-9]/g, '')},'');
        });
        updateMediaForm();
    }
});

-->
</script>

<div id="media-items">
<?php add_filter('attachment_fields_to_edit', 'media_post_single_attachment_fields_to_edit', 10, 2); ?>
<?php echo get_media_items(null, $errors); ?>
</div>
</form>
<?php
}


function wp_edit_external_videos_query( $q = false ) {
    if ( false === $q )
        $q = $_GET;

    $q['m']   = isset( $q['m'] ) ? (int) $q['m'] : 0;
    $q['cat'] = isset( $q['cat'] ) ? (int) $q['cat'] : 0;
    $q['post_type'] = 'external-videos';
    $media_per_page = (int) get_user_option( 'upload_per_page' );
    if ( empty( $media_per_page ) || $media_per_page < 1 )
        $media_per_page = 20;
    $q['posts_per_page'] = apply_filters( 'upload_per_page', $media_per_page );

    $post_mime_types = get_post_mime_types();
    $avail_post_mime_types = get_available_post_mime_types('attachment');

    if ( isset($q['post_mime_type']) && !array_intersect( (array) $q['post_mime_type'], array_keys($post_mime_types) ) )
        unset($q['post_mime_type']);

    wp($q);

    return array($post_mime_types, $avail_post_mime_types);
}


add_filter('image_downsize', 'external_videos_image_downsize', 10, 3);
function external_videos_image_downsize($var, $id, $size) {

    // TODO provide a different thumbnail based on $size

    $id = (int) $id;
    if ( !$video = get_post( $id ) )
        return false;

    if ( $video->post_type != 'external-videos' )
        return false;

    $thumbnail = get_post_meta($id, 'thumbnail_url');
    $thumb = $thumbnail[0];

    return array( $thumb, 120, 90, false );
}


add_filter('media_send_to_editor', 'external_videos_media_send_to_editor', 10, 3);
function external_videos_media_send_to_editor($html, $attachment_id, $attachment) {
    $post =& get_post($attachment_id);
    if ( $post->post_type == 'external-videos' ) {
        $html = get_post_meta($attachment_id, 'video_url');
        $html = $html[0];
    }

    return $html;
}


// This is a bit of a hack so we can add some js script we need on the external videos page
add_action('admin_init', 'add_media_select');
function add_media_select() {
    if (preg_match('/wp-admin\/edit\.php\?.*post_type=external-videos/', $_SERVER["REQUEST_URI"])) {
        wp_enqueue_script('media');
        wp_enqueue_script('wp-ajax-response');
    }
}


add_action('restrict_manage_posts', 'add_find_posts_div');
function add_find_posts_div() {
    if (preg_match('/wp-admin\/edit\.php\?.*post_type=external-videos/', $_SERVER["REQUEST_URI"])) {
        print find_posts_div();
    }
}


add_filter('edit_posts_per_page', 'do_attach');
function do_attach($per_page) {
    global $wpdb;

    if ( isset($_GET['attached']) && (int) $_GET['attached'] ) {
        $attached = (int) $_GET['attached'];
        $message = sprintf( _n('Changed %d attachment.', 'Attached %d attachments.', $attached, 'external-videos'), $attached );
        $_SERVER['REQUEST_URI'] = remove_query_arg(array('attached'), $_SERVER['REQUEST_URI']);
        ?>
        <div id="message" class="updated"><p><strong><?php echo $message; ?></strong></p></div>
        <?php
        unset($_GET['attached']);
}
    elseif ( isset($_GET['found_post_id']) && isset($_GET['media'])  ) {
        if ( ! ( $parent_id = (int) $_GET['found_post_id'] ) )
            return;

        $parent = &get_post($parent_id);
        if ( !current_user_can('edit_post', $parent_id) )
            wp_die( __('You are not allowed to edit this video.', 'external-videos') );

        $attach = array();
        foreach( (array) $_GET['media'] as $att_id ) {
            $att_id = (int) $att_id;

            if ( !current_user_can('edit_post', $att_id) )
                continue;

            $attach[] = $att_id;
            clean_attachment_cache($att_id);
        }

        if ( ! empty($attach) ) {
            $attach = implode(',', $attach);
            $attached = $wpdb->query( $wpdb->prepare("UPDATE $wpdb->posts SET post_parent = %d WHERE post_type = 'external-videos' AND ID IN ($attach)", $parent_id) );
        }

        if ( isset($attached) ) {
            $location = 'edit.php';
            if ( $referer = wp_get_referer() ) {
                if ( false !== strpos($referer, 'edit.php') )
                    $location = $referer;
            }

            $location = add_query_arg( array( 'attached' => $attached ) , $location );
            wp_redirect($location);
            exit;
        }

    }
    return $per_page;
}

?>
