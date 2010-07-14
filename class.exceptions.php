<?php

/*
 * class.exceptions.php
 *
 * @software  OOPS - Object Oriented Php Sessions
 * @author    James Brumond
 *
 * Copyright 2010 James Brumond
 * Dual licensed under MIT and GPL
 */

// make sure that the library is not being called directly
if (__FILE__ == $_SERVER["SCRIPT_FILENAME"]) die("Bad Load Order");



define("OOPS_STOP_MESSAGE", 0);
define("OOPS_STOP_WARNING", 1);
define("OOPS_STOP_CRITICAL", 3);



/*
 * @class   OopsException
 * @parent  Exception
 */
class OopsException extends Exception { }



/*
 * @class   OopsExceptionHandler
 * @parent  void
 */
class OopsExceptionHandler {

	protected $exceptions = true;
	protected $html_exceptions = true;

	public function throw_exception($msg = 'unknown exception', $level = OOPS_STOP_WARNING) {
		try {
			throw new OOPS_Exception($msg, $level);
		} catch (OOPS_Exception $e) {
			$this->handle_exception($e);
		}
	}
	
	public function display($flag) {
		$this->exceptions = !!$flag;
	}
	
	public function display_html($flag) {
		$this->html_exceptions = !!$flag;
	}

	protected function handle_exception($e) {
		// figure out the type of exception
		switch ($e->getCode()) {
			case OOPS_STOP_MESSAGE:     # 0
			case E_NOTICE:             # 8 (built-in)
			case E_USER_NOTICE:        # 1024 (built-in)
				$type = 'Notice';
				$return = true;
				break;
			case OOPS_STOP_WARNING:     # 1
			case E_WARNING:            # 2 (built-in)
			case E_USER_WARNING:       # 512 (built-in)
				$type = 'Warning';
				$return = true;
				break;
			case OOPS_STOP_CRITICAL:    # 3
			case E_USER_ERROR:         # 256 (built-in)
				$type = 'Error';
				$return = false;
				break;
			case E_RECOVERABLE_ERROR:  # 4096 (built-in)
				$type = 'Recoverable Error';
				$return = true;
				break;
			default:
				$type = 'Unknown Type Exception';
				$return = false;
				break;
		}

		if ($this->exceptions) {
			// get some useful information
			$info = $e->getTrace();
			$info = $info[count($info) - 1];

			// echo the exception using html or not?
			if ($this->html_exceptions) {
				$msg = '<strong>' . $type . '</strong>: ' . $e->getMessage() . ' in <strong>' . $info['file'] .
				'</strong> on line <strong>' . $info['line'] . '</strong><br />' . "\n";
			} else {
				$msg = $type . ': ' . $e->getMessage() . ' in ' . $info['file'] . ' on line ' . $info['line'] . "\n";
			}

			// echo the message
			echo $msg;
		}

		if (! $return) die();
		return $return;
	}

}

?>
