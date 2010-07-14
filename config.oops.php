<?php

$config = (object) array(

/*
 * Database Configuration
 */

	// The MySQL database to store session data in
	'database_name'  => 'dbOops',
	
	// The database table the sessions are stored in
	'database_table' => 'tblSessions',
	
	// The host the database is on
	'database_host'  => 'localhost',
	
	// The user name with which to access the database
	'database_user'  => 'test',
	
	// The password with which to access the database
	'database_pass'  => 'passwd',
	
/*
 * Session Configuration
 */
	
	// Should we require a matching IP address?
	'session_match_ipaddress' => false,
	
	// Should we require a matching user agent?
	'session_match_useragent' => true,
	
	// What name should we use for the session id cookie?
	'session_cookie'          => 'oops_session',
	
	// How many minutes before expire (not yet supported)
	'session_expire'          => 180,
	
	// Auto-regenerate session id every new request?
	'session_regeneration'    => true,
	
	// What path is the session cookie to be allowed on?
	'session_path'            => '/'

);

?>
