# External Videos

Automatically syncs your videos from YouTube, Vimeo, Dotsub, Wistia or Dailymotion to your WordPress site.

# Index
- [Credits](#Credits)
- [Description](#Description)
- [Installation](#Installation)
- [FAQ](#FAQ)
- [Screenshots](#Screenshots)
- [Changelog](#Changelog)


# Credits
- Contributors: silviapfeiffer1, johnfjohnf, nimmolo
- Donate link: http://www.gingertech.net/
- Tags: video, crosspost, sync, YouTube, Vimeo, DotSub, Wistia, Dailymotion
- Requires at least: 4.4
- Tested up to: 4.8
- Stable Tag: 1.1.1
- License: GPLv2
- License URI: http://www.gnu.org/licenses/gpl-2.0.html


# Description

This plugin connects WordPress to your video channels on YouTube, Vimeo, DotSub, Wistia, or Dailymotion, and cross-posts the videos in WordPress automatically. For example, it finds all the videos of the user "Fred" on YouTube and adds them each as a new post on WordPress. The posts are created as a separate post type, "external-videos", but can optionally be added to your posts page or news feed like regular posts. Your videos, once imported, can also be added to any other post via the media library, or presented in a gallery using the shortcode [external-videos]. There is also a widget to add a list of the most recent videos in a sidebar.


# Installation

To install the External Videos plugin simply:

1. Unpack the downloaded zipped file
2. Upload the "external-videos" folder to your /wp-content/plugins directory
3. Log into Wordpress
4. Go to the "Plugins" page
5. Activate the External Videos Plugin



# FAQ

### What sites are supported?

Currently supported sites are: YouTube, Vimeo, Dotsub, Wistia, Dailymotion

### How do I register another user account to draw videos from?

Go into Settings->External Videos and click on the tab of your video hosting service. Enter your user name for that service. In some cases, this is all you need.

For other hosts, you will have to login to your account and create API keys in order to access your videos. The plugin contains links to do this.

### How often does the plugin pull videos from the registered publishers?

When you register a external video publisher (e.g. a YouTube user), you should hit the button "add new videos from channels" to extract all existing videos. A daily "cron" job then pulls in any newly posted videos from the last 24 hours. If that is not fast enough for you, you can of course always hit that button again.

If you have problems with "cron", consider installing the "Core Control" plugin, which shows you in Tools -> Core Control -> Cron Tasks tab which tasks you have scheduled.

### How do you do the embedding?

We use OEmbed.

### What shortcodes are available?

The general shortcode is `[external-videos]`, which creates a video gallery.

You can also now specify `[external-videos feature="embed"]` to get just the latest video as a featured video and with all its embedding code.

You can further specify `[external-videos width="300" height="200"]` if you want to change the width and the height of the embedded video.

And you can specify `[external-videos link="page"]` if you want to get the links on the video gallery to link straight through to the video pages instead of providing an overlay.

### How can I get a RSS feed URL for the external videos?

Just add the following to your Website URL: ?feed=rss2&post_type=external-videos .
You can add a link like this to your theme layout.

### How can I get external videos to appear in the Home page ("page for posts")?

In Settings->External Videos, click the checkbox for this.

### How can I make it so imported videos are not automatically published on my site?

In the settings for each hosting account, you can choose whether to set default post status to "Published", "Scheduled", "Draft", "Pending", or "Private".

### Can I change the permalink slug for external-videos posts?

Yes, you can pick any slug you like in Settings->External Videos, as long as it doesn't conflict with other WordPress slugs.


# Screenshots

1. List of the video posts in the admin interface
2. Setup page for the plugin
3. Setup page for a video host
4. Setup page for the widget of recent videos
5. Gallery created by the [external-videos] shortcode; also note recent videos widget on the right
6. Video page (archive) as automatically created by the plugin
7. Media Uploader: inserting External Videos into any page or post


# Changelog

### 1.1
* Media Uploader: External Videos can now be added to any post, like photos or other media items, once they are imported. (Functions rewritten using Media Explorer framework)
* Updates to video host functions to check for API keys if necessary
* Bug and code efficiency fixes

### 1.0
* Major refactor - thanks to Andrew Nimmo
* All video sites updated to their current APIs
* YouTube - Vimeo - Dotsub - Wistia updated
* Dailymotion added
* Settings: Added AJAX admin forms for live plugin settings and video updates
* New settings option: Add external-videos to main Home page query (latest posts)
* New settings option: Automatically append attribution (categories, author and hosting site) to external-video post content
* New admin column to sort external-videos by category
* Note that some time since WP 3.5, you can no longer access connected external-videos from the Media Uploader, to add them to a post or page (other than the original external-videos post). This is because of hooks inaccessible since WP Media changed to backbone.js; this part of the plugin's functionality could be integrated with the Media Explorer plugin project.

### 0.27
* fixed use of set_include_path to be non-destructive

### 0.26
* Fixed some php errors and warnings
* Notice that DotSub is broken - it changed page layout
* Merged pull request from nimmolo/clear-admin-column-php-error

### 0.25
* Updated to YouTube API v3
* Fixed some breakage in external video embedding

### 0.24
* Created setting to remove/keep posts when removed on remote site as per patch by Dan Baritchi
* Instead of removing posts, they now end up in the trash
* Added post_excerpt storage, used by some sites

### 0.23
* Added wistia site support with patches from Dan Baritchi
* Added embed_code meta field for other plugins
* Update todo file

### 0.22
* Forgot to increase version number in external-videos.php file

### 0.21
* Enable choosing author for external video posts
* Enable setting post status for external video posts
* Enable setting post format for external video posts
* Enable choosing video categories for external video posts
* Don't remove manually created external-video pages

### 0.20
* applied patches from nowotny https://github.com/nowotny/external-videos/
* fixed styling of "Add Media" dialog in admin section again
* added localization support
* added Polish translation file
* added English translation file

### 0.19
* rename VimeoAPIException to spEvVimeoAPIException to avoid clash with other plugins

### 0.18
* checked support for WP 3.6.1

### 0.17
* bug fix on thumbnail option to sidebar widget

### 0.16
* added thumbnail option to sidebar widget

### 0.15
* fixed styling of "Add Media" dialog in admin section

### 0.14
* added bug fix contributed by Chris Jean to query post types
* added prefixes to functions to make conflict with other plugins less likely
* removed rewrite rule from sp_external_videos_init function to allow rewrite URLs

### 0.13
* introduced an embedding bug - better fix it quick

### 0.12
* fixed a bug in attaching blog posts to videos for link-through from gallery overlays
* allow re-attaching a different blog post to a video

### 0.11
* added a shortcode that allows to link straight through to video pages instead of the overlay
* fixed a bug on retrieval of keyframe for dotsub

### 0.10
* added option to add the video posts to the site's RSS feed
* fixed a bug on image paths for the thickbox
* made sure whenever a user goes to the admin page that the cron hook is active

### 0.9
* some weirdness with commits didn't seem to update to tag 0.8

### 0.8
* changed some class names to avoid clashes with other plugins that people reported
* turned simple_html_dom code into a class of its own to avoid clashes with other plugins that use this code, too
* cleaning up entered data from surplus white space
* styling fixes to the overlay on gallery
* shielding against a bug with no videos on channels to retrieve yet

### 0.7
* fixed bug on get_category() being called on non-object (can't test it though)
* fixed bug on "attach" to post from external videos list to make it work again
* fixed reports on cron time not working - damned, don't believe the articles on how to use register_activation_hook!
* included a new feature to remove all external videos post types in one go
* fixed up the inclusion of video pages into tag and category management
* now removes videos when a author is being removed
* now deals with deleted videos on external hosts and removes them, too
* fixed bug on "External Videos" tab on "attach video" for posts and pages

### 0.6
* extended the shortcode with width, height, and feature parameters

### 0.5
* General clean up and reorg

### 0.4
* Add Silvia as a proper author so she has commit rights

### 0.3
* Fix stupid syntax error in 0.2 release

### 0.2
* Add support for wordpress 2.9+

### 0.1
* Initial version


#
