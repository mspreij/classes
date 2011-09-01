<?php
class recobject {
	
/** -- Functions -------------------------
 * 
 * recobject($table, $fields, $id=0, $clause=false)     -- Constructor: string $table, array $fields, int $id, array $clause
 * select()                                             -- Fetches row, sets $this->fields items with update_object(), returns row.
 * set_clause($array)                                   -- Adds assoc array (field/value) to use in all queries [2008-02-07 12:14:44]
 * insert($extra='')                                    -- Gets data with get_data(), inserts record, calls select() to update object, returns id or false.
 * update($extra='')                                    -- Gets data with get_data(), updates record, calls select() to update object.
 * validate($data, $type)                               -- 
 * update_object($data)                                 -- fills $this->fields array with passed assoc array (prime candidate for private method in PHP5)
 * get_data($type='')                                   -- Grabs $_POST/GET value (if isset()) for each key in $fields, returns assoc array.
 * hook($name, $function)                               -- register $function to run in $name -> method name. $function can also be the result of create_function(),
                                                           or an anonymous function (closure) in PHP 5.3+. AND it accepts array($object or 'object_name', 'method')
                                                           as $function, too.
 * run_hooks($hook, &$data)                             -- Called by various functions, allows "extending" them without extending the class
 * delete()                                             -- Just deletes the record. Extend class to deal with subrecords, files etc
 * get_list($options='')                                -- what it says, returns array. Also runs update_object hook on each item (as of this writing)
 * styledText($string, $color='#000')                   -- "meh"
 * reset()                                              -- returns the object to a pre-$id state
 * 
**/
	
	var $table;                   // string,       tablename
	var $fields;                  // array,        fields[name] => value
	var $id;                      
	var $clause;                  // assoc array,  field=>'value'
	var $clause_string;           // " AND `foo` = 'ba\'ar'", generated by the set_clause() method, for use in Select, Update, Delete SQL
	var $debug            = false;  
	var $show_errors      = 1;      // 1: errors, 2: warnings, 3: notices, 0: fail silently.
	var $logging          = true;   // log events to logbook table
	var $record_created   = "Record created.";
	var $record_updated   = "Record updated.";
	var $validation_error = '';
	
	// -- Constructor -----------------------
	function recobject($table, $fields, $id=0, $clause=false) {
		// set debug to constant DEBUG, if any
		if (defined('DEBUG')) $this->debug = (int) DEBUG;
		$this->table = $table;
		if (is_array($fields)) {
			foreach($fields as $field) $this->fields[$field] = '';
		}else{
			echo '<span style="color: red;">recobject class error: $fields should be array in constructor</span><br />';
		}
		$this->id = $id;
		if ($clause) $this->set_clause($clause);
		if (! ($table && $fields)) echo '<span style="color: red;">Missing table or fields in constructor</span><br />';
		if ($this->id) {
			$this->select();
		}
	}
	
	//______________________
	// select($id = false) /
	function select($id = false) {
		global $messages;
		if ($id) $this->id = $id;
		if (! $this->id) {
			$messages[] = $this->styledText("Can't select data, no id given.", 'red');
			return false;
		}
		if (is_array($this->id)) {
			$sql = "SELECT id, `". join('`, `', array_keys($this->fields)) ."` FROM $this->table WHERE ";
			foreach ($this->id as $key => $val) {
				$sql .= "`".mysql_real_escape_string($key)."` = '".mysql_real_escape_string($val)."' AND ";
			}
			$sql = substr($sql, 0, -4);
		}else{
			$sql = "SELECT `". join('`, `', array_keys($this->fields)) ."` FROM $this->table WHERE id = '$this->id'";
		}
		if ($this->clause) $sql .= $this->clause_string; // clause
		if ($this->debug) echo $this->styledText($sql.'<br>', 'blue');
		if ($res = mysql_query($sql)) {
			if ($row = mysql_fetch_assoc($res)) {
				if (is_array($this->id)) $this->id = array_shift($row);
				$this->update_object($row);
				return $this->fields; // [2010-12-20 17:03:59]
			}else{
				$this->id = false; // the query worked, clearly there is No Such Record.
				$messages[] = $this->styledText(get_class($this) .": Record not found..", 'red');
				return false;
			}
			return $row;
		}else{
			$messages[] = $this->styledText("Select error: ". mysql_error(), 'red');
			return false;
		}
	}
	
