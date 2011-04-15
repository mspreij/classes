<?php

/*
	This EXTENDS image.class.php
*/

require_once 'image.class.php';

class ImagePlus extends Image {
	
	/** -- Methods -------------------------
	 * 
	 * __construct($x, $y=null)                                -- $x/$y are width/height, or $x is width&height, or $x is image filename
	 * fill($color)                                            
	 * tttext($text, $x, $y, $color=0, $size=12, $angle=0)     
	 * textbox($size=12, $angle=0, $text)                      -- ehr.. similar to ttsize, I guess.. merge someday
	 * ttsize ($text, $size=12, $full=0)                       
	 * text($string, $x, $y, $color=0, $size=2)                
	 * setcolor($name, $value)                                 
	 * draw_border($color=0)                                   
	 * rect($x, $y, $x2, $y2, $color=0, $filled=0)             
	 * rect2($coords, $color=0, $filled=0)                     
	 * circle($x, $y, $width, $height, $color=0, $filled=0)    
	 * poly($coords, $color=0, $fill=0)                        -- coords array has two items per point (x0, y0, x1, y1, ...)
	 * line($x, $y, $x2, $y2, $color=0)                        
	 * point($x, $y, $color=0)                                 
	 * select($x, $y, $x2, $y2)                                -- select an area on which the image operations that follow should operate
	 * deselect([$x, $y, $x2, $y2])                            -- deselect everything, or subtract from the current selection
	 * intify(&$color)                                         -- stomps input into color integer
	 * draw()                                                  
	 * __call($name, $args)                                    -- allows to call php image functions, skip the image resource though (first arg), the class adds that
	 * __destruct()                                            
	 * 
	 * 
	**/
	
	
	var $img;     # image resource
	var $x = 100;
	var $y = 100;
	var $bgcolor   = 0xFFFFFF;
	var $antialias = true;
	var $fontfile = 'fonts/arialblack.ttf';
	var $selected = false; // active selection flag
	var $copy;
	var $color_list = array(
		'black'   => 0x000000,
		'silver'  => 0xC0C0C0,
		'gray'    => 0x808080,
		'white'   => 0xFFFFFF,
		'maroon'  => 0x800000,
		'red'     => 0xFF0000,
		'purple'  => 0x800080,
		'fuchsia' => 0xFF00FF,
		'green'   => 0x008000,
		'lime'    => 0x00FF00,
		'olive'   => 0x808000,
		'yellow'  => 0xFFFF00,
		'navy'    => 0x000080,
		'blue'    => 0x0000FF,
		'teal'    => 0x008080,
		'aqua'    => 0x00FFFF);
	
	//______________________
	// __construct($x, $y) /
	function __construct($x, $y=null) {
		if ($y or is_int($x)) {
			if (! $y) $y = $x;
			$this->x = $x;
			$this->y = $y;
			$this->img = imagecreatetruecolor($this->x, $this->y);   # create image
			imagefill($this->img, 0, 0, $this->bgcolor);             # fill in
		}else{
			if (is_string($x)) {
				parent::Image($x);
				$this->x = imagesx($this->img);
				$this->y = imagesy($this->img);
			}
		}
		if (function_exists('imageantialias')) imageantialias($this->img, $this->antialias);            # anti-alias
		imagealphablending($this->img, true);                    # support alpha channels
	}
	
	// -- fill($color)
	function fill($color, $coords = array(0,0)) {
		if (! is_numeric($color)) $color = $this->intify($color);
		$this->bgcolor = (int) $color;
		if (! is_array($coords)) $coords = array((int) $coords, (int) $coords);
		list ($x, $y) = $coords;
		imagefill($this->img, $x, $y, $this->bgcolor);
	}
	
	// -- tttext($text, $x, $y, $color=0, $size=12, $angle=0)
	function tttext($text, $x, $y, $color=0, $size=12, $angle=0) {
		if (! is_numeric($color)) $color = $this->intify($color);
		imagettftext($this->img, (float) $size, $angle, (int) $x, (int) $y, $color, $this->fontfile, $text);
	}
	
