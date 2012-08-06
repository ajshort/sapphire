<?php
/**
 * A http request that is being routed and has extra data attached to it. Each
 * request can be routed several times until the entire URL is matched.
 *
 * @package framework
 * @subpackage control
 */
class RoutedRequest extends SS_HTTPRequest {

	/**
	 * @var array
	 */
	protected $parts = array();

	/**
	 * @var array
	 */
	protected $params = array();

	/**
	 * @var array
	 */
	protected $latest = array();

	/**
	 * A count of URL parts that have been matched but not shifted off.
	 *
	 * @var int.
	 */
	protected $unshiftedButParsed = 0;

	public function __construct($method = null, $url = null, $body = null, $env = array()) {
		parent::__construct($method, $url, $body, $env);

		if($url = $this->getUrl()) {
			$this->parts = explode('/', $url);
		}
	}

	/**
	 * @return array
	 */
	public function getUrlParts() {
		return $this->parts;
	}

	/**
	 * @return array
	 */
	public function getParams() {
		return $this->params;
	}

	/**
	 * @param string $name
	 * @return string|null
	 */
	public function getParam($name) {
		if(isset($this->params[$name])) return $this->params[$name];
	}

	/**
	 * Returns the parameters matched by the latest rule.
	 *
	 * @return array
	 */
	public function getLatestParams() {
		return $this->latest;
	}

	/**
	 * @param string $name
	 * @return string|null
	 */
	public function getLatestParam($name) {
		if(isset($this->latest[$name])) return $this->latest[$name];
	}

	/**
	 * Gets the unparsed part of the URL.
	 *
	 * @return string
	 */
	public function getRemainingUrl() {
		return implode('/', $this->parts);
	}

	/**
	 * @return bool
	 */
	public function isAllParsed() {
		return count($this->parts) <= $this->unshiftedButParsed;
	}

	/**
	 * Shifts one or more parts off the start of the URL.
	 *
	 * @param int $count
	 * @return string|array
	 */
	public function shift($count = 1) {
		if($count == 1) {
			return array_shift($this->parts);
		} else {
			$result = array();
			$count = min($count, count($this->parts));

			for($i = 0; $i < $count; $i++) {
				$result[] = array_shift($this->parts);
			}

			return $result;
		}
	}

	/**
	 * Shifts all parameter values down a space.
	 *
	 * @return string
	 */
	public function shiftParams() {
		$keys = array_keys($this->params);
		$values = array_values($this->params);
		$value = array_shift($values);

		// Push additional unparsed URL parts onto the parameter stack.
		if(array_key_exists($this->unshiftedButParsed, $this->parts)) {
			$values[] = $this->parts[$this->unshiftedButParsed];
		}

		foreach($keys as $position => $key) {
			$this->params[$key] = isset($values[$position]) ? $values[$position] : null;
		}

		return $value;
	}

	/**
	 * Pushes an array of named parameters onto the request.
	 *
	 * @param array $params
	 */
	public function pushParams(array $params) {
		$this->latest = $params;

		foreach($params as $k => $v) {
			if($v || !isset($this->params[$k])) $this->params[$k] = $v;
		}
	}

	/**
	 * @return int
	 */
	public function getUnshiftedButParsed() {
		return $this->unshiftedButParsed;
	}

	/**
	 * @param int $count
	 */
	public function setUnshiftedButParsed($count) {
		$this->unshiftedButParsed = $count;
	}

	/**
	 * @deprecated 3.1 Use {@link getParams()}.
	 */
	public function allParams() {
		Deprecation::notice('3.1', 'Use getParams()');
		return $this->getParams();
	}

	/**
	 * @deprecated 3.1 Use {@link getParam()}.
	 */
	public function param($name) {
		Deprecation::notice('3.1', 'Use getParam()');
		return $this->getParam($name);
	}

	/**
	 * @deprecated 3.1 Use {@link getLatestParams()}.
	 */
	public function latestParams() {
		Deprecation::notice('3.1', 'Use getLatestParams()');
		return $this->getLatestParams();
	}

	/**
	 * @deprecated 3.1 Use {@link getLatestParam()}.
	 */
	public function latestParam($name) {
		Deprecation::notice('3.1', 'Use getLatestParam()');
		return $this->getLatestParam($name);
	}

	/**
	 * @deprecated 3.1 Use {@link isAllParsed()}.
	 */
	public function allParsed() {
		Deprecation::notice('3.1', 'Use isAllParsed()');
		return $this->isAllParsed();
	}

	/**
	 * @deprecated 3.1 Use {@link getRemainingUrl()}.
	 */
	public function remaining() {
		Deprecation::notice('3.1', 'Use getRemainingUrl()');
		return $this->getRemainingUrl();
	}

	/**
	 * @deprecated 3.1 Use {@link shiftParams()}.
	 */
	public function shiftAllParams() {
		Deprecation::notice('3.1', 'Use shiftParams()');
		return $this->shiftParams();
	}

}
