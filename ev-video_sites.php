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

/// ***   Pulling Videos From Diverse Sites   *** ///


function sp_ev_fetch_youtube_videos($author)
{
    $author_id = $author['author_id'];

    // setup YouTube API access
    $client = new Google_Client();
    $client->setDeveloperKey($author['developer_key']);
    $client->setApplicationName($author['secret_key']);

    // get channel ID for author
    $service = new Google_Service_YouTube($client);
    $results = $service->channels->listChannels('contentDetails',
                array('forUsername' => $author_id ));
    $channel_id = $results->items[0]->contentDetails->relatedPlaylists->uploads;

    // get first lot of 50 videos



    $date = date(DATE_RSS);
    $new_videos = array();

    // loop through all feed pages
    $per_page = 50;
    $pageToken = '';
    do {
      // fetch videos
      $videofeed = $service->playlistItems->listPlaylistItems('snippet',
                    array('playlistId' => $channel_id,
                          'maxResults' => $per_page,
                          'pageToken' => $pageToken,
                          )
                    );

      foreach ($videofeed->items as $vid)
      {
        // extract fields
        $video = array();
        $video['host_id']     = 'youtube';
        $video['author_id']   = strtolower($author_id);
        $video['video_id']    = $vid->id;
        $video['title']       = $vid->snippet->title;
        $video['description'] = $vid->snippet->description;
        $video['authorname']  = $vid->snippet->channelTitle;
        $video['videourl']    = 'https://www.youtube.com/watch?v='.$vid->snippet->resourceId->videoId;
        $video['published']   = date("Y-m-d H:i:s", strtotime($vid->snippet->publishedAt));
        $video['author_url']  = "http://www.youtube.com/user/".$video['author_id'];
        $video['category']    = '';
        $video['keywords']    = array();
        $video['thumbnail']   = $vid->snippet->thumbnails->default->url;
        $video['duration']    = '';
        $video['ev_author']   = $author['ev_author'];
        $video['ev_category'] = $author['ev_category'];
        $video['ev_post_format'] = $author['ev_post_format'];
        $video['ev_post_status'] = $author['ev_post_status'];

        // add $video to the end of $new_videos
        array_push($new_videos, $video);

      }
      // next page
      $pageToken = $videofeed->nextPageToken;
    } while ($pageToken);

    return $new_videos;
} // end sp_ev_fetch_youtube_videos()

/*
 * NEW VIMEO API 3.0
 */

