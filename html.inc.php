<?php

/** -- Functions -------------------------
 * 
 * pageStart($list)                                                                
 * selectlist($name, $list, $selected, $usekeys = 1, $extra='', $return=false)     
 * selectListOther($name, $list, $selected, $usekeys = 1, $return=false)           
 * popup_link($link, $label, $width=300, $height=300, $options='')                 
 * inputRow($name, $value, $prop_input = array())                                  
 * inputField($name, $value, $prop_input = array())                                
 * input_datetime($name, $value, $props)                                           
 * 
 * 
**/


//____________________ May need updating?
//  pageStart($list) /
function pageStart($list) {
	global $me;
	$defaults = array(
		'stylesheet' => '',
		'style'      => '',
		'javascript' => '',
		'jquery'     => '',
		'js_include' => '',
		'headertags' => '',
		'body'       => '',
		'charset'    => 'UTF-8',
		);
	if (! is_array($list)) $list = array('title'=>$list); // if not array, assume title string
	$list = array_merge($defaults, (array) $list); // merge default values with custom, first come first served
	extract($list);
	$tags = (bool) strpos(strtolower($javascript), '</script>');
	echo "<!DOCTYPE html>
<html>

<head>
	<title>". ($title?$title:'untitled') ."</title>
	<meta http-equiv='content-type' content='text/html; charset=$charset'>
	<script src='http://ajax.googleapis.com/ajax/libs/jquery/1.4/jquery.min.js' type='text/javascript'></script>\n";
	if ($headertags) {
		if (! is_array($headertags)) $headertags = array($headertags);
		foreach ($headertags as $tag) {
			if ($tag) echo "\t$tag\n";
		}
	}
	if ($js_include) {
		if (! is_array($js_include)) $js_include = array($js_include);
		foreach($js_include as $js_incl) {
			if ($js_incl) echo "\t<script src='$js_incl' type='text/javascript' charset='utf-8'></script>\n";
		}
	}
	if ($stylesheet) {
		if (! is_array($stylesheet)) $stylesheet = array($stylesheet);
		foreach($stylesheet as $tmp) {
			if ($tmp) echo "\t<link rel='stylesheet' type='text/css' href='$tmp'>\n";
		}
	}
	if ($jquery) {
		echo 
	'<script type="text/javascript">
	$(document).ready(function(){
		'.$jquery.'
	});
	</script>'."\n";
	}
	echo ($style?"<style type='text/css'>\n". $style ."\n</style>\n":'') ."
". ($javascript ? ($tags ? $javascript : "<script language='javascript' type='text/javascript'>\n$javascript\n</script>\n") : '') ."
</head>

<body $body>"; // </body> -> This helps tm folding. Yeah.
}


//__________________________________________________________________________
// selectlist($name, $list, $selected, $usekeys, $extra='', $return=false) /
function selectlist($name, $list, $selected, $usekeys = 1, $extra='', $return=false) {
	$output = "<select name='$name' $extra>\n";
	foreach($list as $key => $value) {
		$h_key   = htmlspecialchars($key, ENT_QUOTES);
		$h_value = htmlspecialchars($value, ENT_QUOTES);
		if ($usekeys) {
			$output .= "<option value='$h_key' " . (( (string) $key == (string) $selected)?'selected="selected"':'') . ">$h_value</option>\n";
		}else{
			$output .= "<option value='$h_value' " . (( (string) $value == (string) $selected)?'selected="selected"':'') . ">$h_value</option>\n";
		}
	}
	$output .= "</select>\n";
	if ($return) return $output;
	echo $output;
}


//_____________________________________________________
// selectlistother($name, $list, $selected, $usekeys) /
function selectlistother($name, $list, $selected, $usekeys = 1) {
	echo 
	 "<select name='$name'
		onchange=\"this.form.{$name}__other.style.visibility = (this.options[".count($list)."].selected)?'visible':'hidden'; return true;\">\n";
	foreach($list as $key => $value) {
		$h_key   = htmlspecialchars($key, ENT_QUOTES);
		$h_value = htmlspecialchars($value, ENT_QUOTES);
		if ($usekeys) {
			echo "<option value='$h_key' " . (( (string) $key == (string) $selected)?'selected="selected"':'') . ">$h_value</option>\n";
		}else{
			echo "<option value='$h_value' " . (( (string) $value == (string) $selected)?'selected="selected"':'') . ">$h_value</option>\n";
		}
	}
	echo "<option value=''>Other...</option>\n";
	echo "</select>\n";
	echo "<input type='text' name='{$name}__other' style='margin-left: 8px; visibility: ". ($list ? 'hidden' : 'visible') .";' />\n";
}


