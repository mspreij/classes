<?php
/**
* Browser
*/

class Browser {
	
	public $url               = '';      // This is the property of the class that holds the URL value, that is, the location of the web resource.
	public $cookies           = array(); // name, value, expire, path, domain. Default: chocolate chip.
	public $params            = array(); // assoc array name => value
	public $method            = 'get';   // GET/POST (yes, really)
	public $valid_methods     = array('get', 'post'); // Just in case. (have you met my customers?)
	public $follow_redirects  = false;   // Keep going, follow the signs.
	public $max_redirects     = 4;
	public $error_level       = 1;       // 1: errors, 2: warnings, 3:notices, 4:whatever man, 0: fail silently (or just run flawlessly)
	public $error_colors      = array(1=>'red', '#F80', 'blue');
	public $error_names       = array(1=>'error', 'warning', 'notice');
	public $display_errors    = true;    // blaat.
	public $log_level         = 1;
	public $debug             = false;   // show
	public $connection;                  // curl thing
	// These are bits of the Response
	public $status            = array(); // Society bullshit
	public $headers           = array(); // HTTP stuff no one cares about, really.
	public $body              = '';      // Your temple, defile it.
	public $raw               = '';      // Like my nerves.
	public $errors            = '';      // Inevitable.
	// Things I had no idea where else to park, yet.
	public $funky_chars       = array('00', '01', '11', '80', '82', '83', '84', '86', '87', '88', '8a', '8b', '8c', '8e', '8f', '94', '96', '97', '98', '99', '9b', '9c');
	public $truly_funky_chars = array('ad');
	public $useragent         = 'PHP/Curl-based browser simulator by gmail.com@mspreij';
	
	function __construct($url) {
		$this->url = $url;
	}
	
	function method($method) {
		$method = strtolower($method);
		if (! in_array($method, $this->valid_methods)) {
			return false;
		}
		$this->method = $method;
		return true;
	}
	
	function get() {
		$this->method('get');
		// todo: add params to the url somehow. if given url contains a hash insert them before that, and after questionmark etc.
		return $this->fetch(); // bool success
	}
	
	function post() {
		$this->method('post');
		return $this->fetch(); // bool success
	}
	
	function set_param($name, $value) {
		$this->params[$name] = $value;
	}
	
	function params() {
		return $this->params;
	}
	
	//__________
	// fetch() /
	protected function fetch() {
		$this->connection = curl_init($this->url);
		curl_setopt($this->connection, CURLOPT_HEADER, 1);          // include headers to output
		curl_setopt($this->connection, CURLOPT_RETURNTRANSFER, 1);  // have curl_exec return output as string
		curl_setopt($this->connection, CURLINFO_HEADER_OUT, true);  // store request headers, too
		curl_setopt($this->connection, CURLOPT_USERAGENT, $this->useragent);  // Sheesh, some servers are anal.
		// Wooo! I don't have to do this myself! Awesome!
		curl_setopt($this->connection, CURLOPT_COOKIEJAR, "cookie.txt");
		curl_setopt($this->connection, CURLOPT_COOKIEFILE, "cookie.txt");
		
		if ($this->method == 'post') {
			curl_setopt($this->connection, CURLOPT_POST, true);
			curl_setopt($this->connection, CURLOPT_POSTFIELDS, $this->params());
			curl_setopt($this->connection, CURLOPT_HTTPHEADER, array("Expect:"));
		}
		// curl_setopt($this->connection, CURLOPT_HTTPHEADER, array("Accept-Encoding: compress, gzip"));
		$string = curl_exec($this->connection);
		$this->curl_info = curl_getinfo($this->connection);
		$this->request_headers = $this->curl_info['request_header'];
		if (! $string) {
			$this->errors .= curl_error($this->connection);
			return false;
		}
		curl_close($this->connection);
		// split headers from body
		$gap_pos = strpos($string, "\r\n\r\n");
		$header  = substr($string, 0, $gap_pos);
		$body    = substr($string, $gap_pos+4);
		// status line
		$status_line = substr($header, 0, strpos($header, "\r\n"));
		list($status_http, $status_code, $status_text) = explode(' ', $status_line, 3);
		// headers
		$keys = array();
		foreach(explode("\r\n", substr($header, strpos($header, "\r\n")+2)) as $line) {
			if (strpos($line, ':') > 0) {
				$col_pos = strpos($line, ':');
				list($key, $value) = array(trim(substr($line, 0, $col_pos)), trim(substr($line, $col_pos+1)));
				if (in_array($key, $keys)) {
					$headers[$key] .= ', '.$value; // as per RFC 2616 sec 4.2, 2.1
				}else{
					$headers[$key] = $value;
				}
				$keys[] = $key;
			}else{
				$malformed_headers[] = $line;
			}
		}
		if (isset($malformed_headers)) {
			$this->errors['malformed_headers'] = $malformed_headers;
		}
		$this->status = array(
			'line'  => $status_line,
			'http'  => $status_http,
			'code'  => $status_code,
			'text'  => $status_text,
		);
		$this->headers = $headers;
		$this->body    = $body;
		$this->raw     = $string;
		$this->hex();
		// check for cookies, etc. Might call fetch() again.
		$this->post_process();
		return true; // eh. why not.
	}
	
