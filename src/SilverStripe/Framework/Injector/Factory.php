<?php

namespace SilverStripe\Framework\Injector;

/**
 * A factory class which is used by the injector to create new service instances.
 */
interface Factory {

	/**
	 * Creates a new instance of a service class and returns it.
	 *
	 * @param string $class The class to create.
	 * @param array $params The configured constructor parameters.
	 * @return object The newly created instance.
	 */
	public function create($class, array $params = array());

}