function sp_ev_fetch_vimeo_videos($author)
{
  $author_id = $author['author_id'];
  $developer_key = $author['developer_key'];
  $secret_key = $author['secret_key'];
  $access_token = $author['auth_token'];

  // Limit this sucker. New API returns hella fields
  $fields_desired = array(
                  'uri',
                  'name',
                  'link',
                  'description',
                  'duration',
                  'pictures',
                  'user.name',
                  'user.link',
                  'privacy',
                  'tags',
                  'release_time',
                  );
  $fields_desired = implode(',', $fields_desired);

  $vimeo = new \Vimeo\Vimeo($developer_key, $secret_key, $access_token);
  $per_page = 50;
  $date = date(DATE_RSS);
  $new_videos = array();

  // loop through all feed pages
  $page = 1;
  do {
    // Do an authenticated call
    try {
      $videofeed = $vimeo->request('/users/'.$author_id.'/videos',
                                    array(//'fields' => $fields_desired,
                                          // 'privacy.view' => 'anybody',
                                          'sort' => 'date',
                                          'filter' => 'embeddable',
                                          'filter_embeddable' => 'true',
                                          'page' => $page,
                                          'per_page' => $per_page),
                                    'GET',
                                    true,
                                    $fields_desired); //nimmo hack for lame Vimeo library inability to process nested arrays
    }
    catch (\Vimeo\Exceptions\VimeoRequestException $e) {
      echo "Encountered an API error -- code {$e->getCode()} - {$e->getMessage()}";
    }
    // Test this mofo
    // echo '<pre>$videofeed full: <br />'; print_r(($videofeed['body'])); echo '</pre>';
    // echo '<pre>'; print_r($videofeed['body']['data'])); echo '</pre>';

    // Adjust array to get to the data
    $pagefeed = $videofeed['body']['data'];
    // how many we got?
    $count = count($pagefeed);
    // echo '<pre>$pagefeed: '.$count.'<br />'; print_r($pagefeed); echo '</pre>';

    foreach ($pagefeed as $vid)
    {
      // extract fields
      $video = array();
      $video['host_id']     = 'vimeo';
      $video['author_id']   = strtolower($author_id);
      $video['video_id']    = $vid['uri'];
      $video['title']       = $vid['name'];
      $video['description'] = $vid['description'];
      $video['authorname']  = $vid['user']['name'];
      $video['videourl']    = $vid['link'];
      $video['published']   = $vid['release_time'];
      $video['author_url']  = $vid['user']['link'];
      $video['category']    = '';
      $video['keywords']    = array();
      if ($vid['tags']) {
        foreach ($vid['tags'] as $tag) {
          array_push($video['keywords'], $tag['tag']);
        }
      }
      $video['thumbnail']   = $vid['pictures']['sizes'][2]['link'];
      $video['duration']    = sp_ev_sec2hms($vid['duration']);
      $video['ev_author']   = $author['ev_author'];
      $video['ev_category'] = $author['ev_category'];
      $video['ev_post_format'] = $author['ev_post_format'];
      $video['ev_post_status'] = $author['ev_post_status'];

      // add $video to the end of $new_videos
      array_push($new_videos, $video);
    }

    // next page
    $page += 1;
  } while ($videofeed['body']['paging']['next']);

  // echo '<pre>sp_ev_fetch_vimeo_videos: <br />'; print_r($new_videos); echo '</pre>';
  return $new_videos;
} // end sp_ev_fetch_vimeo_videos()


function sp_ev_fetch_dotsub_videos($author)
{
    $author_id = $author['author_id'];

    $url = "http://dotsub.com/view/user/$author_id?page=0";
    $newlines = array("\t","\n","\r","\x20\x20","\0","\x0B",",");
    $date = date(DATE_RSS);
    $page=0;
    $new_videos = array();

    // loop through all feed pages
    while ($url != NULL) {
        $html = ev_html_dom_parser::file_get_html($url);

        $length_str = $html->find('div[class=pagercontext]',0);
        $length_pcs = explode(" ", str_replace($newlines, "",$length_str->plaintext));

        $length = $length_pcs[3]-$length_pcs[1]+1;
        $current = $length_pcs[3];
        $items = isset($length_pcs[5]) ? $length_pcs[5] : 0;

        for ($i = 0; $i < $length; $i++) {
            // get video item at position i
            $item = $html->find('div[class=mediaBox]',$i);
            $metadata = $item->find('div[class=mediaMetadata]',0);
            $next=$i+1;

            // extract fields
            $video = array();
            $video['host_id']     = 'dotsub';
            $video['author_id']   = strtolower($author_id);
            $video['video_id']    = $item->id;
            $video['title']       = $metadata->find('a',0)->plaintext;
            $video['description'] = $metadata->find('p[id=description'.$next.'p]',0)->innertext;
            $video['authorname']  = $author_id;
            $video['videourl']    = 'http://dotsub.com'.$metadata->find('a',0)->href;

            // need to retrieve videourl to gain upload date
            $html_video = ev_html_dom_parser::file_get_html($video['videourl']);
            $published_div = $html_video->find('div[class=moduleBody]',0)->find('div',6);
            $published_pcs = explode(" ", str_replace($newlines, "",$published_div));
            $monthnames = array(1=>'Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec');
            $month = array_search($published_pcs[4],$monthnames);
            $published = mktime(0, 0, 0, $month, $published_pcs[5], $published_pcs[6]);
            $video['published']   = date("Y-m-d H:i:s", $published);

            // further fields
            $video['author_url']  = "http://dotsub.com/channel/user/".$video['author_id'];
            $video['category']    = $metadata->find('div[class=mediaLinks]',0)->find('a',2)->plaintext;
            $video['keywords']    = NULL;
            $video['thumbnail']   = $item->find('div[class=thumbnail]',0)->find('img',0)->src;

            // parse the seconds out of the duration string
            $duration_str = $metadata->find('li[class=first-metadataItem]',0)->find('h4',0)->plaintext;
            $duration_pcs = explode(" ", str_replace($newlines, "",$duration_str));
            $video['duration'] = $duration_pcs[1];
            $video['ev_author']   = $author['ev_author'];
            $video['ev_category'] = $author['ev_category'];
            $video['ev_post_format'] = $author['ev_post_format'];
            $video['ev_post_status'] = $author['ev_post_status'];

            // add $video to the end of $new_videos
            array_push($new_videos, $video);
        }

        // next feed page, if available
        if ($current < $items) {
          $page = $page + 1;
          $url = "http://dotsub.com/view/user/$author_id?page=$page";
        } else {
          $url = NULL;
        }
    }
    return $new_videos;
} // end sp_ev_fetch_dotsub_videos()


