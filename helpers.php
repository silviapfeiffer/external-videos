<?

// Convert $num_secs to Hours:Minutes:Seconds
function sec2hms($num_secs) {
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

function shorten_text($input, $length = 36, $ellipses = true) {
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
 
	return $trimmed_text;
}

?>
