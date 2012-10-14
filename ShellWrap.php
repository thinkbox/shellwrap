<?php

namespace MrRio;

class ShellWrap {
	
	static private $output = array();
	static private $prepend = array();
	static private $stdin = null;

	static public $exec_string;

	public function __construct($prepend = null) {
		self::$prepend = $prepend;
	}

	public function __toString() {
		return self::$output;
	}

	/**
	 * Check if array is associative, thanks to 
	 * http://stackoverflow.com/questions/173400
	 **/ 
	static private function __isAssociative($array) {
		return array_keys($array) !== range(0, count($array) - 1);
	}

	static private function __run($arguments) {

		// Unwind the args, figure out which ones were passed in as an array
		self::$stdin = null;

		foreach($arguments as $arg_key => $argument) {
			if (is_object($argument)) {

				self::$stdin = strval($argument);
				unset($arguments[$arg_key]);

			} else if (is_array($argument)) {
				if (self::__isAssociative($argument)) {
					
					// Ok, so we're passing in arguments
					
					$output = '';

					foreach ($argument as $key => $val) {

						if ($output != '') {
							$output .= ' ';
						}

						// If you pass 'false', it'll ignore the arg altogether
						if ($val !== false) {
							// Figure out if it's a long or short commandline arg
							if (strlen($key) == 1) {
								$output .= '-';
							} else {
								$output .= '--';
							}
							$output .= $key;

							// If you just pass in 'true', it'll just add the arg
							if ($val !== true) {

								$output .= ' ' . escapeshellarg($val);
							} 

						}
					}

					$arguments[$arg_key] = $output;
				} else {

					// We're passing in an array, but it's not --key=val style

					$arguments[$arg_key] = implode(' ', $argument);
				}
			}
		} 

		$shell = implode(' ', $arguments);

		$output = array();
		$return_var = null;

		// Set exec_string for testing purposes
		self::$exec_string = $shell;

		// Prepend the path

		$parts = explode(' ', $shell);
		$parts[0] = exec('which ' . $parts[0]);

		if ($parts[0] != '') {
			$shell = implode(' ', $parts);
		}

		$descriptor_spec = array(
			0 => array('pipe', 'r'), // Stdout
			1 => array('pipe', 'w'), // Stdin
			2 => array('pipe', 'w') // Stderr
		);

		$process = proc_open($shell, $descriptor_spec, $pipes);

		if (is_resource($process)) {

			fwrite($pipes[0], self::$stdin);
			fclose($pipes[0]);

			$error_output = trim(stream_get_contents($pipes[2]));
			self::$output = stream_get_contents($pipes[1]);

			fclose($pipes[1]);
			fclose($pipes[2]);

			$return_value = proc_close($process);

			if ($return_value != 0) {
				throw new \Exception($error_output);
			}


		} else {
			throw new \Exception('Process failed to spawn');
		}

		//exec($shell, $output, $return_var);


	}

	// Raw arguments

	public function __invoke() {
		$arguments = func_get_args();
		$this->__run($arguments);
		return $this;
	}

	public function __call($name, $arguments) {
		array_unshift($arguments, $name);
		$this->__run($arguments);
		return $this;
	}

	public static function __callStatic($name, $arguments) {

		array_unshift($arguments, $name);
		if (isset(self::$prepend)) {
			$arguments = array_merge(self::$prepend, $arguments);
		}

		self::__run($arguments);
		return new ShellWrap();
	}

}