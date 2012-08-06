<?php
/**
 * File similar to main.php designed for command-line scripts
 * 
 * This file lets you execute SilverStripe requests from the command-line.  The URL is passed as the first argument to the scripts.
 * 
 * @package framework
 * @subpackage core
 */

/**
 * Ensure that people can't access this from a web-server
 */
if(isset($_SERVER['HTTP_HOST'])) {
	echo "cli-script.php can't be run from a web request, you have to run it on the command-line.";
	die();
}

/**
 * Execute the script from inside its directory.
 */
chdir(__DIR__);

/**
 * Include SilverStripe's core code
 */
require_once("core/Core.php");

global $databaseConfig;

// We don't have a session in cli-script, but this prevents errors
$_SESSION = null;

// Connect to database
require_once("model/DB.php");
DB::connect($databaseConfig);

// Direct away - this is the "main" function, that hands control to the apporopriate controller
DataModel::set_inst(new DataModel());
Director::direct();