	// -- textbox($size=12, $angle=0, $text) Todo: merge this with ttsize() below
	function textbox($size=12, $angle=0, $text) {
		return imageftbbox($size, $angle, $this->fontfile, $text);
	}
	
	// -- ttsize ($text, $size=12, $full=0)
	function ttsize ($text, $size=12, $full=0) {
		$res = imagettfbbox((float) $size, 0, $this->fontfile, $text);
		if ($full) return $res;
		return array($res[2]-$res[0], $res[1]-$res[7]);
	}
	
	// -- text($string, $x, $y, $color=0, $size=2)
	function text($string, $x, $y, $color=0, $size=2) {
		if (! is_numeric($color)) $color = $this->intify($color);
		imagestring($this->img, (int) $size, $x, $y, $string, $color);
	}
	
	// -- setcolor($name, $value)
	function setcolor($name, $value) {
		$this->color_list[$name] = (int) $value;
	}
	
	// -- draw_border($color=0)
	function draw_border($color=0) {
		if (! is_numeric($color)) $color = $this->intify($color);
		list($x, $y) = $this->getxy();
		imagerectangle($this->img, 0, 0, $x-1, $y-1, $color);
	}
	
	// -- rect($x, $y, $x2, $y2, $color=0, $filled=0)
	function rect($x, $y, $x2, $y2, $color=0, $filled=0) {
		if (! is_numeric($color)) $color = $this->intify($color);
		if ($filled) {
			return imagefilledrectangle($this->img, $x, $y, $x2, $y2, $color);
		}else{
			return imagerectangle($this->img, $x, $y, $x2, $y2, $color);
		}
	}
	
	// -- rect2($coords, $color=0, $filled=0)
	function rect2($coords, $color=0, $filled=0) {
		list($x, $y, $x2, $y2) = split('[,\s]+', $coords);
		return $this->rect($x, $y, $x2, $y2, $color, $filled);
	}
	
	// -- circle($x, $y, $width, $height, $color=0, $filled=0)
	function circle($x, $y, $width, $height, $color=0, $filled=0) {
		if (! is_numeric($color)) $color = $this->intify($color);
		if ($filled) {
			return imagefilledellipse($this->img, $x, $y, $width, $height, $color);
		}else{
			return imageellipse($this->img, $x, $y, $width, $height, $color);
		}
	}
	
	// -- poly($coords, $color=0, $fill=0)
	function poly($coords, $color=0, $fill=0) {
		$this->intify($color); // <@_Lasar> And slackify the shit out of the day.
		if ($fill) {
			return imagefilledpolygon($this->img, $coords, count($coords)/2, $color);
		}else{
			return imagepolygon($this->img, $coords, count($coords)/2, $color);
		}
	}
	
	// -- line($x, $y, $x2, $y2, $color=0)
	function line($x, $y, $x2, $y2, $color=0) {
		if (! is_numeric($color)) $color = $this->intify($color);
		return imageline($this->img, $x, $y, $x2, $y2, $color);
	}
	
	// -- point($x, $y, $color=0)
	function point($x, $y, $color=0) {
		if (! is_numeric($color)) $color = $this->intify($color);
		imagesetpixel($this->img, $x, $y, $color);
	}
	
	// -- select($x, $y, $x2, $y2)
	function select($x, $y, $x2, $y2) {
		if (! $this->selected) { // first selection. create new image resource, copy current image to it, set transparent color
			$this->copy = new ImagePlus($this->x, $this->y); // nested image objects, fun fun (but it allows reusing our methods on the copy)
			imagecopymerge($this->copy->img, $this->img, 0, 0, 0, 0, $this->x, $this->y, 100);
			$this->copy->trans = imagecolorallocatealpha($this->copy->img, 0, 0, 0, 127); // yep, it's see-through black
			imagealphablending($this->copy->img, false);                                  // (with alphablending on, drawing transparent areas won't really do much..)
			imagecolortransparent($this->copy->img, $this->copy->trans);                  // somehow this doesn't seem to affect actual black areas that were already in the image (phew!)
			$this->selected = true;
		}
		$this->copy->rect($x, $y, $x2, $y2, $this->copy->trans, 1); // Finally erase the defined area from the copy
	}
	
