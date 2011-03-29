<?php
/*
 * Image - a (very basic) image handling class in PHP
 * Written by Jesse Lang - http://jesselang.com/
 * Version 0.01a - Released May 5th, 2005
 * 
 * If you make modifications that may be useful to others, please send 
 * them to <j |at| jgdataworks.com>.
 * --
 * [2006-08-28 23:39:54]
 * Modified by Maarten Spreij (gmail.com@mspreij) - http://mechintosh.com/
 * Version 0.1a (why not)
 * Last update: [2009-04-06 14:12:10]
 * ---------------------------------------
 * 
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

/*
-- Sample code
 
  if ($I = new Image('my_image.jpg')) {
    $I->scale_image(300);
    $I->save('my_new_image.jpg'); # saves scaled image to disk
  }else{
    echo 'an error occurred. (yeah. dude.)';
  }
-- Some more for ->scale_image();
  $I->scale_image(300)       >> scales the longest side to 300 pixels, scales other side accordingly
  $I->scale_image(',175')    >> scales the *height* to 175 pixels, scales width accordingly
  $I->scale_image('200,300')       >> and
  $I->scale_image(array(200, 300)) >> scale width to 200, height to 300 pixels
  $I->scale_image('1000,100') >> if the original was a square image, it will sit (still square) centered,
                                 with 450 px of $this->bgcolor on either side.
  $I->scale_image('1000,100', true) >> stretch it to fill up the canvas

-- Functions --------------------------
 Image($mixed = 0)                      -- Constructor
 ImageFromFile($f)                      -- 
 ImageFromData($d)                      -- 
 save($filename='', $print=0)           -- overwrites current file unless different filename specified
 scale_image($size, $stretch=false)     -- see examples above
   $size is in pixels and can be given as an [$x, $y] array, or as a string.
   if the string contains a space or comma,
     the first two values are interpreted as ints and returned.
     if one of those is 0, it is calculated as scaled from the current dimensions, so giving ',200' will enforce
     a height of 200 pixels and return a properly scaled width along with it.
   else the string is taken as an integer pointing at the longest side, and the other side is scaled accordingly.
   if both x and y were given, but the proportions aren't the same as the original, the image will be scaled smaller,
   and the extra space filled up with $this->bgcolor
 getxy($side='')                        -- returns x, y or both (in which case as array(x, y)) of image
 calc_sizes($width, $height, $string)   -- does the $size fu described under scale_image($size)
 getErrors()                            -- returns $errors array
 __clone()                              -- called after object is cloned (PHP 5)
 destroy()                              -- register_shutdown_function thingie, frees memory associated with image resource
*/

class Image {
	var $data;          # raw image data
	var $filename;
	var $img;           # image resource
	var $quality = 100; # JPEG image quality from 0 to 100, 100 being best
	var $errors = array(); # array with, uh.. - errors. not that anything ever goes wrong. of course.
	var $bgcolor = 16777215; # bgcolor int. white: 16777215 red: 16711680 blue: 255 green: 65280
	var $stretch_check = false; # this is set to true if both X and Y were given, for resizing the image.
	
	### Constructor ___
	# Image($mixed=0) /
	function Image($mixed=0) {
		if (! $this->ImageFromFile($mixed)) {
			if (! $this->ImageFromData($mixed)) {
				// ehr... yeah. Hmph. Panic?
			}
		}
	}
	
	#____________________
	# ImageFromFile($f) /
	function ImageFromFile($f) {
		if ( ($fp = fopen($f, 'rb')) || ($fp = fopen($_SERVER['DOCUMENT_ROOT'] . $f, 'rb')) ) {
			$this->data = fread($fp, filesize($f));
			$this->filename = $f;
			return $this->ImageFromData($this->data);
		}else{
			return false;
		}
		return true;
	}
	
	#____________________
	# ImageFromData($d) /
	function ImageFromData($d) {
		if ($this->img = imagecreatefromstring($d)) {
			register_shutdown_function(array($this, 'destroy'));
			return true;
		}
		return false;
	}
	
	#_______________________________
	# save($filename='', $print=0) /
	function save($filename='', $print=0) {
		if (empty($filename)) {
			if (empty($this->filename)) {
				return false;
			}else{
				$filename = $this->filename;
			}
		}
		$filetype = strtolower(substr(strrchr($filename, '.'), 1));
		$types = imagetypes();
		switch ($filetype) {
			case 'jpg':
			case 'jpeg':
				if ($types & IMG_JPG) {
					if ($print) {
						header("Content-Type: image/jpeg\n\n");
						return imagejpeg($this->img);
					}else{
						return imagejpeg($this->img, $filename, $this->quality);
					}
				}
				break;
			case 'png':
				if ($types & IMG_PNG) {
					if ($print) {
						header("Content-Type: image/png\n\n");
						return imagepng($this->img);
					}else{
						return imagepng($this->img, $filename);
					}
				}
				break;
			case 'gif':
				if ($types & IMG_GIF) {
					if( $print ) {
						header("Content-Type: image/gif\n\n");
						return imagegif($this->img);
					}else{
						return imagegif($this->img, $filename);
					}
				}
				break;
			default:
				$this->errors[] = "'$filetype' is not a supported image type.";
				return false;
		}
	}
	
