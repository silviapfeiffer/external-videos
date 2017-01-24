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

if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// divers helper routines for the external videos plugin


// Convert $num_secs to Hours:Minutes:Seconds
function sp_ev_sec2hms($num_secs) {
  $result = '';

  $hours   = intval(intval($num_secs) / 3600);
  $result .= $hours.':';

  $minutes = intval(((intval($num_secs) / 60) % 60));
  if ($minutes < 10) $result .= '0';
  $result .= $minutes.':';

  $seconds = intval(intval(($num_secs % 60)));
  if ($seconds < 10) $result .= '0';
  $result .= $seconds;

  return $result;
}

function sp_ev_shorten_text($input, $length = 36, $ellipses = true) {
  //no need to trim, already shorter than trim length
  if (strlen($input) <= $length) {
    return $input;
  }

  //find last space within length
  $last_space = strrpos(substr($input, 0, $length), ' ');
  $trimmed_text = substr($input, 0, $last_space);

  //add ellipses (...)
  if ($ellipses) {
    $trimmed_text .= '...';
  }

  return sanitize_text_field( $trimmed_text );
}

function sp_ev_convert_youtube_time($youtube_time){
  $start = new DateTime('@0'); // Unix epoch
  $start->add(new DateInterval($youtube_time));
  return $start->format('H:i:s');
}

?>