#__________________________________________________________________
# popup_link($link, $label, $width=300, $height=300, $options='') /
function popup_link($link, $label, $width=300, $height=300, $options='') {
	$output = '';
	$defaults = array(
		'toolbar'=>'no',
		'location'=>'no',
		'directories'=>'no',
		'status'=>'no',
		'menubar'=>'no',
		'scrollbars'=>'yes',
		'resizable'=>'yes',
		'style'=>'',
		'class'=>'',
		'return'=>false); # that last key makes the function return the code instead of echo it
	if (is_array($options)) {
		$options = array_merge($defaults, $options);          # have the values of options override those in defaults, but
		$defaults = array_intersect_key($options, $defaults); # ..kick out the keys not existing in defaults.
	}
	extract($defaults);
	$output = "<A HREF='#'
	style='$style'
	class='$class'
	onClick=\"MyWindow=window.open('$link','MyWindow','toolbar=$toolbar,location=$location,directories=$directories,status=$status,menubar=$menubar,scrollbars=$scrollbars,resizable=$resizable,width=$width,height=$height'); return false;\">$label</A>";
	if ((bool) $return) {
		return $output;
	}else{
		echo $output;
	}
}


#_______________________________________
# inputRow($name, $value, $prop_input) /
function inputRow($name, $value, $prop_input = array()) {
	$sp = '&nbsp;';
	if (! is_array($prop_input)) {
		$prop_input = stringToAssoc($prop_input);
	}
	$defaults = array(
		'label'     => ucwords($name),
		'type'      => 'text',
		'size'      => 32,
		'maxlength' => '255',
		'td1class'  => '');
	$props = array_merge($defaults, $prop_input);
	extract($props);
	if ($type == 'none') return false;          # do not display
	$label = str_replace('%20', ' ', $label);
	if ($type != 'row') {
		echo
			"<TR><TD VALIGN='top' CLASS='td1 $td1class'>\n".
				"  $label$sp\n".
			"</TD><TD VALIGN='top' CLASS='td2'>\n";
		inputField($name, $value, $prop_input);
		echo "</TD></TR>\n\n";
	}else{
		echo "<TR><TD COLSPAN='2'>\n";
		echo $value;
		echo "</TD></TR>\n";
	}
}


#_________________________________________
# inputField($name, $value, $prop_input) /
function inputField($name, $value, $prop_input = array()) {
	$output = '';
	static $label_id = 1;
	if (! $name) return $label_id++;
	if (! is_array($prop_input)) {
		$prop_input = stringToAssoc($prop_input);
	}
	$defaults = array(
		'type'        => 'text',
		'size'        => 40,
		'maxlength'   => 255,
		'rows'        => 4,
		'maxrows'     => 20,
		'cols'        => 60,
		'class'       => '',
		'wrap'        => 'virtual',
		'checked'     => '',
		'link_params' => '',
		'text'        => '',
		'extra'       => '',
		'return'      => false
		);
	$props = array_merge($defaults, $prop_input);
	foreach($props as $key => $waarde) {
		if (is_string($waarde)) $props[$key] = rawurldecode($waarde);
		// $props[$key] = str_replace('%20', ' ', $waarde);
	}
	extract($props);
	if ($type == 'none') return false;          # do not display
	if ((bool) $checked) $checked = "checked='checked'";  // 'checked'
	if (isset($ml)) $maxlength = $ml;           // 'maxlength'
	switch (strtolower($type)) {
		case 'text':                                    # text
			$output = "  <input type='text' name='$name' size='$size' maxlength='$maxlength' value='".htmlents($value)."' ". @$options ." class='$class'>$extra\n";
			break;   
		case 'textarea':                                # textarea
		case 'ta':                                      # textarea
			if (! is_int($rows)) {
				if (substr($rows, 0, 1) == 'n') $rows = min(substr_count($value, "\n") + (int) substr($rows, 1), $maxrows);
			}
			$output = "  <textarea rows='$rows' cols='$cols' name='$name' wrap='$wrap' class='$class' ". @$options .">". htmlents($value) ."</textarea>\n";
			break;
		case 'checkbox':                                # checkbox
			$output = "  <LABEL FOR='l_$label_id'><INPUT TYPE='checkbox' NAME='$name' ID='l_$label_id' VALUE='".htmlents($value)."' $checked> $label</LABEL>\n";
			$label_id++;
			break;
		case 'checkbool':                               # checkbool
			$output = "  <INPUT TYPE='hidden' VALUE='0' NAME='$name'>\n".
					 "  <INPUT TYPE='checkbox' NAME='$name' VALUE='1' ". ($value?'CHECKED':'') ." $extra> $text\n";
			break;
		case 'radio':                                   # radio
			$output = "  <LABEL FOR='l_$label_id'><INPUT TYPE='radio' NAME='$name' ID='l_$label_id' VALUE='".htmlents($value)."' $checked> $label</LABEL>\n";
			$label_id++;
			break;
		case 'select':                                  # select (external)
			if (is_string($list)) {
				$list = stringToAssoc($list);
				foreach($list as $key => $item) {
					$list[$key] = rawurldecode($item);
				}
			}
			$output .= selectList($name, $list, $value, @$usekeys, @$extra, 1);
			break;
		case 'selectother':                             # selectother (external)
			if (is_string($list)) {
				$list = stringToAssoc($list);
				foreach($list as $key => $item) {
					$list[$key] = rawurldecode($item);
				}
			}
			$output .= selectlistother($name, $list, $value, @$usekeys, 1);
			break;
		case 'datetime':                                # datetime (external)
			$output = input_datetime($name, $value, array_merge($props, array('show_time'=>true, 'return'=>true)));
			break;
		case 'date':                                    # date (external)
			$output = input_datetime($name, $value, array_merge($props, array('show_time'=>false, 'return'=>true)));
			break;
		case 'password':                                # password
			$output = "  <INPUT TYPE='password' NAME='$name' SIZE='$size' MAXLENGTH='$maxlength' VALUE='".htmlents($value)."' class='$class'>\n";
			break;
		case 'file':                                    # file
			$output = "  <INPUT TYPE='file' NAME='$name'>\n";
			break;
		case 'image':                                   # image (calls file)
			if (! isset($preview)) $preview = $value;
			if ($preview) $output = "<a href='".str_replace('%2F', '/', urlencode($path)).urlencode($value)."' target='_blank'><img src='".str_replace('%2F', '/', urlencode($path)).urlencode($preview)."'></a><br>";
			$output .= inputField($name, $value, 'type=file return=1');
			break;
		case 'hidden':                                  # hidden
			$output = "  <INPUT TYPE='hidden' NAME='$name' VALUE='".htmlents($value)."'>\n";
			break;
		case 'display':                                 # display
			$output = $value."\n";
			break;
		default:
			$output = styledText("inputField() error: Unsupported type: $type<BR>", 'red');
			break;
	}
	if ($return) return $output;
	echo $output;
}


