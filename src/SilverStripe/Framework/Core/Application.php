<?php
/**
 * @package framework
 * @subpackage core
 */

namespace SilverStripe\Framework\Core;

use DB;
use Debug;
use Director;

use SilverStripe\Framework\FrameworkModule;
use SilverStripe\Framework\Injector\Injector;
use SilverStripe\Framework\Injector\ApplicationServiceConfigurationLocator;
use SilverStripe\Framework\Manifest\ClassLoader;
use SilverStripe\Framework\Manifest\Manifest;

/**
 * The default implementation of the application interface. The application
 * is the main entry point for a SilverStripe application. It serves to
 * initialise the environment, hand of control to the {@link Director} class,
 * and provide dependencies.
 *
 * Each application is made up of a number of modules - the framework is itself
 * a module. Each application subclass needs to define a `getBasePath` method,
 * and also a `registerModules` method to register modules that are a part of
 * the application:
 *
 * <code>
 *   class ExampleApplication extends Application {
 *
 *     public function getBasePath() {
 *       return dirname(__DIR__);
 *     }
 *
 *     protected function registerModules() {
 *       $this->registerModule('name', 'path/to/module');
 *       parent::registerModules();
 *     }
 *
 *   }
 * </code>
 *
 * It has the following responsibilities:
 * <ul>
 *   <li>Provide a list of modules making up the application</li>
 *   <li>Get the paths for various parts of the application</li>
 *   <li>Initialise and provide access to the manifest and loaders</li>
 *   <li>Provide access to the application injector and config objects.</li>
 *   <li>Generate responses to incoming requests</li>
 * </ul>
 *
 * @package framework
 * @subpackage core
 * @see ComposerApplication
 * @see WebrootApplication
 */
abstract class Application implements ApplicationInterface {

	private static $curr;

	protected $modules;
	protected $manifest;
	protected $config;
	protected $injector;
	protected $classLoader;
	protected $tempPath;

	/**
	 * Returns the currently active application instance.
	 *
	 * @return Application
	 */
	public static function curr() {
		return self::$curr;
	}

	public static function respond() {
		$app = new static();

		$app->start();
		$app->handle()->output();
		$app->stop();
	}

	public function __construct() {
		$this->modules = new ModuleSet($this);
		$this->registerModules();
	}

	public function start() {
		// Register this application as the current one
		self::$curr = $this;

		// Bootstrap the environment
		Bootstrap::bootstrap($this);

		// Create the manifest, and register loaders
		$this->initManifest();
		$this->initClassLoader();

		// Create the config and then use it to create the injector, which will
		// be used for later dependencies
		$this->createInjector();
		$this->initConfig();
		$this->initInjector();

		// Register the created objects with the injector
		$injector = $this->getInjector();
		$injector->registerNamedService('Config', $this->getConfig());

		$injector->registerNamedService('Application', $this);
		$injector->registerNamedService('Manifest', $this->getManifest());
		$injector->registerNamedService('ClassLoader', $this->getClassLoader());
		$injector->registerNamedService('PhpManifest', $this->getManifest()->getPhpManifest());
		$injector->registerNamedService('ConfigManifest', $this->getManifest()->getConfigManifest());
		$injector->registerNamedService('TemplateManifest', $this->getManifest()->getTemplateManifest());

		// @todo This code should be moved elsewhere
		global $databaseConfig;
		DB::connect($databaseConfig);

		if(Director::isLive()) {
			error_reporting(E_ALL & ~(E_DEPRECATED | E_STRICT | E_NOTICE));
		}

		Debug::loadErrorHandlers();
	}

	public function stop() {
		if(self::$curr !== $this) {
			throw new \Exception('The application is not currently running');
		}

		self::$curr = null;
	}

	public function handle() {
		return $this->injector->get('Director')->direct();
	}

	public function get($name) {
		return $this->getInjector()->get($name);
	}

	/**
	 * @return ModuleSet
	 */
	public function getModules() {
		return $this->modules;
	}

	/**
	 * @return ModuleInterface|null
	 */
	public function getModule($name) {
		return $this->modules->get($name);
	}

	public function getManifest() {
		return $this->manifest;
	}

	/**
	 * @param Manifest $manifest
	 */
	public function setManifest(Manifest $manifest) {
		$this->manifest = $manifest;
	}

	public function getConfig() {
		return $this->config;
	}

	/**
	 * @param Config $config
	 */
	public function setConfig(Config $config) {
		$this->config = $config;
	}

	/**
	 * @return \SilverStripe\Framework\Injector\Injector
	 */
	public function getInjector() {
		return $this->injector;
	}

	public function getClassLoader() {
		return $this->classLoader;
	}

	public function getPublicPath() {
		return $this->getBasePath() . '/public';
	}

	public function getAssetsPath() {
		return $this->getPublicPath() . '/' . ASSETS_DIR;
	}

	/**
	 * Returns the path to the app-specific temp directory, creating one if
	 * one doesn't exist.
	 *
	 * @return string
	 */
	public function getTempPath() {
		if(!$this->tempPath) {
			$base = $this->getBasePath();

			if(is_dir("$base/silverstripe-cache")) {
				return $this->tempPath = "$base/silverstripe-cache";
			}

			$tmp = sys_get_temp_dir();
			$name = 'silverstripe-cache' . preg_replace('/[^a-zA-Z0-9-]/', '-', $base);
			$path = "$tmp/$name";

			if(!is_dir($path)) {
				mkdir($path);

				if(!is_dir($path)) {
					throw new \Exception(
						'Could not gain access to a temp folder. Please create ' .
						'a directory called "silverstripe-cache"  in your ' .
						'application base directory.'
					);
				}
			}

			$this->tempPath = $path;
		}

		return $this->tempPath;
	}

	/**
	 * Registers the module instances for this application.
	 */
	protected function registerModules() {
		$this->registerModule(new FrameworkModule());
	}

	/**
	 * Registers a module. This can either be passed a {@link ModuleInterface}
	 * instance, or the module name and path. The path can either be absolute
	 * or relative to the base path.
	 *
	 * @param ModuleInterface|string $module
	 * @param string $path
	 * @param string $type
	 * @see ModuleSet
	 */
	protected function registerModule() {
		if(func_num_args() == 1) {
			call_user_func_array(array($this->modules, 'add'), func_get_args());
		} else {
			call_user_func_array(array($this->modules, 'addFromDetails'), func_get_args());
		}
	}

	/**
	 * Creates and initialises the application manifest.
	 */
	protected function initManifest() {
		$this->manifest = new Manifest($this);
		$this->manifest->init(isset($_GET['flush']));
	}

	/**
	 * Creates and initialises the application config instance.
	 */
	protected function initConfig() {
		$this->config = new Config($this->manifest->getConfigManifest());
		$this->config->init();
	}

	/**
	 * Createss the injector but does not initialise it. This must be done in
	 * two stages as initialisation requires the config to be available.
	 */
	protected function createInjector() {
		$this->injector = new Injector();
	}

	/**
	 * Initialises the previously created injector.
	 */
	protected function initInjector() {
		$this->getInjector()->setConfigLocator(new ApplicationServiceConfigurationLocator($this));
	}

	/**
	 * Creates and initialises the class loader instance.
	 */
	protected function initClassLoader() {
		$this->classLoader = new ClassLoader($this->manifest->getPhpManifest());
		$this->classLoader->register();
	}

}