function sp_ev_fetch_wistia_videos($author)
{
  $author_id = $author['author_id'];
  $developer_key = $author['developer_key'];

  $url = "https://api.wistia.com/v1/medias.json";
  $url .= '?sort_by=created&sort_direction=1';
  $new_videos = array();

  // set other options
  $per_page = 100;	// max results Wistia will return per page
  $max_pages_limit = 12;	// max number of pages to retrieve (to prevent infinite loop)
  $wistia_default_image_size = '100x60';	// do not change, this is correct as of 10/2013
  $wistia_requested_image_size = '180x135';

  // loop through all author videos
  $page = 1;	// Wistia video results pages begin with 1

  while ($url != NULL) {
    $wistia_url = $url . "&page=$page";

    // send request; wistia is slow, so add extra long timeout
    $headers = array( 'Authorization' => 'Basic ' . base64_encode( "api:$developer_key" ) );
    $result = wp_remote_get( $wistia_url, array ( 'headers' => $headers, 'timeout' => 25 ) );

    // return on error
    if (!$result || is_wp_error( $result ) || preg_match('/^[45]/', $result['response']['code'])  ) {
        return $new_videos;
    }

    // get list of videos
    $videofeed = json_decode($result['body']);
    $length = count($videofeed);

    // loop through videos returned & extract fields
    foreach ($videofeed as $vid) {
      $video = array();
      $video['host_id']     = 'wistia';
      $video['author_id']   = strtolower($author_id);
      $video['video_id']    = $vid->hashed_id;
      $video['title']       = $vid->name;
      $video['description'] = $vid->description;
      $video['authorname']  = $author_id;
      $video['videourl']    = "http://$author_id.wistia.com/medias/" . $vid->hashed_id;
      $video['published']   = date("Y-m-d H:i:s", strtotime($vid->created));
      $video['author_url']  = "http://$author_id.wistia.com";

      $thumbnail_url	= $vid->thumbnail->url;
      // check for default "not found" thumbnail (default_100x60.gif)
      if (substr_count($thumbnail_url, 'default_') == 0) {
        // replace default image size with specifi size we require
        $thumbnail_url = str_replace($wistia_default_image_size, $wistia_requested_image_size, $thumbnail_url);
      }
      $video['thumbnail'] = $thumbnail_url;
      $video['duration']    = $vid->duration;
      $video['ev_author']   = $author['ev_author'];
      $video['ev_category'] = $author['ev_category'];
      $video['ev_post_format'] = $author['ev_post_format'];
      $video['ev_post_status'] = $author['ev_post_status'];

      // add $video to the end of $new_videos array
      array_push($new_videos, $video);
    }

    // next page
    if ($length > 0) {
      $page += 1;
    } else {
      $url = NULL;
    }

  }
  return $new_videos;

} // end sp_ev_fetch_wistia_videos()

?>