	//________
	// hex() /
	function hex() {
		$hex = array();
		$len = strlen($this->raw); // these habits die hard
		// There is a shorter way to do all this, I know it - but apparently not at 3 AM.
		$j = 0;
		$hexString = $charString = '';
		$table = "<table>\n";
		for($i=0;$i<$len;$i++) {
			$char = $this->raw[$i];
			$hex = dechex(ord($char));
			if (strlen($hex) == 1) $hex = '0'.$hex;
			if (in_array($hex, $this->funky_chars)) {
				$char = '<span style="color: red; background: #ffC;">.</span>';
			}elseif (in_array($hex, $this->truly_funky_chars)) {
				$char = '<span style="color: red; background: #ffC;">-</span>';
			}elseif (in_array($char, array("\n", "\r", "\t"))) {
				$char = '<span style="color: #0B0; background: white;">.</span>';
			}else{
				$char = htmlentities($char);
			}
			$hexString  .= $hex.' ';
			$charString .= $char;
			$j++;
			if ($j == 8) {
				$hexString  .= '&nbsp;';
			}elseif ($j == 16) {
				$table .= "<tr><td>$hexString | </td><td>".iconv("ISO-8859-1", "UTF-8", $charString)."</td></tr>\n";
				// $table .= "<tr><td>$hexString | </td><td>$charString</td></tr>\n";
				$hexString = $charString = '';
				$j = 0;
			}
		}
		if ($j) $table .= "<tr><td>$hexString</td><td>".iconv("ISO-8859-1", "UTF-8", $charString)."</td></tr>\n"; // trailing bits
		// if ($j) $table .= "<tr><td>$hexString</td><td>$charString</td></tr>\n"; // trailing bits
		$table .= "</table>";
		$this->hex = $table; // and replace.
	}
	
	function post_process() {
		// cookies
		
		// redirects
		
		// ...
		return true;
	}
	
	//_____________________________
	// handle_error($msg, $level) /
	function handle_error($msg, $level) {
		if ($this->error_level >= $level) {
			echo "<span style='color: ".$this->show_error_colors[$level]."'>".$this->show_error_names[$level].": $msg</span>\n";
		}
		if ($this->log_level >= $level) {
			$this->log($msg, $level);
		}
	}
	
	//____________________
	// log($msg, $level) /
	function log($msg, $level) {
		return false;
	}
	
	function __call($method, $params) {
		echo "<span style='color: red; background: #FEFF9A'>Don't be silly, that's not even a method.</span><br>";
		return false;
	}
}

/* -- Log --------------------------------

[2011-03-03 05:39:15] Apparently parameters work, somewhat. Curl puts in an "Expect: 100-continue" HTTP header though, surpressing that for now.
                      Also, apparently cookies "just work", if there's a cookiejar. Have to test, and see Todo (or "Maybe do") list.
                      Added hex function, ripped from other curl file.
                      There's a lot of stuff that the curl lib already does for you, but not always in a way that is easy to customize.. replace as
                      needed, though it's silly extra work.
[2011-03-02 00:20:45] It exists - and it PARSES. You wanted more?

Todo: a metric fuckton.
- parameters don't work with GET
- authentication
- fix Hex function, a lot of the characters that turned up as little squares and are thusly replaced with coloured dots have perfectly fine symbols
  in the used fonts. Is the htmlentities not working? Or it skips them? Force encode them somehow? htmlentities has a charset parameter, must try
  more..
- custom HTTP headers?
- the error logging/display/message system (not that anyone *reads* those, but hey)

Maybe do:
- cookies? copy the parameters used by PHP's functions. For now cookie.txt file works. Figure out a good way to use a tempnam() file instead,
  that doesn't rely on a cookie for its name.. (md5 some browser/ip stuff?)

*/

?>