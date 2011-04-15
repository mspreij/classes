<?php
//   /inc/functions.inc.php

/** -- Functions -------------------------
 * 
 * htmlents($string)                                              
 * stringtoassoc($string, $toggles = '')                          
 * unnest_array(&$arr, $return=false)                             
 * showmessages($return = false)                                  
 * array_unshift_assoc(&$arr, $key, $val)                         
 * googlinks($links, $total, $return=0)                           
 * http_raw_query($formdata, $numeric_prefix=null, $key=null)     
 * key_unnest($arr, $first_only = false)                          
 * is_in_array($str, $array)                                      
 * array_dump($array, $options='')                                
 * now()                                                          
 * nice_time($time, $short=false)                                 
 * get_month_list()                                               
 * validateEmail($address)                                        
 * get_column($array, $col_name)                                  
 * styledText($string, $color='black', $style='')                 
 * urlify($value)                                                 
 * 
 * 
 ** --- File ---
 * handle_fileupload($field, $dest_path, $name='')                
 * duplicate_name($orig, $list = array(), $max = 64)              
 * clean_filename($filename)                                      -- replace spaces by underscores, then filter out crap, then collapse adjacent underscores.
 * fsize($x)                                                      
 * file_ext($file)                                                
 * file_label($file, $label)                                      
 * resize_image($image, $sizes)                                   
 * 
 * 
 ** --- Logging ---
 * writeLog($data, $level=2, $type='system')                      
 * mailAdmin($subject, $message, $writeLog_id = false)            
 * handleError($message, $level=2, $type='system')                
 * 
 * 
**/



//____________________
// htmlents($string) /
function htmlents($string) {
	return htmlspecialchars($string, ENT_QUOTES);
}


//________________________________________
// stringToAssoc($string, $toggles = '') /
function stringtoassoc($string, $toggles = '') {
	$array = array();
	$urldecode = $bool = false;
	if (strstr(strtolower($toggles), 'u')) $urldecode = true;
	if (strstr(strtolower($toggles), 'b')) $bool      = true;
	$string = preg_split('/\s+/', $string);
	foreach($string as $pair) {
		$pair = ltrim($pair, '=');
		if (strpos($pair, '=') !== false) { # it's a pair, good
			$key = substr($pair, 0, strpos($pair, '='));
			$value = substr(strstr($pair, '='), 1);
		}else{ # no '=' sign, assume value
			$value = $pair;
		}
		if ($bool) { # convert strings 'true' & 'false' to boolean 
			if (strtolower($value) === 'false') $value = false;
			if (strtolower($value) === 'true')  $value = true;
		}
		if ($urldecode) $value = rawurldecode($value);
		# add the [key &] value
		if (isset($key)) {
			$array[$key] = $value;
			unset($key);
		}else{
			$array[] = $value;
		}
	}
	return $array;
}


//_____________________________________
// unnest_array(&$arr, $return=false) /
function unnest_array(&$arr, $return=false) {
	if (! is_array($arr)) {
		echo styledText('Error: unnest_array needs array, got '. gettype($arr), 'red');
		return false;
	}
	$out = array();
	foreach($arr as $row) $out[] = array_shift($row);
	if ($return) {
		return $out;
	}else{
		$arr = $out;
	}
}


//________________________________
// showMessages($return = false) /
function showmessages($return = false) {
	if (! isset($GLOBALS['messages'])) return false;
	$messages = $GLOBALS['messages'];
	$output   = '';
	if (is_array($messages)) {
		if (count($messages) > 0) {
			$output .= join("\n", $messages); // took out <br> [2009-09-04 02:11:54]
		}
	}elseif ($messages){
		$output .= $messages;
	}
	$GLOBALS['messages'] = array(); // flush messages
	$output = "<div class='showMessages'>\n$output</div>\n";
	if ($return) return $output;
	echo $output;
}


//_________________________________________ -- if key already exists, does the item end up as the first item..?
// array_unshift_assoc(&$arr, $key, $val) /
function array_unshift_assoc(&$arr, $key, $val) {
	$arr = array_reverse($arr, true);
	$arr[$key] = $val;
	$arr = array_reverse($arr, true);
	return count($arr);
}


