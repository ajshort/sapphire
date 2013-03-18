<?php

/** 
 * This bootstraps the SilverStripe system so that phpunit can be run directly on SilverStripe tests.
 */

// Make sure display_errors is on
ini_set('display_errors', 1);

// Check we're using at least PHPUnit 3.5
if(version_compare(PHPUnit_Runner_Version::id(), '3.5', '<')) {
	echo 'PHPUnit 3.5 required to run tests using bootstrap.php';
	die();
}

// Fake the script name and base
global $_SERVER;
if (!$_SERVER) $_SERVER = array();

$frameworkPath = dirname(dirname(__FILE__));
$frameworkDir = basename($frameworkPath);

$_SERVER['SCRIPT_FILENAME'] = $frameworkPath . DIRECTORY_SEPARATOR . 'cli-script.php';
$_SERVER['SCRIPT_NAME'] = '.' . DIRECTORY_SEPARATOR . $frameworkDir . DIRECTORY_SEPARATOR . 'cli-script.php'; 

if(!defined('BASE_PATH')) define('BASE_PATH', dirname($frameworkPath));

// Flush the manifest
$_GET['flush'] = 1;

// Connect to database
require_once $frameworkPath . '/core/Core.php';
require_once $frameworkPath . '/tests/FakeController.php';

global $databaseConfig;
DB::connect($databaseConfig);

// Now set a fake REQUEST_URI
$_SERVER['REQUEST_URI'] = BASE_URL . '/dev';

// Fake a session 
$_SESSION = null;

// Prepare manifest autoloader
$controller = new FakeController();

// Get test manifest
TestRunner::use_test_manifest();

SapphireTest::set_is_running_test(true);

// Remove the error handler so that PHPUnit can add its own
restore_error_handler();