	// -- deselect()
	function deselect() {
		if (! $this->selected) return false;
		if (func_num_args() == 4) { // deselect an area from the current selection
			list($x, $y, $x2, $y2) = func_get_args();
			imagecopymerge($this->copy->img, $this->img, $x, $y, $x, $y, $x2-$x, $y2-$y, 100);
		}else{ // deselect everything, draw the perforated copy back over the original
			imagealphablending($this->img, true);
			imagecopymerge($this->img, $this->copy->img, 0, 0, 0, 0, $this->x, $this->y, 100); // copy the copy back
			$this->copy->__destruct();
			$this->selected = false;
		}
	}
	
	// -- intify(&$color)
	function intify(&$color) {
		if (is_numeric($color)) return;
		if (in_array($color, array_keys($this->color_list))) {
			$color = $this->color_list[$color];
		}else{
			$color = 0;
		}
		return $color;
	}
	
	// draw()
	function draw() {
		if ($this->selected) {
			$this->deselect();
		}
		header('Content-type: image/png');
		imagepng($this->img); # (whaddayamean "image formats" ?)
		$this->__destruct();
	}
	
	//_______________________ This kicks ass.
	// __call($name, $args) /
	function __call($name, $args) {
		array_unshift($args, $this->img);
		return call_user_func_array($name, $args);
	}
	
	//_______________ This kicks the image's ass.
	// __destruct() /
	function __destruct() {
		@imagedestroy($this->img);
	}
	
}

/* -- Log --------------------------------

[2011-04-15 23:16:08] Patched intify() to make it have a RETURN VALUE (these are useful).
[2011-04-15 22:48:16] Renamed class, removed color2int.
[2009-10-04 23:36:42] Added textbox($size=12, $angle=0, $text) then found ttsize($text, $size=12, $full=0) already existed, must merge someday..
[2009-06-29 04:12:41] __call() *returns* value (this could be useful, yanno?)
[2009-06-28 02:17:50] done adding select() & deselect().
[2009-05-07 00:57:12] now EXTENDS image.class.php for easy reading and saving files.
[2009-05-06 15:59:16] draw() now calls $this->__destruct(); instead of imagedestroy(), I think this is allowed (and maybe more betterer)
[2009-05-06 15:34:42] added circle
[2008-12-17 11:42:36] experimenting with $coords vs "$x, $y, $x2, $y2, .." [*1]
[2008-12-17 11:38:42] set alpha blending is true by default

Todo: add function head comments, update f list, etc
Todo: add some helper methods to deal with alpha stuff ("60% red")
Todo: some of these methods are getting too many optional arguments.. pass assoc array with defaults ('foo'=>$this->foo, or 'foo'=>$this->settings['foo']) in the function body?
Todo: select/deselect is nice. Next up: layers?
Todo: draw($format) for gif, jpg etc?
Todo: Catch errors, print them as text in the image. If no image can be created, create one anyway large enough to hold the error text. If the error-image can't be
      created, cry and stamp your feet.

------------------------------------------

Done: fase out color2int, replaced by intify()

Idea: make $fill[ed] a float, for alpha?

New image:
200, 300  -> $x, $y
1000      -> $x, $y => 1000, 1000
image.png -> read in file
200+chars,LikelyBinaryCode -> interpret as image data

1: func_get_args() could be useful here but will need type checking or somesuch if there are more optional arguments added (since it's hard
to distinguish between 4 coordinates and 1 $coords + 3 optional args). use assoc arrays for optional argument passing?

*/

?>