//_______________________________________
// googlinks($links, $total, $return=0) /
function googlinks($links, $total, $return=0) {
	global $me;
	$page = (int) @$_GET['page'];
	$skip = $links * $page;
	$out = '';
	$b = "style='font-weight: bold;'";
	// echo $_SERVER['QUERY_STRING'];
	parse_str($_SERVER['QUERY_STRING'], $query);
	// print_r($query);
	unset($query['page']);
	if ($query = http_raw_query($query)) $query .= '&';
	$pages = ceil($total/$links)-1;
	// $start = "$me?{$query}";
	$start = "?";
	$items[] = ($page > 0) ? array('<span class="prev">&laquo; prev</span>', $start."page=".($page-1)) : '<span class="prev">&laquo; prev</span>';
	for($i=0;$i<=min($pages, 99);$i++) { // a hundred pages will be quite sufficient for now, thank you.
		$items[] = array($i+1, $start."page=$i");
	}
	$items[] = ($page < $pages) ? array('<span class="next">next &raquo;</span>', $start."page=".($page+1)) : '<span class="next">next &raquo;</span>';
	if (! $return) {
		foreach($items as $val) $out .= ' '. (is_array($val) ? "<a href='{$val[1]}' ".(($val[0] == ($page+1)) ? $b : '').">{$val[0]}</a>" : $val);
		$out = substr($out, 1);
	}elseif ($return == 1) {
		$out = array();
		foreach($items as $val) $out[] = is_array($val) ? "<a href='{$val[1]}' ".(($val[0] == ($page+1)) ? $b : '').">{$val[0]}</a>" : $val;
	}
	return $out;
}


//_____________________________________________________________
// http_raw_query($formdata, $numeric_prefix=null, $key=null) /
function http_raw_query($formdata, $numeric_prefix=null, $key=null) {
	$res = array();
	foreach((array) $formdata as $k => $v) {
		$tmp_key = rawurlencode(is_int($k) ? $numeric_prefix.$k : $k);
		if ($key) $tmp_key = $key.'['.$tmp_key.']';
		$res[] = ( (is_array($v) || is_object($v)) ? http_raw_query($v, null, $tmp_key) : $tmp_key."=".rawurlencode($v) );
	}
	$separator = ini_get('arg_separator.output');
	return implode($separator, $res);
}


//________________________________________
// key_unnest($arr, $first_only = false) /
function key_unnest($arr, $first_only = false) {
	$out = array();
	foreach($arr as $row) {
		$key = array_shift($row);
		$out[$key] = $first_only ? array_shift($row) : $row;
	}
	return $out;
}

//____________________________ -- shouldn't a stringtolower-based comparison be faster?
// is_in_array($str, $array) /
function is_in_array($str, $array) {
	return preg_grep('/^' . preg_quote($str, '/') . '$/i', $array);
}

//__________________________________ draws a table around a multidimensional array (such as a SELECT result)
// array_dump($array, $options='') /
function array_dump($array, $options='') {
	$options = strtolower(" $options");
	$pre     = strpos($options, 'p'); // duplicate as needed
	$return  = strpos($options, 'r'); // echo or return?
	$out     = '';
	$out .= "<table border='1' cellspacing='0'>\n";
	if (is_array(current($array))) { // nested array
		if (! is_numeric(key(current($array)))) { // of which inner arrays are assoc
			$out .= "<tr><th>•</th><th>";
			$out .= join('</th><th>', array_map('htmlents', array_keys(current($array))));
			$out .= "</th></tr>\n";
		}
	}
	foreach($array as $key => $value) {
		$out .= "<tr>";
		if (is_string($value)) {
			$out .= "<td valign='top'>$key</td><td>".(($pre && strstr($value, "\n")) ? '<pre>':'').htmlspecialchars($value)."</td>";
		}else if(is_array($value)) {
			$out .= "<td>$key</td>";
			foreach($value as $col) {
				$out .= "<td>".(($pre && strstr($value, "\n")) ? '<pre>':'').htmlspecialchars($col)."</td>";
			}
		}
		$out .= "</tr>\r";
	}
	$out .= "</table>";
	if ($return) return $out;
	echo $out;
}


//________
// now() /
function now() {
	return date('Y-m-d H:i:s');
}


//_________________________________
// nice_time($time, $short=false) /
function nice_time($time, $short=false) {
	if ($time == '0000-00-00 00:00:00' or ! $time) return '';
	$diff = abs(floor((time() - strtotime($time)) / 3600));
	$showtime = date('Y/m/d H:i:s', strtotime($time));
	if($diff < 24){
		$today_day = strftime('%a',time());
		$time_day = strftime('%a',strtotime($time));
		$daghint = ($today_day == $time_day ? 'vandaag' : 'gisteren' ) .', ';
		return $daghint . substr($showtime, -8, 5);
	}else{
		return strftime(($short ? '%a' : '%A') .', %d %B %Y', strtotime($time));
	}
}