	#_____________________________________
	# scale_image($size, $stretch=false) /
	function scale_image($size, $stretch=false) {
		list($width, $height) = $this->getxy();
		if (! $sizes = $this->calc_sizes($width, $height, $size)) {
			$this->errors[] = 'Scaling failed.';
			return false;
		}
		list($x, $y) = $sizes;
		$dest_x = $dest_y = 0;
		if ($this->stretch_check && (! $stretch)) {
			# width and height were explicitly specified but $stretch is false, re-set image so it won't be distorted
			$orig_proportion = $width / $height;
			$new_proportion = $x / $y;
			if ($orig_proportion > $new_proportion) {
				# new canvas is stretched vertically, $x stays $x but $y must shrink to remain scaled to $x
				$y = round($y * ($new_proportion / $orig_proportion));
				$dest_y = round(($sizes[1] - $y) / 2);
			}elseif ($orig_proportion < $new_proportion) {
				# new canvas is stretched horizontally, $y stays $y but $x must become smaller to fit in properly
				$x = round($x * ($orig_proportion / $new_proportion));
				$dest_x = round(($sizes[0] - $x) / 2);
			}
		}
		$newImg = imagecreatetruecolor($sizes[0], $sizes[1]);    # create temp image
		imagefill($newImg, 0, 0, $this->bgcolor);
		# dest resource, source resource, dst_x, dst_y,  src_x, src_y,  dst_w, dst_h,  src_w, src_h
		imagecopyresampled($newImg, $this->img, $dest_x, $dest_y, 0, 0, $x, $y, $width, $height);
		imagedestroy($this->img);                  # destroy original
		$this->img = imagecreatetruecolor($sizes[0], $sizes[1]); # carry temp image over to original's var
		imagecopy($this->img, $newImg, 0, 0, 0, 0, $sizes[0], $sizes[1]);
		imagedestroy($newImg);                     # destroy temp image
		return true;
	}
	
	#__________________
	# getxy($side='') /
	function getxy($side='') {
		if (strtolower($side) == 'x') return imagesx($this->img);
		if (strtolower($side) == 'y') return imagesy($this->img);
		return array(imagesx($this->img), imagesy($this->img));
	}
	
	# tries to turn $string into two new values for x and y. string formats are checked for delimiters (whitespace, comma) or returned as int,
	# resizing the smaller current side to scale. arrays have their first two items returned as int.
	#_______________________________________
	# calc_sizes($width, $height, $string) /
	function calc_sizes($width, $height, $string) {
		if (is_array($string)) {
			if (count($string) >= 2) {
				$x = (int) $string[0];
				$y = (int) $string[1];
				if ($x && $y) {
					$this->stretch_check = true;
					return array($x, $y);
				}
				if ($x || $y) {
					$x = $y = max($x, $y);
					return array($x, $y);
				}
				$this->errors[] = "Scaling error, supplied size array had no valid items.";
				return false;
			}else{
				# assume it was an array with a single non-array value, and let the rest of the function give it a try
				$this->errors[] = "If scaling failed, the supplied size argument being a single-item array might have been at fault..";
				$string = $string[0];
			}
		}
		if (is_string($string) or is_numeric($string)) {
			# issa string, return parts if there are delimiters
			if (preg_match('/[\s,]+/', $string)) {
				$string = preg_split('/[\s,]+/', $string);
				$x = (int) $string[0];
				$y = (int) $string[1];
				if (! ($x || $y)) {
					$this->errors[] = "scale_image() got a silly size string: '$string' (both int values -> 0)";
					return false; # that made no sense!
				}
				if ($x && $y) $this->stretch_check = true;
				if (! $x) $x = round(($y/$height) * $width);
				if (! $y) $y = round(($x/$width) * $height);
				return array($x, $y);
			}
			# it's a single value, turn to int, use as largest side
			$int = (int) $string;
			if (! $int) { // whoops, value made no sense
				$this->errors[] = "scale_image() got a silly size string: '$string' (int value -> 0)";
				return false;
			}
			# now see which side we need to apply it to, width or height
			if ($width > $height) {
				return array($int, round(($height/$width) * $int)); # it's width, calculate new height
			}elseif($width < $height) {
				return array(round(($width/$height) * $int), $int); # it's height, calculate new width
			}else{
				return array($int, $int); # original is square
			}
		}else{
			$this->errors[] = "scale_image() got a silly size value or type: ". var_dump_string($string);
			return false;
		}
	}
	
	#______________
	# getErrors() /
	function getErrors() {
		return $this->errors;
	}
	
	#____________
	# __clone() /
	function __clone() {
		$this->img = imagecreatefromstring($this->data);
	}
	
	#____________ PHP4 Destructor
	# destroy() /
	function destroy() {
		return @imagedestroy($this->img);
	}
	
	# End of Class! \o/
}

/* -- Log --------------------------------
[2009-04-06 14:12:10] added __clone() method.
[2006-12-12 14:23:37] getxy() -> getxy($side=''), so it can return either x or y as well
[2006-09-01 00:56:45] w00t. scaling and stretching and such worked out.
[2006-08-31 17:47:57] scale_image() would stretch the image if both width and height were specified, we don't want this,
                      therefor we add the $stretch argument which defaults to 0 (false). We only actually need to test
         for it if both width and height were specified, which atm only the calc_sizes function can "know". So it might
         just do $this->stretch_check = true;

Todo: Might want to clean up code a little, rename variables, // instead of # and such.
Todo: cache x,y dimensions of image in getxy(), reset after scaling.
------------------------------------------
Done: 
- Return statements in constructor, those should not be needed/wanted, right? Right

*/

?>