	//_____________________
	// set_clause($array) /
	function set_clause($array) {
		if (is_array($array)) { //  && count($array)
			$this->clause = $array;
			$this->clause_string = ''; // reset if it was changed
			foreach($this->clause as $field => $value) $this->clause_string .= " AND `$field` = '".mysql_real_escape_string($value)."'";
			return true;
		}else{
			$messages[] = $this->styledText('recobject class error, clause property should be a (non-empty) array.<br>', 'red');
			return false;
		}
	}
	
	//____________________
	// insert($extra='') /
	function insert($extra='') {
		global $messages;
		$data = $this->get_data('insert');
		if (is_array($extra)) foreach($extra as $key => $val) $data[$key] = $val;
		if ($data) {
			$this->run_hooks('pre_insert', $data);
			// Validate
			if (! $this->validate($data, 'insert')) {
				$messages[] = $this->styledText("Failed to insert, invalid data: ". $this->validation_error, '#f80');
				return False;
			}
			if ($this->clause) $data = array_merge($data, $this->clause); // clause
			foreach ($data as $key => $value) {
				if (is_array($value)) {
					$data[$key] = json_encode($value);
					if ($this->show_errors > 1) $messages[] = $this->styledText("Warning: ->insert(): json_encoded value for field '$key' (was an array). It's better to handle this in a get_data() hook.", '#f80');
				}
			}
			$sql = "
				INSERT INTO $this->table (`". join('`, `', array_keys($data)) ."`)
				VALUES ('". join("', '", array_map('mysql_real_escape_string', $data)) ."')";
			if ($this->debug) echo $this->styledText($sql.'<br>', 'green', 'p');
			if ($res = mysql_query($sql)) {
				$messages[] = $this->record_created;
				$this->id = mysql_insert_id();
				$this->select();
				if ($this->logging) writelog("Insert\n$sql", -1, 'cms');
				$this->run_hooks('post_insert', $data);
				return $this->id;
			}else{
				$messages[] = $this->styledText("Insert error: ". mysql_error(), 'red');
				writelog("insert error:  $sql\n\n". mysql_error());
				return false;
			}
		}else{
			$messages[] = $this->styledText("No data", 'red');
			return false;
		}
	}
	
	//____________________
	// update($extra='') /
	function update($extra='') {
		global $messages;
		$data = $this->get_data('update');
		if (is_array($extra)) foreach($extra as $key => $val) $data[$key] = $val;
		if ($data) {
			$this->run_hooks('pre_update', $data);
			// Validate
			if (! $this->validate($data, 'update')) {
				$messages[] = $this->styledText("Failed to update, validation error: ". $this->validation_error, '#f80');
				return False;
			}
			$sql = "UPDATE $this->table SET\n";
			foreach($data as $key => $value) {
				if (is_array($value)) {
					$value = json_encode($value);
					if ($this->show_errors > 1) $messages[] = $this->styledText("Warning: ->update(): json_encoded value for field '$key' (was an array). It's better to handle this in a get_data() hook.", '#f80');
				}
				$sql .= "`$key` = '". mysql_real_escape_string($value) ."', ";
			}
			$sql = substr($sql, 0, -2) ." WHERE id = '$this->id'";
			if ($this->clause) $sql .= $this->clause_string; // clause
			if ($this->debug) echo $this->styledText($sql.'<br>', '#C60');
			if ($res = mysql_query($sql)) {
				$messages[] = $this->record_updated;
				$data = $this->select();
				if ($this->logging) writelog("Update\n$sql", -1, 'cms');
				$this->run_hooks('post_update', $data);
				return true;
			}else{
				$messages[] = $this->styledText("Update error: ". mysql_error(), 'red');
				return false;
			}
		}else{
			$messages[] = $this->styledText("No data", 'red');
			return false;
		}
	}
	