#________________________________
# input_datetime($name, $props) /
function input_datetime($name, $value, $props) {
	$output = '';
	$defaults = array(
		'show_time'  => true,
		'year_list'  => range(date('Y')-7, date('Y')+4),
		'month_list' => get_month_list(),
		'return'     => false);
	$props = array_merge($defaults, $props);
	extract($props);
	if (! $value) $value = date('Y-m-d H:i');  
	list($year, $month, $day, $time) = sscanf($value, "%d-%d-%d %s"); // regular MySQL datetime format
	$output .= inputField($name .'_d', $day,   array('type'=>'select', 'list'=>range(1, 31), 'usekeys'=>false, 'return'=>true));
	$output .= inputField($name .'_m', $month, array('type'=>'select', 'list'=>$month_list,  'usekeys'=>true, 'return'=>true));
	$output .= inputField($name .'_y', $year,  array('type'=>'select', 'list'=>$year_list,   'usekeys'=>false, 'return'=>true));
	if ($show_time) {
		$output .= ', ' . inputField($name .'_t', substr($time, 0, 5), 'type=text size=10 maxlength=8 return=1');
	}
	if ($return) return $output;
	echo $output;
}

if (basename($_SERVER['PHP_SELF']) == 'html.inc.php') echo "'html.inc.php' parses.";


/* -- Log --------------------------------

[2010-10-27 13:35:14] Ditto.
[2010-03-24 03:00:31] Copied from somewhere recent enough
[2009-09-13 05:46:57] Updated selectList, selectlistother, input_datetime & inputField, added parameter $return and variable $output
[2009-08-15 00:00:48] Fixed pageStart to only show jQuery document.ready tags if there's actual jQuery content passed.
[2009-04-12 04:26:58] Threw script.js out again. Use jQuery damnit.
[2009-04-12 02:02:53] Added script.js to default pageStart()
                      Copied @$options from inputField "input" to "textarea" since it's a useful beastie.
[2009-02-06 06:27:03] Copied from Saturn
[2008-11-15 18:55:50] input_datetime was only accepting two params, value missing, fixed.
                      also, it called inputField with only two params (also fixed). what's with this thing?
[2008-10-11 16:54:38] Copied file from Mech.CX

Todo: patch selectListOther to not rely on it being inside a form (does getElementsByName('foo')[0] work?)

*/

?>