//___________________
// get_month_list() /
function get_month_list() {
	$outlist = array();
	foreach(range(1, 12) as $month) {
		$outlist[substr('0'.$month, -2)] = strftime('%B', mktime(0, 0, 0, $month, 1, 2000));
	}
	return $outlist;
}


//__________________________
// validateEmail($address) /
function validateEmail($address) {
	return preg_match("/^[A-Za-z0-9_+-]+([\.]{1}[A-Za-z0-9_+-]+)*@[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)*(\.[A-Za-z0-9-]{2,6})+$/", $address);
}


//________________________________
// get_column($array, $col_name) /
function get_column($array, $col_name) {
	$out = array();
	foreach ($array as $row) {
		$out[] = $row[$col_name];
	}
	return $out;
}


#_________________________________________________
# styledText($string, $color='black', $style='') /
function styledText($string, $color='black', $style='') {
	if (func_num_args() == 1) return $string;
	$style_str = "color: $color; ";
	$style = ' '.$style;
	if (strpos($style, 'b')) $style_str .= ' font-weight: bold;';
	if (strpos($style, 'i')) $style_str .= ' font-style: italic;';
	if (strpos($style, 'u')) $style_str .= ' text-decoration: underline;';
	$string = "<SPAN STYLE='$style_str'>$string</SPAN>";
	return $string;
}


//_________________
// urlify($value) /
function urlify($value) {
	// translate funky chars
	$value = str_replace(str_split('àáâäåãèéêéìíîïòóôöõøùúûü'), str_split('aaaaaaeeeeiiiioooooouuuu'), $value);
	// strip invalid stuff, trim, and replace remaining spaces by '_'
	$value = strtolower(str_replace(' ', '_', trim(preg_replace('/[^a-z0-9_ ]/i', '', $value))));
	$value = preg_replace('/_+/', '_', $value);
	return $value;
}


// --- File Functions --------------------


//__________________________________________________
// handle_fileupload($field, $dest_path, $name='') /
function handle_fileupload($field, $dest_path, $name='') {
	global $messages;
	if ($tmp = $_FILES[$field]['name']) {
		$file = $_FILES[$field];
		if ($name) {                          // [2009-10-23 17:03:20]
			$name .= '.'. file_ext($file['name']);
		}else{
			$name = $file['name'];
			if (strstr($name, '\\')) $name = array_pop(explode('\\', $name)); // fixes funky browser names
			$name = clean_filename($name);
			$files = array_map('basename', (array) glob($dest_path.'*')); // todo: shouldn't glob Always return array? check docs, fix as needed (empty item array), file bug as needed.
			if (in_array($name, $files)) {            // if the file already exists, attempt to rename it
				$name = duplicate_name($name, $files);  // this might put spaces in the filenames again, but .. *sigh*
				$messages[] = styledText("Name of file already in use, renaming.<br>", '#F40');
			}
		}
		// if dest_path is empty, it will be moved to the current working directory (getcwd()), usually the script's location
		if (! @move_uploaded_file($file['tmp_name'], $dest_path . $name)) { // botheration
			$messages[] = styledText("Error moving file ". ($dest_path ? "to $dest_path" : '') .".<br>", 'red');
			echo $php_errormsg;
			return false;
		}else{
			$messages[] = styledText("Added file.<br>", 'blue');
			if (! chmod($dest_path . $name, 0644)) {
				$messages[] = styledText("Error changing file permissions.", 'orange');
			}
			return $name;
		}
	}else{
		$messages[] = 'No file uploaded.';
		return false;
	}
}