	//_________________________
	// validate($data, $type) /
	function validate($data, $type) {
		// $this->validation_error = '...';
		return true;
	}
	
	//_______________________ -- private
	// update_object($data) /
	function update_object($data) {
		$this->run_hooks('update_object', $data);
		foreach($data as $key => $value) {
			$this->fields[$key] = $value;
		}
	}
	
	//_____________________
	// get_data($type='') /
	function get_data($type='') {
		global $messages;
		$data = array();
		$_request = array_merge($_GET, $_POST); // [2010-04-01 20:35:02]
		foreach(array_keys($this->fields) as $field) {
			if (isset($_request[$field])) {
				$data[$field] = $_request[$field]; // [2009-11-21 17:42:42]
			}
		}
		$this->run_hooks('get_data', $data);
		return $data;
	}
	
	//_________________________ register custom functions, added [2008-11-15 19:05:38]
	// hook($name, $function) /
	function hook($name, $function) {
		global $messages;
		$this->hooks[$name][] = $function;
		// that's the meat of it; now we'll just check if it was useful, and throw warnings/errors otherwise.
		if (is_string($function)) {
			if (! function_exists($function)) {
				if ($this->show_errors > 1) {
					$messages[] = styledText("Warning: hook function '$function' is not defined!<br />", '#f80');
				}
			}
		}elseif(is_array($function)) {
			if (count($function)==2) {
				if (! method_exists($function[0], $function[1])) {
					$messages[] = styledText("Error: hook method '". $function[1] ."' is not defined in Class '".(is_string($function[0]) ? $function[0] : get_class($function[0]))."'!<br />", '#f80');
				}
			}else{
				$messages[] = styledText(get_class($this) ."->hook() parameter error: [object,method] array should have exactly 2 items.", '#f80');
			}
		}elseif(is_object($function) && strtolower(get_class($function)) == 'closure'){
			// cool?
		}else{
			$messages[] = styledText(get_class($this) ."->hook() did not expect to get a function of type '". gettype($function) ."', there. Try string or array (for methods).", '#f80');
			return false; // that made no sense.
		}
		// Special case: hooks are only added once the record is initialized - and selected. update_object() is called inside the select method.
		// So, we re-fetch it and let it run the hook stuff.
		if ($name == 'update_object') {
			if ($this->id) $this->select();
		}
	}
	
	//___________________________ [2009-11-22 01:20:09]
	// run_hooks($hook, &$data) /
	function run_hooks($hook, &$data) {
		global $messages;
		if (isset($this->hooks[$hook])) {
			foreach ($this->hooks[$hook] as $function) {
				if (is_string($function)) {
					if (function_exists($function)) {
						$data = $function($data);
					}else {
						$messages[] = $this->styledText("Error: could not run hook function '$function': not defined.<br />", 'red');
					}
				}elseif(is_array($function)) {
					if (count($function)==2) {
						if (method_exists($function[0], $function[1])) {
							$data = $function[0]->$function[1]($data);
						}else{
							$messages[] = $this->styledText("Error: could not run hook method '". $function[1] ."' from Class '".(is_string($function[0]) ? $function[0] : get_class($function[0]))."'!<br />", '#f80');
						}
					}else{
						$messages[] = $this->styledText(get_class($this) ."->run_hooks() parameter error: [object,method] array should have exactly 2 items.", '#f80');
					}
				}elseif(is_object($function) && strtolower(get_class($function)) == 'closure') {
					$data = $function($data);
				}
			}
		}
	}
	
