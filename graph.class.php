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
	var $values = array();
	var $line_margin_top    = 5; // percentage from highest value of graph/line to top of graph bounding box
	var $line_margin_bottom = 10;
	
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
	
	
	// -- plot_graph($color=0)
	function plot_graph($color=0) {
		if (! is_numeric($color)) $color = $this->intify($color);
		$this->min_val          = min($this->values);
		$this->max_val          = max($this->values);
		$this->delta_val        = ($this->max_val - $this->min_val) * (($this->line_margin_top + $this->line_margin_bottom + 100)/100); // height of graph in 'val', including margins
		$this->graph_bottom_val = $this->max_val - (($this->max_val - $this->min_val) * (1 + ($this->line_margin_bottom/100))); // value of 'val' at graph bottom
		$this->delta_px         = $this->getxy('y') - ($this->offy + $this->offy2);
		$this->graph_width_px   = $this->getxy('x') - ($this->offx + $this->offx2);
		$prev_vals              = array($this->offx, $this->val2px($this->values[0])); // initial x,y
		foreach($this->values as $i => $val) {
			if (! $i) continue;
			$val_x = $this->offx + ($i * $this->graph_width_px / (count($this->values)-1));
			$val_y = $this->val2px($val);
			$this->line($prev_vals[0], $prev_vals[1], $val_x, $val_y, $color);
			$prev_vals = array($val_x, $val_y);
		}
	}
	
	function val2px($val) {
		$val_px = ($this->delta_px - ((($val - $this->graph_bottom_val) / $this->delta_val) * $this->delta_px)) + $this->offy;
		return $val_px;
	}
	
	
	function set_values($values) {
		if (! is_array($values)) {
			$values = preg_split('/[^0-9.]/', $values); // who knows, it might work.
		}
		$this->values = $values;
	}
	
	
	function __destruct() {
		parent::__destruct();
	}
	
}

/* -- Log --------------------------------

[2011-04-18 22:06:54] added set_values()
[2011-04-15 23:37:57] made second constructor argument optional in the silly case someone wants to use a background image for the graph.
                      (Also, been bloody ages..)



*/

?>