//____________________________________________________
// duplicate_name($orig, $list = array(), $max = 64) /
function duplicate_name($orig, $list = array(), $max = 64) {
	global $messages;
	$ext = '';
	$counter = 0;
	$list = (array) $list;
	$max = (int) $max;
	$newname = $orig;
	do {
		$name = $newname; # name in, newname out
		if (preg_match('/ copy$| copy \d+$/', $name, $matches)) {
			// don't even check for extension, name ends with " copy[ digits]"
		# preg hereunder matches anything with at least one period in the middle and an extension of 1-5 characters
		}elseif (preg_match('/(.+)\.([^.]{1,5})$/', $name, $parts)) {
			# split to name & extension
			list($name, $ext) = array($parts[1], $parts[2]);
		}
		if (preg_match('/ copy (\d+)$/', $name, $digits)) {
			$newname = substr($name, 0, - strlen($digits[1])) . ($digits[1] + 1);
			$cutlen = 6 + strlen($digits[1]+1); // ' copy ' + digits
		}elseif(preg_match('/ copy$/', $name, $digits)) {
			$newname = $name . ' 1';
			$cutlen = 7; // ' copy' + ' 1'
		}else{
			$newname = $name . ' copy';
			$cutlen = 5; // ' copy'
		}
		if ($ext) {
			$newname .= '.' . $ext;
			$cutlen += strlen($ext) + 1;
		}
		if ($max > 0) {
			if (strlen($newname) > $max) {
				$newname = substr($newname, 0, max($max - $cutlen, 0)) . substr($newname, -$cutlen);
				if (strlen($newname) > $max) {
					$messages[] = styledText("duplicate_name() error: Can't keep the new name under given max length.\n", 'red');
					return false;
				}
			}
		}
		if ($counter++ > 500) {
			$messages[] = styledText("duplicate_name() error: Too many similarly named files or infinite while loop.\n", 'red');
			return false;
		}
	} while (in_array($newname, $list));
	return $newname;
}


//____________________________
// clean_filename($filename) /
function clean_filename($filename) {
	$filename = str_replace(' ', '_', $filename);
	// \w for any word character; some punctuation, range of high-ASCII (192-255), couple isolated cases, and the '-'
	$pattern = '/[\w.!_À-ÿšŽŠžŸ-]+/';
	preg_match_all ($pattern, $filename, $matches);
	$filename = implode('', $matches[0]);
	$filename = preg_replace('/_+/', '_', $filename);
	return $filename;
}


//__________________________
// fsize($x, $precision=3) /
function fsize($x, $precision=3) {
	settype($x, 'integer');
	$sizes = explode(' ', 'Bytes KB MB GB TB PB EB');
	for ($i=0; $x>=1024; $i++) {
		$x /= 1024;
	}
	if ($sizes[$i] == 'MB' && $i < 10) $precision = 1;
	return round($x, $precision) .' '. $sizes[$i];
}


//__________________
// file_ext($file) /
function file_ext($file) {
	return substr(strrchr($file, "."), 1);
}


//____________________________
// file_label($file, $label) /
function file_label($file, $label) {
	if (! $file) return '';
	return substr($file, 0, strrpos($file, ".")).$label.substr($file, strrpos($file, "."));
}


//_____________________________________________
// resize_image($image, $sizes, $force=false) / 
function resize_image($image, $sizes, $force=false) {
	global $messages;
	// require_once 'class/my_image.class.php';
	$I = new Image($image);
	if ($I->data) {
		if (! is_array($sizes)) $sizes = array($sizes); // [2011-02-08 01:37:10]
		foreach($sizes as $label => $size) {
			if (! $label) $label = ''; // numeric 0 becomes empty string. It makes sense.
			if (strpos($label, '/')!==false) {
				// Since '/' is illegal in a filename on most filesystems, we'll interpret this as a
				// path, instead. And add [another] slash if needed.
				// [2010-12-19 13:05:48] Also, we'll strip the path info from the current image, if any
				$newname = $label .(substr($label, -1)!='/' ? '/':''). basename($image);
			}else{
				$newname = file_label($image, $label); // foo.jpg + '_thumb' = foo_thumb.jpg
			}
			// now we attempt some hackery to only resize it if needed (unless $force == true)
			$resize = false;
			if (! $force) {
				$x = $I->getxy('x');
				$new_size = $I->calc_sizes($x, $I->getxy('y'), $size);
				if (! is_array($new_size)) {
					continue;
				}
				$new_x = $new_size[0];
				if ($new_x < $x) $resize = true;
			}
			if ($resize or $force) {
				$J = clone $I;
				$J->scale_image($size);
				if (! $J->save($newname)) {
					$messages[] = styledText('Errors in resize_image(): '. join('<br>', $I->getErrors()), 'red');
				}
				$J->destroy();
			}else{
				$messages[] = styledText("Skipped resizing $label => ". json_encode($size) .", image too small<br>\n", '#f80');
			}
		}
		$I->destroy();
	}else{
		$messages[] = styledText("Error reading image data in resize_image().", 'red');
	}
}


// --- Logging Functions -----------------