	//___________
	// delete() /
	function delete() {
		global $messages;
		$post_delete_data = '';
		$sql = "DELETE FROM $this->table WHERE id = '$this->id'";
		if ($this->clause) $sql .= $this->clause_string;
		if ($this->debug) echo $this->styledText($sql, 'purple');
		if (isset($this->hooks['post_delete']) && $this->hooks['post_delete'] or $this->logging) {
			$post_delete_data = fetch_row("SELECT * FROM $this->table WHERE id = '$this->id'");
		}
		if ($this->logging) {
			$log  = "Delete\n$sql\nWas:";
			$log .= print_r($post_delete_data, true);
		}
		$res = mysql_query($sql);
		if ($res) {
			$this->run_hooks('post_delete', $post_delete_data);
			if ($this->logging) writelog($log, -1, 'backup');
			$this->id = false;
			foreach ($this->fields as $key => $value) $this->fields[$key] = null;
			// foreach ($this->fields as $key => &$value) $value = null;
			// unset($value);
		}else{
			writelog('Failed query: '. $log, 2, 'cms');
		}
		return $res;
	}
	
	
	//________________________
	// get_list($options='') /
	function get_list($options='') {
		global $messages;
		if (is_array($options)) {
			extract($options);
		}
		// put together query:
		$sql = "SELECT id, "; // you get id for FREE!
		foreach(array_keys($this->fields) as $field) $sql .= "`$field`, ";
		$sql = substr($sql, 0, -2) ." FROM `$this->table`";
		if (isset($where))   $sql .= " WHERE $where";
		if ($this->clause)   {
			$sql .= (isset($where) ? $this->clause_string : ' WHERE '.substr($this->clause_string, 4)); // clause, copied from ->select
		}
		if (isset($groupby)) $sql .= " GROUP BY $groupby";
		if (isset($orderby)) $sql .= " ORDER BY $orderby";
		if (isset($limit))   $sql .= " LIMIT $limit";
		// now execute it.
		// echo $sql;
		if ($res = mysql_query($sql)) {
			if (mysql_num_rows($res)) {
				while($row = mysql_fetch_assoc($res)) {
					$this->run_hooks('update_object', $row); // [2011-08-11 03:57:32] woah!
					$data[] = $row;
				}
				// writelog(var_export($data, 1), -1);
				return $data;
			}else{
				return array(); // [2010-12-12 21:50:27]
			}
		}else{
			if ($this->debug) $messages[] = $this->styledText("List error: ". mysql_error() ."\n\n$sql"."<br>\n", 'red');
			return false;
		}
	}
	
	//_____________________________________
	// styledText($string, $color='#000') /
	function styledText($string, $color='#000') {
		return "<span style='color: $color;'>$string</span>"; // christ. there. happy now?
	}
	
	// "experimental"
	function reset() {
		$this->id = false;
		foreach ($this->fields as $key => $value) {
			$this->fields[$key] = null;
		}
	}
	
} // End of Base Class


