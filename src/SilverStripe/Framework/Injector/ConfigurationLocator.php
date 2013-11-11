<?php

namespace SilverStripe\Framework\Injector;

/**
 * An object which locates additional configuration for an injector.
 */
interface ConfigurationLocator {

	/**
	 * Performs a configuration value lookup and returns the value.
	 *
	 * @param string $name
	 * @return mixed
	 */
	public function getConfiguration($name);

	/**
	 * Gets the service configuration for a service type.
	 *
	 * @param string $service
	 * @return array
	 */
	public function getServiceConfiguration($service);

	/**
	 * Gets the configured dependencies to be injected into a service object.
	 *
	 * @param object $object The un-injected service object.
	 * @param string $type The type the service was created as.
	 * @return array
	 */
	public function getObjectDependencies($object, $type);

}
