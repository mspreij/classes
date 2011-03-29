<?php
/**
 * HTML
 * 
 * Singleton html page object, add headers and body stuffs, replace 'm as needed, add arrays of [template, data]
 * to the body, and call ->render(). BOOM! HTML page.
 * 
 * 
 * Optionally define:
 * function getPage() {
 *   return HTML::page(); // why yes, I AM a lazy bastard.
 * }
 * 
 * -- Methods ----------------------------
 * 
 * __construct()                                                    -- It's a private thing.                                                              [Private]
 * page()                                                           -- returns singleton object instance
 * add($key, $val, $tag='')                                         -- add css, stylesheets, javascript, script srcs, jquery, random header tags, and bodytag cruft.
 * set($key, $val)                                                  -- set title, charset and other future single-value properties
 * body($input, $tag=false, $overwrite=false)                       -- string $input will be echoed, an array will be treated as array(template, assoc data) that is
 *                                                                     parsed at render() time. Tagging them allows overwriting later easily; a tag identifier is
 *                                                                     returned regardless (assoc data from array is optional).
 * render($return = false)                                          -- render the page, echoing or optionally returning it as string instead
 * render_part($part, $nested=false)                                -- used by render() and called from body() if rendering is in process. Returns HTML chunk.
 * 
 * 
**/

class HTML {
	
	private static $instance; // singleton property
	
	// These can be set, reset, added to, etc; they define the contents of the page
	public $bodytag      = array();
	public $js_include   = array();
	public $stylesheet   = array();
	public $headertags   = array();
	public $style        = array();
	public $javascript   = array();
	public $jquery       = array();
	public $body         = array();
	
	// Set these like HTML->set('title', 'Hello World!')
	public $title        = 'untitled';
	public $charset      = 'UTF-8';
	public $mimetype     = 'text/html';
	public $doctype      = '<!DOCTYPE html>';
	public $xmlns        = ''; // 'http://www.w3.org/1999/xhtml';
	
	protected $html_out  = '';       // the string that will collect all the bits and parts that make up the page, while it's rendering (used to sit in several methods,
	                                 // now it's just used in ->render(), as per [2011-01-20 16:17:26])
	
	public $close_page   = true;     // add closing body & html tags
	protected $rendering = false;    // 
	
	// -- Constructor
	private function __construct() {
		// Go 'way, I'm changing!
	}
	
	//_________
	// page() /
	public static function page() {
		if (! self::$instance) {
			self::$instance = new HTML;
		}
		return self::$instance;
	}
	
	//___________________________  tag is there in case people mess up and confuse it with the body method.
	// add($key, $val, $tag='') /
	public function add($key, $val, $tag='') {
		if (! isset($this->$key)) {
			trigger_error("Trying to add unknown property '". var_export($key, 1) ."' to Class ". __CLASS__, E_USER_WARNING);
			return false;
		}
		if (strtolower($key) == 'body') {
			// trigger_error("Consider using ->body() to add .. ehr.. body parts. (Passing arguments on to that, now.)");
			return $this->body($val, $tag);
		}
		if (! is_array($val)) $val = array($val);
		foreach ($val as $item) {
			if ($item) {
				$this->{$key}[] = $item;
			}
		}
	}
	
	//__________________
	// set($key, $val) /
	public function set($key, $val) {
		if (! isset($this->$key)) {
			trigger_error("->set(): Trying to add unknown property '". var_export($key, 1) ."' to Class ". __CLASS__, E_USER_WARNING);
			return false;
		}
		$this->$key = $val;
	}
	
	//_____________________________________________
	// body($input, $tag=false, $overwrite=false) /
	public function body($input, $tag=false, $overwrite=false) {
		if (is_array($input)) {
			if (! count($input)) {
				trigger_error('->body() got empty content array.', E_USER_WARNING);
				return false;
			}
			$input = array_values($input); // ditch keys so we can pick items by number
			if (! isset($input[1])) $input[1] = array();
			$contents = array('template'=>$input[0], 'data'=>$input[1]);
			if (! file_exists($contents['template'])) {
				// Show a Notice, but carry on regardless, it could still be created, or a later call to ->body() can re-set it
				trigger_error('Template to use does not exist: '. var_export($template, 1));
			}
		}else{
			$contents = $input;
		}
		if ($this->rendering) {
			// $nested = true, so that the output is included in the current output_buffer, instead of being returned to the calling function
			$this->render_part($contents, True);
			return;
		}
		if ($tag!==false) {
			if (isset($this->body[$tag])) {
				if ($overwrite) {
					$this->body[$tag] = $contents;
				}else{
					trigger_error(get_class($this) .'->body(): Tag '. var_export($tag, 1) .' already in use, specify $overwrite=true if you want to do that.', E_USER_WARNING);
					return false;
				}
			}else{
				$this->body[$tag] = $contents;
			}
		}else{
			$this->body[] = $contents;
			$keys = array_keys($this->body);
			$tag = $keys[count($keys)-1];
		}
		return $tag;
	}
	
