=== External Videos ===
Contributors: silviapfeiffer1, johnfjohnf
Donate link: http://www.gingertech.net/
Tags: videos, YouTube, Vimeo, DotSub
Requires at least: 2.9
Tested up to: 3.3.1
Stable Tag: 0.17

This is a WordPress post types plugin for videos posted to external social networking sites.

== Description ==

This plugin creates a new WordPress post type called "External Videos" and aggregates videos from a external social networking site's user channel to the WordPress instance. For example, it finds all the videos of the user "Fred" on YouTube and adds them each as a new post type. The videos can be presented in a gallery using the shortcode [external-videos]. There is also a widget to add a list of the most recent videos in a sidebar.

While some aspects of this plugin do work on wordpress 2.9.2 it is
really designed for 3.0+. When using 2.9 you will miss the following
features:

* Admin Interface to list videos
* Video page per video
* Adding videos as attachments to posts


== Installation ==

To install the External Videos plugin simply:

1. Unpack the downloaded zipped file
2. Upload the "external-videos" folder to your /wp-content/plugins directory
3. Log into Wordpress
4. Go to the "Plugins" page
5. Activate the External Videos Plugin

== Frequently Asked Questions ==

= What sites are supported? =

Currently supported sites are: YouTube, Vimeo, DotSub

= How do I register another user account to draw videos from? =

Go into the admin interface and add the site and user name. For Vimeo you will also have to add a developer key, which you can get from http://vimeo.com/api/docs/getting-started .

= How often does the plugin pull videos from the registered publishers? =

When you register a external video publisher (e.g. a YouTube user), you should hit the button "add new videos from channels" to extract all existing videos. A daily "cron" job then pulls in any newly posted videos from the last 24 hours. If that is not fast enough for you, you can of course always hit that button again.

If you have problems with "cron", consider installing the "Core Control" plugin, which shows you in Tools -> Core Control -> Cron Tasks tab which tasks you have scheduled.

= How do you do the embedding? =

We use OEmbed. For DotSub we use the service of embed.ly.

= What short codes are available? =

The general shortcode is [external-videos], which creates a video gallery.
You can also now specify [external-videos feature="embed"] to get just the latest video as a featured video and with all its embedding code.
You can further specify [external-videos width="300" height="200"] if you want to change the width and the height of the embedded video.
And you can specify [external-videos link="page"] if you want to get the links on the video
gallery to link straight through to the video pages instead of providing an overlay.

= How can I get a RSS feed URL for the external videos? =

Just add the following to your Website URL: ?feed=rss2&post_type=external-videos .
You can add a link like this to your theme layout.
 

== Screenshots ==

1. screenshot-1.png : a list of the video posts in the admin interface
2. screenshot-2.png : the setup page for the plugin
3. screenshot-3.png : the setup page for the widget of recent videos
4. screenshot-4.png : attaching a video from the external videos collection to a post or page
5. screenshot-5.png : selecting a post or page to attach a video to
6. screenshot-6.png : a gallery created by the [external-videos] shortcode; also note the recent videos widget on the right
7. screenshot-7.png : a video page as automatically created by the plugin

== Changelog ==

= 0.17 =
* bug fix on thumbnail option to sidebar widget

= 0.16 =
* added thumbnail option to sidebar widget

= 0.15 =
* fixed styling of "Add Media" dialog in admin section

= 0.14 =
* added bug fix contributed by Chris Jean to query post types
* added prefixes to functions to make conflict with other plugins less likely
* removed rewrite rule from sp_external_videos_init function to allow rewrite URLs

= 0.13 =
* introduced an embedding bug - better fix it quick

= 0.12 =
* fixed a bug in attaching blog posts to videos for link-through from gallery overlays
* allow re-attaching a different blog post to a video

= 0.11 =
* added a shortcode that allows to link straight through to video pages instead of the overlay
* fixed a bug on retrieval of keyframe for dotsub

= 0.10 =
* added option to add the video posts to the site's RSS feed
* fixed a bug on image paths for the thickbox
* made sure whenever a user goes to the admin page that the cron hook is active

= 0.9 =
* some weirdness with commits didn't seem to update to tag 0.8

= 0.8 =
* changed some class names to avoid clashes with other plugins that people reported
* turned simple_html_dom code into a class of its own to avoid clashes with other plugins that use this code, too
* cleaning up entered data from surplus white space
* styling fixes to the overlay on gallery
* shielding against a bug with no videos on channels to retrieve yet

= 0.7 =
* fixed bug on get_category() being called on non-object (can't test it though)
* fixed bug on "attach" to post from external videos list to make it work again
* fixed reports on cron time not working - damned, don't believe the articles on how to use register_activation_hook!
* included a new feature to remove all external videos post types in one go
* fixed up the inclusion of video pages into tag and category management
* now removes videos when a author is being removed
* now deals with deleted videos on external hosts and removes them, too
* fixed bug on "External Videos" tab on "attach video" for posts and pages

= 0.6 =
* extended the shortcode with width, height, and feature parameters

= 0.5 =
* General clean up and reorg

= 0.4 =
* Add Silvia as a proper author so she has commit rights

= 0.3 =
* Fix stupid syntax error in 0.2 release

= 0.2 =
* Add support for wordpress 2.9+

= 0.1 =
* Initial version