/* -- Log --------------------------------

[2011-08-11 03:57:32] Patched get_list() to run update_object hooks on result list. /might/ just have to make this optional..
                      Replaced mres calls with mysql_real_escape_string.
[2011-07-29 23:18:15] Fixed hook method: update_object wouldn't re-select() if the callback function was a closure.
[2011-07-23 14:25:11] "Added" styledText() as method to rely less on non-class functions, but jeez, we need some proper error reporting in here.
[2011-01-09 20:53:31] Added ->validate($data, $type) & (string) property ->validation_error. ->validate($data, $type) returns true by default
[2010-12-27 18:13:27] Added $type arg to get_data() because YOU NEVER KNOW and it turned out to be maybe useful somewhere (reverts [2010-12-01 20:07:12]).
[2010-12-20 17:03:59] select() used to return $row, now it returns $this->fields, after update_object() has had its way with it, so that any hooks/overrides
                      that affect $this get back with the select data too.
[2010-12-12 21:50:27] get_list() now returns an empty array instead of 0, when there were no errors but no records either
[2010-12-01 20:50:38] patched ->hook() and run_hooks() to support anonymous functions (AKA closures). create_function() was already supported (it turns out to
                      basically be a wrapper for eval() and returns a function-name string).
[2010-12-01 20:07:12] Removed $type arg from get_data(), wasn't used (it used to be called with 'insert' and 'update', from those methods).
                      Added "pre_insert" and "pre_update" hooks in those methods
[2010-11-30 14:42:49] update_object() now actually checks for its hooks (->hook('update_object',..) will call select() if $this->id, which then calls update_object())
[2010-11-04 15:49:15] Updated update() and insert() to json_encode any array values in $data, and give a warning.
                      Added ->show_errors property, that should be used instead of ->debug in various places
                      Updated delete(), post_delete hook now gets the whole row instead of just the id (useful for deleting images that were named in the
                      record etc)
[2010-11-03 19:02:55] Updated select() with optional $id param: if $id is only known after object creation, $R->select($id) will set it and request the data
[2010-10-27 13:42:17] $id in the Constructor can now also be an assoc array, $this->id is reset as soon as it finds the record. The clause is still also used.
[2010-07-06 13:46:28] Added get_list(), copied from InfoBuddy
[2010-04-01 20:35:02] Re-incarnating $_REQUEST as $_request, since the former contained _COOKIE as well, which could contain parameters that were also defined
                      fieldnames - fun fun fun.
[2010-03-27 17:27:01] Allowed unsetting clause() with an empty array (so easy, so useful - why'd it take so long?!)

[2010-03-12 00:41:26] ->hook/run_hooks now accept array(object, method) as function to run. I rule.
[2010-02-28 22:23:01] Made sure the hooks actually *ran*, too.
[2009-11-18 14:33:06] Allow multiple hook functions for the same hook
[2009-06-12 04:46:25] debug sql is now actually echo'd in $this->delete. (doh)
[2009-02-08 01:58:18] fixed select(), if a record doesn't exists it sets $this->id to false and complains. Otherwise, it now returns the $row.
[2008-12-07 01:21:09] delete() now logs errors regardless of $this->logging
[2008-11-15 19:37:39] Delete foreach($this->fields as $key => &$value) not working well under older PHP (I think?), rewrote.
[2008-11-15 19:05:38] Added hook method: accepts name of method to apply in (only get_data for now), and name of [custom] function to run.
											Custom function must accept $data and return $data in get_data(), maybe other method-custom-calls get other requirements.
[2008-10-11 16:27:27] ->delete() now sets fields to null and $id to false on successful delete, logs on failure.


Todo: implement parameterized SQL queries - though that might require either moving to mysqli, adding some abstraction class, or a bunch of methods that
Todo:   wrap queries for both interfaces.
TODO: Sort out debug vs show_errors, go through code applying correct one
Todo: merge this with record.class.php and [meta]db.class.php to get one huge frickin class that is extendable, but simple to use (not
Todo:   [much more] complicated then recobject class). Would like to keep: set_key, set_clause, file upload.
Todo: Introduce toggles for the returning of status messages, or something.. $this->record_updated is not exactly optimal.
Todo: Minimize dependencies on misc custom functions
Todo: A "Handle act" bit might be a method, with sensible default actions and some more hooks (on_successful_update etc)
Todo:   The optional insert() (and update()?) argument might be set with a seperate method to make this easier (then again, it'd be just as much code?)

Maybe: make this->fields array a stdClass object?
Maybe: yet more callback hooks?


Done: ->reset()
      Was: needs way to empty record again (outside of delete()) like set id to 0 and all fields to empty. Why - for example to close a form without saving it.
      The code to handle actions on the object would sit under the code that creates the object (for various reasons), and when it's created all the fields
      get a value. If /then/ the save is cancelled (in the handle-act code) AND there's a form to create a new record under that, which is also used by the
      editing, the object values end up in should-be-empty fields.
      And there could be other reasons..

*/

?>