	//________________________
	// render($return=false) /
	public function render($return=false) {
		$this->rendering = true;
		$htmlents = 'htmlents';
		// Body Parts: we're doing these first, since some views may require additional stylesheets or script includes.
		$bodyparts = array();
		foreach($this->body as $part) {
			$bodyparts[] = $this->render_part($part);
		}
		// Head: get your style, script, jquery etc here.
		$this->html_out = $this->doctype."
<html".($this->xmlns ? " xmlns='$this->xmlns'" : '').">

<head>
	<meta http-equiv='content-type' content='$this->mimetype; charset=$this->charset'>
	<title>{$htmlents($this->title)}</title>\n";
		// any type of custom thing
		foreach($this->headertags as $tag) $this->html_out .= "\t$tag\n";
		// style
		foreach($this->stylesheet as $val) {
			$this->html_out .= "\t<link rel='stylesheet' type='text/css' href='$val'>\n";
		}
		if ($this->style) {
			$this->html_out .= "\t<style type='text/css'>\n";
			foreach ($this->style as $snippet) {
				$this->html_out .= "\t$style\n\n";
			}
			$this->html_out .= "\t</style>\n";
		}
		// script (includes, jquery, raw)
		foreach($this->js_include as $src) {
			$this->html_out .= "\t<script src='$src' type='text/javascript' charset='utf-8'></script>\n";
		}
		if ($this->jquery) {
			$this->html_out .= "\t<script type='text/javascript'>\n".
				"\t$(document).ready(function(){\n";
			foreach ($this->jquery as $snippet) {
				$this->html_out .= "\t$snippet\n\n";
			}
			$this->html_out .= "\t});\n\t</script>\n";
		}
		if ($this->javascript) {
			$this->html_out .= "\t<script type='text/javascript'>\n";
			foreach ($this->javascript as $script) {
				$this->html_out .= "\t$script\n\n";
			}
			$this->html_out .= "\t</script>\n";
		}
		$this->html_out .= "</head>\n\n<body ". join(' ', $this->bodytag) .">\n";
		// putting it all together..
		$this->html_out .= join('', $bodyparts);
		if ($this->close_page) $this->html_out .= "\n\n</body>\n</html>";
		$this->rendering = false;
		if ($return) return $this->html_out;
		echo $this->html_out;
	}
	
	//____________________________________
	// render_part($part, $nested=false) /
	function render_part($part, $nested=false) {
		if (is_array($part)) {
			// Copy the part to this object to avoid the $part variable being overwritten by the extract (and then not finding the template)
			// Not sure I like the copy part, but for a webpage the amounts of data being shuttled around can't be THAT humongous
			$this->part = $part;
			if (is_array($this->part['data'])) {
				// Here is where user's variables are created.
				// It would be nice if, for nested views, you could somehow make available a reference of this array (taking into account multi-nested etc).
				extract($this->part['data']);
			}else{
				$data = $this->part['data'];
			}
			// Catch contents of included files
			if (file_exists($this->part['template'])) {
				if ($nested) {
					// Inception! We're inside a View already, so just 'echo' the contents and it will be caught by the output buffer in the surrounding scope.
					include $this->part['template'];
				}else{
					ob_start();
					include $this->part['template'];
					return ob_get_clean();
				}
			}else{
				trigger_error('Template not found: '. var_export($this->part['template'], 1), E_USER_WARNING);
			}
		}else{
			return $part;
		}
	}
	
}


/* -- Log --------------------------------

[2011-01-20 16:08:01] Related to previous: render() now first handles the body parts, so that views /can/ still add headers. It's almost useful now!
[2011-01-18 04:10:50] ->render_body_part() became ->render_part(), no longer protected, returns chunk of HTML now instead of adding it to the ->html_out property
[2011-01-09 19:14:34] Merged ->view() and ->body() into ->body(), the first argument can now be a string (will be echoed) or an array(template, data).
                      Added render_body_part() and a property ->rendering, to enable "nested" views: inside a template file you can call $this->body(input)
                      again and it will render the input immediately.
[2011-01-05 16:06:15] Added file_exists() checks where appropriate, throwing notice & warning as needed
[2011-01-05 15:27:39] Added $this->close_page property (which made it easier to throw existing pages in there - $html where it's useful, then
											spit out the contents and continue with whatever the old page already had)
[2011-01-05 13:25:31] ->view() $data is now optional so you can add templates that should be parsed by PHP but have no data
[2011-01-05 13:07:19] Boom!
[2011-01-05 00:09:38] So, now it more or less works. Changed it into a static singleton thinger. Time to sleep.
[2011-01-04 23:07:32] Decided that was a bad plan, and those classes can reset the data by using tags, and overwrite.
[2011-01-04 23:04:20] Polished it up a little with a really dirty (IMHO) &$data trick in ->view(), which lets "ORM" classes that use this one
											change $data after they've added it, and it'll ->render() with the newer data.
[2010-12-12 20:34:35] Started. Finish it sometime..?

Todo: reconsider some method names, maybe add some shortcut methods ($this->title('My new shiny page!') etc)
Todo: the $messages array for errors and notifies and such could be part of this class. Or, there could be a more general way to add a data container, and
      some functions that know how to render it (or log it, or..), callbacks or extend class, .. ponder.
Todo: add template-stack in render_body_part(), check to avoid recursion. Maybe have object property to override this (logic in template could deal with it)

Todo: check for htmlents() values where needed

Todo? add HTTP headers method? then again, the regular PHP function for that works /fine/...

*/

?>