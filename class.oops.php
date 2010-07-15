<?php

/*
 * class.oops.php
 *
 * @software  OOPS - Object Oriented Php Sessions
 * @version   0.1.1-rc
 * @author    James Brumond
 * @created   13 July, 2010
 * @updated   13 July, 2010
 *
 * Copyright 2010 James Brumond
 * Dual licensed under MIT and GPL
 */

// make sure that the library is not being called directly
if (__FILE__ == $_SERVER["SCRIPT_FILENAME"]) die("Bad Load Order");

// make sure we have a valid default timezone
@date_default_timezone_set(@date_default_timezone_get());



/*
 * @const  OOPS_DIRECTORY
 */
	define("OOPS_DIRECTORY", dirname(__FILE__) . "/");

/*
 * @const  OOPS_CONFIG_FILE
 */
	define("OOPS_CONFIG_FILE", "config.oops.php");

/*
 * @const  OOPS_EXCEPTIONS
 */
	define("OOPS_EXCEPTIONS", "class.exceptions.php");



/*
 * @include  class.exceptions.php
 * @class    OopsException
 * @class    OopsExceptionHandler
 */
	require_once OOPS_DIRECTORY . OOPS_EXCEPTIONS;



/*
 * @class   IRV_Sessions
 * @parent  void
 */

class Oops {

/*
 * Statics
 */
	
	public static function read_config() {
		require_once OOPS_DIRECTORY . OOPS_CONFIG_FILE;
		return $config;
	}

/*
 * Private Properties
 */

	protected $config     = null;
	protected $exceptions = null;
	protected $user_data  = null;
	protected $db         = null;
	protected $session_id = null;
	protected $data       = null;
	protected $db_table   = null;
	
/*
 * Private Methods
 */

	protected function build_insert($data) {
		$data = (array) $data;
		$segment = '(' . implode(', ', array_keys($data)) . ') values (';
		$values = array();
		foreach ($data as $value) {
			if (is_int($value) || is_numeric($value)) {
				$values[] = (string) $value;
			} else {
				$values[] = "'$value'";
			}
		}
		$segment .= implode(', ', $values) . ')';
		return $segment;
	}

	protected function set_cookie($id) {
		$expires = time() + (60 * $this->config->session_expire);
		try {
			setcookie($this->config->session_cookie, $id, $expires, $this->config->session_path);
		} catch (Exception $e) {
			$this->exceptions->throw_exception('session cookie could not be set');
			return false;
		}
		return true;
	}
	
	protected function delete_cookie() {
		setcookie($this->config->session_cookie, '', time() - 3600, $this->config->session_path);
	}
	
	protected function get_live_user_data() {
		return (object) array(
			'ip_address' => $_SERVER['REMOTE_ADDR'],
			'user_agent' => substr($_SERVER['HTTP_USER_AGENT'], 0, 50),
			'last_activity' => $_SERVER['REQUEST_TIME'],
			'session_id' => ((isset($_COOKIE[$this->config->session_cookie])) ?
				$_COOKIE[$this->config->session_cookie] : null),
			'user_data' => serialize(array())
		);
	}
	
	protected function throw_db_error($msg = 'database error') {
		$this->exceptions->throw_exception("$msg: " . mysql_error(), IRV_STOP_CRITICAL);
	}
	
	protected function run_query($query) {
		$this->open_db();
		$result = @mysql_query($query, $this->db);
		if ($result === false) $this->throw_db_error();
		return $result;
	}
	
	protected function open_db() {
		if ($this->db === null) {
			$this->db = @mysql_connect(
				$this->config->database_host, $this->config->database_user, $this->config->database_pass
			);
			if (! $this->db) $this->throw_db_error('cannot not connect to database');
		}
	}
	
	protected function close_db() {
		if ($this->db) @mysql_close($this->db);
		$this->db = null;
	}
	
	protected function fetch_user_data($id) {
		$query = "select * from " . $this->db_table . " where session_id='$id'";
		$result = $this->run_query($query);
		$row = mysql_fetch_assoc($result);
		return (($row !== false) ? (object) $row : $row);
	}
	
