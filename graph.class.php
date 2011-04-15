<?php

require_once 'imageplus.class.php';

class Graph extends ImagePlus {
	
	/** -- Methods -------------------------
	 * 
	 * __construct($x, $y, $offx, $offy=null, $offx2=null, $offy2=null)    
	 * draw_bounding_box($color=0)                                         
	 * 
	 * 
	**/
	
	var $offx;    // offsets for inner graph area
	var $offy;
	var $offx2;
	var $offy2;
	var $img;
	
	function __construct($x, $y=0, $offx=10, $offy=null, $offx2=null, $offy2=null) {
		parent::__construct($x, $y);    // constructs the image
		$this->offx  = (int) $offx;     // offset values
		$this->offy  = ($offy  != null) ? (int) $offy  : $this->offx;
		$this->offx2 = ($offx2 != null) ? (int) $offx2 : $this->offx;
		$this->offy2 = ($offy2 != null) ? (int) $offy2 : $this->offy;
	}
	
	
	function draw_bounding_box($color=0) {
		if (! is_numeric($color)) $color = $this->intify($color);
		list($x, $y) = $this->getxy();
		imagerectangle($this->img, $this->offx, $this->offy, ($x - $this->offx2) - 1, ($y - $this->offy2) - 1,  $color);
	}
	
	
	function __destruct() {
		parent::__destruct();
	}
	
}

/* -- Log --------------------------------

[2011-04-15 23:37:57] made second constructor argument optional in the silly case someone wants to use a background image for the graph.
                      (Also, been bloody ages..)



*/

?>