<?php
/**
 * An entry point for cli requests.
 *
 * @package framework
 */

// Ensure that this can only be run from the CLI.
if(php_sapi_name() != 'cli') {
	die(
		'The CLI script cannot be run from a web reqest, you have to run it ' .
		'on the command line.'
	);
}

$loader = null;
$bases = array(dirname(dirname(__DIR__)), dirname(__DIR__) . '/vendor');

foreach($bases as $base) {
	if(file_exists("$base/autoload.php")) {
		$loader = "$base/autoload.php";
		break;
	}
}

if(!$loader) {
	echo "The Composer autoloader could not be found. Please ensure that " .
	     "Composer is set up correctly, and the autoloader has been " .
	     "built.\n";
	exit(1);
}

require_once $loader;

Application::respond();