//______________________________ ! [2010-12-07 19:09:02] added referrer field
// writeLog($string, $level=0) / Last update [2011-01-16 01:47:36], backtrace error level > 1
function writeLog($data, $level=2, $type='system', $backtrace=true) {
	global $User;
	if (! function_exists('mres')) {function mres($string) {return mysql_real_escape_string($string);}}
	$user_id = isset($User->id) ? $User->id : '0';
	$referrer = @$_SERVER['HTTP_REFERER'];
	settype($level, 'int');
	if (! defined('EMAIL_SYSTEM')) {
		define('EMAIL_SYSTEM', 'info@'.$_SERVER['HTTP_HOST']);
		$data = "writelog() notice: EMAIL_SYSTEM not defined.\n\n". $data;
	}
	if ($level > 1 and $backtrace) $data .= "\n\n[Included] file: ". __FILE__ ."\n\nDebug_backtrace():\n\n". print_r(debug_backtrace(), true);
	$sql = 
	 "INSERT INTO logbook
			(`user_id`, `ip`, `browser`, `page`, `type`, `data`, `referrer`, `level`)
		VALUES
			($user_id,
			 '". mres($_SERVER['REMOTE_ADDR']) ."',
			 '". mres($_SERVER['HTTP_USER_AGENT'])."',
			 '". mres($_SERVER['REQUEST_URI'])."',
			 '". mres($type) ."',
			 '". mres($data) ."',
			 '". mres($referrer) ."',
			 '$level')";
	$res = mysql_query($sql);
	if ((! $res) && ($level >= 2)) {
		echo '<span style="color: red;">Failure logging error, please notify '. EMAIL_SYSTEM .'</span>';
		// echo styledText('Include the following error message: "'. mysql_error() .'"', '#FF8000'); // security issue?
		return false;
	}else{
		return mysql_insert_id();
	}
}


//________________________________
// mailAdmin($subject, $message) /
function mailAdmin($subject, $message, $writeLog_id = false) {
	$message = 
	 "Request URI: {$_SERVER['REQUEST_URI']}
		\n\nMessage:\n".
		wordwrap($message, 78);
	if ($writeLog_id) {
		$message .= "\n\nSee logbook: http://{$_SERVER['HTTP_HOST']}/config/logbook.php?id=$writeLog_id";
	}
	# Note: mail nearly always returns true, it doesn't mean the message was actually sent, only delivered to some MTA (maybe)
	$res = mail(EMAIL_SYSTEM, $subject, $message, "From: Website <errors@{$_SERVER['HTTP_HOST']}>\n");
	if (! $res) {
		echo '<span style="color: red;">Failure sending mail, please notify '. EMAIL_SYSTEM .'</span>';
		return false;
	}
}


//__________________________________
// handleError($message, $level=2) /
function handleError($message, $level=2, $type='system') {
	$id = writeLog($message, $level, $type);
	if ($level >= 2) mailAdmin($_SERVER['HTTP_HOST'] .' Error', $message, $id);
	return $id;
}

/* -- Log --------------------------------

[2011-02-08 01:37:10] Updated resize_image() to accept string/int $sizes ($label will become 0, it will replace the original image by default)
[2011-01-16 01:47:36] Updated writelog() to show backtrace (by default) for error levels > 1, instead > 0
[2011-01-09 21:05:36] Updated showMessages(), simpler return structure (if (return) return output; echo output;)
[2010-12-30 15:30:31] Translated a bunch of $messages from Dutch -> English.
[2010-12-19 13:05:48] Updated resize_image() with force_resize bool, paths. (somewhere here, anyway)
[2010-12-13 05:25:29] Updated array_dump(), added return option (really have to merge those two properly sometime)
[2010-12-07 19:09:02] Updated writelog: added referrer field, defined mres() in case it wasn't already, replaced mysql_real_escape_string calls with it
[2010-11-17 15:46:44] Updated resize_image(), label (in sizes array) will now be interpreted as filepath if it contains a forward slash.
[2010-04-02 xx:xx:xx] Futzed googlinks about for mod-rewrite/url-embedded queries.
[2010-03-24 02:34:17] Copied from Infobuddy, added in MySQL functions

Todo: Consider additional fields for the log table?
Todo: resize_image() was updated (2010-11-17 15:46:44) but /could/ have a slightly more subtle label path resolving thingie - have to distinguish between
      path relative to script, or to image upload directory. Could also chdir() to the right directory before calling it, or set the paths relative to server root.
      Figure out a good way, implement and document it.
      ALSO: the $force thing, if an image is smaller already, should it be re-saved under the new label or location regardless? add another toggle? or something
      to choose from [force-save&scale / force-save / force-neither] instead ?

*/

?>