	protected function generate_session_id() {
		do {
			$id = md5(uniqid(rand(), true));
		} while ($this->fetch_user_data($id));
		return $id;
	}
	
	protected function destroy_session() {
		$id = $this->session_id;
		if ($id === null) return false;
		$this->run_query("delete from " . $this->db_table . " where session_id='" . $this->session_id . "'");
		$this->data = null;
		$this->session_id = null;
		$this->delete_cookie();
	}
	
	protected function update_session() {
		if ($this->is_open()) {
			$this->run_query("update " . $this->db_table . " set last_activity=" . time() .
				", user_data='" . serialize($this->data) . "' where session_id='" . $this->session_id . "'");
		}
	}
	
	protected function open_session($force_new = false) {
		if (! $this->user_data)
			$this->user_data = $this->get_live_user_data();
		// get any existing session data from the database
		if ($this->user_data->session_id && ! $force_new) {
			$from_database = $this->fetch_user_data($this->user_data->session_id);
			if ($from_database
			&& (! $this->config->session_match_useragent || $from_database->user_agent == $this->user_data->user_agent)
			&& (! $this->config->session_match_ipaddress || $from_database->ip_address == $this->user_data->ip_address)) {
				return $from_database;
			}
		}
		// no session already exists, create a new one
		$this->user_data->session_id = $this->generate_session_id();
		$this->run_query("insert into " . $this->db_table . " " . $this->build_insert($this->user_data));
		return $this->user_data;
	}
	
	protected function regenerate_session_id() {
		$old_id = $this->user_data->session_id;
		if (! empty($old_id)) {
			$new_id = $this->generate_session_id();
			if ($this->set_cookie($new_id)) {
				$this->run_query("update " . $this->db_table . " set session_id='$new_id' where session_id='$old_id'");
				$this->user_data->session_id = $new_id;
				$this->session_id = $new_id;
				return true;
			}
		}
		return false;
	}
	
	protected function is_expired($last_activity) {
		$timestamp = time();
		$expiration = $this->config->session_expire * 60;
		return (($last_activity + $expiration) >= $timestamp);
	}

/*
 * Magic Methods
 */

	public function __construct() {
		$this->config = self::read_config();
		$this->exceptions = new OopsExceptionHandler;
		$this->db_table = $this->config->database_name . "." . $this->config->session_db_table;
		$this->user_data = $this->get_live_user_data();
	}
	
	public function __destruct() {
		$this->update_session();
	}
	
	public function __get($name) {
		switch ($name) {
			case 'session_id':
				return $this->session_id;
				break;
			default:
				return null; break;
		}
	}

/*
 * Public Properties
 */
	
	// no public properties

/*
 * Public Methods
 */

	public function is_open() {
		return ($this->session_id !== null);
	}

	public function start() {
		if (! $this->is_open()) {
			$data = $this->open_session();
			if ($this->is_expired($data->last_activity)) {
				$this->destroy();
				$data = $this->open_session(true);
			}
			$data->last_activity = $_SERVER['REQUEST_TIME'];
			$this->user_data = $data;
			$this->data = unserialize($data->user_data);
			$this->session_id = $data->session_id;
			if ($this->config->session_regeneration)
				$this->regenerate_session_id();
			$this->set_cookie($this->session_id);
		}
	}
	
	public function regenerate() {
		if ($this->is_open())
			return $this->regenerate_session_id();
	}
	
	public function destroy() {
		if ($this->is_open())
			$this->destroy_session();
	}
	
	public function set_value($name, $value) {
		if ($this->is_open())
			$this->data[$name] = $value;
	}
	
	public function get_value($name) {
		if ($this->is_open())
			return ((isset($this->data[$name])) ? $this->data[$name] : null);
	}
	
	public function unset_value($name) {
		if ($this->is_open())
			unset($this->data[$name]);
	}

}



/*
 * Auto Initialize
 */

$_conf = Oops::read_config();
if ($_conf->general_auto_init)
	$GLOBALS[$_conf->general_auto_name] = new Oops;
unset($_conf);




?>
