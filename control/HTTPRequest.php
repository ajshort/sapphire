<?php
/**
 * Represents a HTTP request which is handled by the framework.
 *
 * @package framework
 * @subpackage control
 */
class SS_HTTPRequest extends SS_HttpMessage implements ArrayAccess {

	protected $get;
	protected $post;
	protected $files;
	protected $server;

	protected $method;
	protected $host;
	protected $baseUrl;
	protected $url;

	/**
	 * A useful factory function to create a new request instance.
	 *
	 * @return SS_HTTPRequest
	 */
	public static function create(
		$method = 'GET',
		$url = '/',
		$get = array(),
		$post = array(),
		$files = array(),
		$server = array(),
		$headers = array(),
		$body = null
	) {
		if(($pos = strpos($url, '?')) !== false) {
			$uri = $url;
			$url = substr($uri, 0, $pos);
			$query = substr($uri, $pos + 1);

			parse_str($query, $urlGet);
			$get = array_merge($get, $urlGet);
		} else {
			$query = http_build_query($get, '', '&');
			$uri = $url  . ($query ? "?$query" : null);
		}

		$server = array_replace($server, array(
			'REQUEST_METHOD' => strtoupper($method),
			'PATH_INFO' => '',
			'REQUEST_URI' => $uri,
			'QUERY_STRING' => $query,
		));

		$inst = new static($method, $url, $body, array(
			'get' => $get,
			'post' => $post,
			'files' => $files,
			'server' => $server
		));

		if($headers) {
			$inst->setHeaders($headers);
		}

		return $inst;
	}

	/**
	 * Creates a request object from the CLI globals, ensuring that all required
	 * information is present.
	 *
	 * @return SS_HTTPRequest
	 */
	public static function create_from_cli() {
		global $_FILE_TO_URL_MAPPING;

		// The first argument is compulsory and specifies the URL to run.
		if(!isset($_SERVER['argv'][1])) {
			echo "You must specify the URL to execute as the first argument.\n";
			echo "For more information see:\n";
			echo "http://doc.silverstripe.org/framework/en/topics/commandline\n";
			exit(1);
		}

		// Process the remaining arguments and load them into the get array.
		$get = array();

		if(count($_SERVER['argv']) > 1) {
			foreach(array_slice($_SERVER['argv'], 2) as $arg) {
				if(strpos($arg, '=') === false) {
					$get['args'][] = $arg;
				} else {
					if(substr($arg, 0, 2) == '--') {
						$arg = substr($arg, 2);
					}

					parse_str($arg, $parsed);
					$get = array_merge($get, $parsed);
				}
			}
		}

		// Set some sensible server defaults.
		$server = array_merge(
			array(
				'SERVER_PROTOCOL' => 'HTTP/1.1',
				'HTTP_ACCEPT' => 'text/plain;q=0.5',
				'HTTP_ACCEPT_LANGUAGE' => '*;q=0.5',
				'HTTP_ACCEPT_ENCODING' => '',
				'HTTP_ACCEPT_CHARSET' => 'ISO-8859-1;q=0.5',
				'SERVER_SIGNATURE' => 'Command-line PHP/' . phpversion(),
				'SERVER_SOFTWARE' => 'PHP/' . phpversion(),
				'SERVER_ADDR' => '127.0.0.1',
				'REMOTE_ADDR' => '127.0.0.1',
				'HTTP_USER_AGENT' => 'CLI',
			),
			$_SERVER
		);

		// Read host information for the file to URL mapping.
		if(isset($_FILE_TO_URL_MAPPING)) {
			$path = getcwd();

			while($path && $path != '/' && !preg_match('/^[A-Z]:\\\\$/', $path)) {
				if(isset($_FILE_TO_URL_MAPPING[$path])) {
					$url = $_FILE_TO_URL_MAPPING[$path];
					$url .= str_replace('\\', '/', substr(getcwd(), strlen($path)));

					$components = parse_url($url);

					$server['HTTP_HOST'] = $components['host'];
					$server['SERVER_NAME'] = $components['host'];
					$server['SCRIPT_NAME'] = $components['path'];
					$server['SCRIPT_FILENAME'] = getcwd() . '/' . $_SERVER['PHP_SELF'];

					if(!empty($components['port'])) {
						$server['SERVER_PORT'] = $components['port'];
						$server['HTTP_HOST'] = $server['HTTP_HOST'] . ':' . $components['port'];
					}

					break;
				}

				$path = dirname($path);
			}
		}

		return static::create(
			'GET',
			$_SERVER['argv'][1],
			$get,
			array(),
			array(),
			$server,
			file_get_contents('php://input')
		);
	}

	/**
	 * @deprecated 3.1 Use {@link HTTP::send_file()}.
	 */
	public static function send_file($data, $name, $mimeType = null) {
		Deprecation::notice('3.1', 'Use HTTP::send_file()');
		return HTTP::send_file($data, $name, $mimeType);
	}

	/**
	 * Creates a new request instances. The url, method, body and environment
	 * superglobals can either be passed in, or they will be read from the
	 * environment.
	 *
	 * @param string $method
	 * @param string $url
	 * @param string $body
	 * @param array $env
	 */
	public function __construct($method = null, $url = null, $body = null, $env = array()) {
		$this->method = $method;
		$this->body = isset($body) ? $body : file_get_contents('php://input');

		if(isset($url)) {
			$this->url = trim($url, '/');
		}

		$this->get = isset($env['get']) ? $env['get'] : $_GET;
		$this->post = isset($env['post']) ? $env['post'] : $_POST;
		$this->files = isset($env['files']) ? $env['files'] : $_FILES;
		$this->server = isset($env['server']) ? $env['server'] : $_SERVER;

		if($this->server) {
			$this->extractHeaders();
		}
	}

	/**
	 * Returns the HTTP method used for this request.
	 *
	 * The method can be set using a "X-HTTP-Method-Override" header or using
	 * a "_method" post var.
	 *
	 * @return string
	 * @throws Exception Throws an exception on an invalid method
	 */
	public function getMethod() {
		$result = $this->method;

		if($method = $this->getHeader('X-HTTP-Method-Override')) {
			$result = strtoupper($method);
		} elseif($method = $this->postVar('_method')) {
			$result = strtoupper($method);
		} elseif(!$result) {
			$result = strtoupper($this->serverVar('REQUEST_METHOD'));
		}

		if(!in_array($result, array('GET', 'POST', 'PUT', 'DELETE', 'HEAD'))) {
			throw new \Exception('An invalid HTTP method was specified');
		}

		return $result;
	}

	/**
	 * Returns the scheme, either http or https.
	 *
	 * @return string
	 */
	public function getScheme() {
		return $this->isSecure() ? 'https' : 'http';
	}

	/**
	 * @return string|null
	 */
	public function getUser() {
		return $this->serverVar('PHP_AUTH_USER');
	}

	/**
	 * @return string|null
	 */
	public function getPassword() {
		return $this->serverVar('PHP_AUTH_PW');
	}

	/**
	 * @return string
	 */
	public function getPort() {
		if($port = $this->getHeader('X-Forwarded-Port')) {
			return $port;
		} else {
			return $this->serverVar('SERVER_PORT');
		}
	}

	public function getHeader($name) {
		return $this->serverVar('HTTP_' . strtoupper(str_replace('-', '_', $name)));
	}

	public function setHeader($name, $value) {
		$this->server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
		parent::setHeader($name, $value);
	}

	/**
	 * @return string
	 */
	public function getHost() {
		if($this->host === null) {
			if($forward = $this->getHeader('X-Forwarded-Host')) {
				$this->host = strtok($forward, ',');
			} else {
				$this->host = $this->serverVar('HTTP_HOST');
			}
		}

		return $this->host;
	}

	/**
	 * @return string
	 */
	public function getSchemeAndHost() {
		return $this->getScheme() . '://' . $this->getHost();
	}

	/**
	 * Returns the URI for this request (URL including get parameters).
	 *
	 * @return string
	 */
	public function getRequestUri() {
		$uri = $this->getUrl();

		if($get = $this->getVars()) {
			$uri .= '?' . http_build_query($get, null, '&');
		}

		return $uri;
	}

	/**
	 * @return string
	 */
	public function getBaseUrl() {
		if($this->baseUrl === null) {
			$this->baseUrl = $this->extractBaseUrl();
		}

		return $this->baseUrl;
	}

	/**
	 * @return string
	 */
	public function getAbsoluteBaseUrl() {
		return $this->getSchemeAndHost() . $this->getBaseUrl();
	}

	/**
	 * Returns the URL for this request relative to the application root.
	 *
	 * @return string
	 */
	public function getUrl($includeGet = false) {
		if($includeGet) {
			Deprecation::notice('3.1', 'Use getRequestUri()');
			return $this->getRequestUri();
		}

		if($this->url === null) {
			$this->url = $this->extractUrl();
		}

		return $this->url;
	}

	/**
	 * @return string
	 */
	public function getExtension() {
		return pathinfo($this->getUrl(), PATHINFO_EXTENSION);
	}

	/**
	 * Gets the client IP this request was made from.
	 *
	 * @return string
	 */
	public function getIp() {
		if($ip = $this->getHeader('Client-Ip')) {
			return $ip;
		} elseif($ip = $this->getHeader('X-Forwarded-For')) {
			return $ip;
		} else {
			return $this->serverVar('REMOTE_ADDR');
		}
	}

	/**
	 * Gets an array of all mime types from the Accept header.
	 *
	 * @param bool $includeQuality Include quality (default false)
	 * @return array
	 */
	public function getAcceptMimeTypes($includeQuality = false) {
		$types = array();
		$typesWithQuality = explode(',', $this->getHeader('Accept'));

		if($includeQuality) {
			return $typesWithQuality;
		}

		foreach($typesWithQuality as $typeWithQuality) {
			$types[] = preg_replace('/;.*/', null, $typeWithQuality);
		}

		return $types;
	}

	/**
	 * @return array
	 */
	public function getVars() {
		return $this->get;
	}

	/**
	 * @param string $name
	 * @return string|array
	 */
	public function getVar($name) {
		if(isset($this->get[$name])) return $this->get[$name];
	}

	/**
	 * @return array
	 */
	public function postVars() {
		return $this->post;
	}

	/**
	 * @param string $name
	 * @return string|array
	 */
	public function postVar($name) {
		if(isset($this->post[$name])) return $this->post[$name];
	}

	/**
	 * @return array
	 */
	public function requestVars() {
		return ArrayLib::array_merge_recursive($this->getVars(), $this->postVars());
	}

	/**
	 * @param string $name
	 * @return string|array
	 */
	public function requestVar($name) {
		if(isset($this->post[$name])) {
			return $this->post[$name];
		} elseif(isset($this->get[$name])) {
			return $this->get[$name];
		}
	}

	/**
	 * @return array
	 */
	public function filesVars() {
		return $this->files;
	}

	/**
	 * @param string $name
	 * @return string|array
	 */
	public function filesVar($name) {
		if(isset($this->files[$name])) return $this->files[$name];
	}

	/**
	 * @return array
	 */
	public function serverVars() {
		return $this->server;
	}

	/**
	 * @param string $name
	 * @return string|array
	 */
	public function serverVar($name) {
		if(isset($this->server[$name])) return $this->server[$name];
	}

	/**
	 * Overrides the request superglobals with values from this request.
	 */
	public function setGlobals() {
		$_GET = $this->getVars();
		$_POST = $this->postVars();
		$_REQUEST = $this->requestVars();
		$_FILES = $this->filesVars();
		$_SERVER = $this->serverVars();
	}

	/**
	 * @return bool
	 */
	public function isGet() {
		return $this->getMethod() == 'GET';
	}

	/**
	 * @return bool
	 */
	public function isPost() {
		return $this->getMethod() == 'POST';
	}

	/**
	 * @return bool
	 */
	public function isPut() {
		return $this->getMethod() == 'PUT';
	}

	/**
	 * @return bool
	 */
	public function isDelete() {
		return $this->getMethod() == 'DELETE';
	}

	/**
	 * @return bool
	 */
	public function isHead() {
		return $this->getMethod() == 'HEAD';
	}

	/**
	 * Returns whether this request is secure (ie made over HTTPS).
	 *
	 * @return bool
	 */
	public function isSecure() {
		return (
			   strtolower($this->getHeader('X-Forwarded-Protocol')) == 'https'
			|| $this->serverVar('SSL')
			|| ($https = $this->serverVar('HTTPS')) && $https != 'off'
		);
	}

	/**
	 * Returns whether this request was made via an AJAX request.
	 *
	 * @return bool
	 */
	public function isAjax() {
		return $this->requestVar('ajax') || $this->getHeader('X-Requested-With') == 'XMLHttpRequest';
	}

	/**
	 * Checks if the extension for this request is for a common media type
	 * embedded into a page.
	 *
	 * @return bool
	 */
	public function isMedia() {
		return in_array($this->getExtension(), array(
			'css', 'js', 'jpg', 'jpeg', 'gif', 'png', 'bmp', 'ico'
		));
	}

	/**
	 * Returns whether a request var exists.
	 *
	 * @param string $offset
	 * @return bool
	 */
	public function offsetExists($offset) {
		return (bool) $this->requestVar($offset);
	}

	/**
	 * @see requestVar()
	 */
	public function offsetGet($offset) {
		return $this->requestVar($offset);
	}

	/**
	 * @ignore
	 */
	public function offsetSet($offset, $value) {
		throw new \Exception('You cannot call offsetSet on request instances');
	}

	/**
	 * @ignore
	 */
	public function offsetUnset($offset) {
		throw new \Exception('You cannot call offsetUnset on request instances');
	}

	protected function extractHeaders() {
		foreach($this->serverVars() as $key => $value) {
			if(substr($key, 0, 5) == 'HTTP_') {
				$key = substr($key, 5);
				$key = strtolower(str_replace('_', ' ', $key));
				$key = str_replace(' ', '-', ucwords($key));

				$this->setHeader($key, $value);
			}
		}

		if(isset($server['CONTENT_TYPE'])) {
			$this->setHeader('Content-Type', $server['CONTENT_TYPE']);
		}

		if(isset($server['CONTENT_LENGTH'])) {
			$this->setHeader('Content-Length', $server['CONTENT_LENGTH']);
		}
	}

	protected function extractBaseUrl() {
		$scriptFilename = realpath($this->serverVar('SCRIPT_FILENAME'));
		$scriptName = $this->serverVar('SCRIPT_NAME');

		// Determine the base URL by comparing SCRIPT_NAME to SCRIPT_FILENAME
		// and getting common elements
		if(strpos($scriptFilename, BASE_PATH) === 0) {
			$remove = substr($scriptFilename, strlen(BASE_PATH));

			if(substr($scriptName, -strlen($remove)) == $remove) {
				return substr($scriptName, 0, -strlen($remove)) . '/';
			}
		}

		// If that didn't work, failover to the old syntax.  Hopefully this
		// isn't necessary, and maybe if can be phased out?
		if(strpos($scriptName, 'index.php') !== false) {
			$base = dirname($scriptName);
		} else {
			$base = dirname(dirname($scriptName));
		}

		return $base . '/';
	}

	/**
	 * Extracts the URL from the server vars, not including the get params. This
	 * code is adapted from the Zend Framework.
	 *
	 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
	 * @license http://framework.zend.com/license/new-bsd New BSD License
	 */
	protected function extractUrl() {
		// First check the path info for if we are running withour rewrites.
		if($pathInfo = $this->serverVar('PATH_INFO')) {
			return trim($pathInfo, '/');
		}

		$baseUrl = $this->getBaseUrl();
		$requestUri = null;

		// Check this first so IIS will catch.
		$httpXRewriteUrl = $this->serverVar('HTTP_X_REWRITE_URL');
		if($httpXRewriteUrl !== null) {
			$requestUri = $httpXRewriteUrl;
		}

		// Check for IIS 7.0 or later with ISAPI_Rewrite
		$httpXOriginalUrl = $this->serverVar('HTTP_X_REWRITE_URL');
		if($httpXOriginalUrl !== null) {
			$requestUri = $httpXOriginalUrl;
		}

		// IIS7 with URL Rewrite: make sure we get the unencoded url
		// (double slash problem).
		$iisUrlRewritten = $this->serverVar('HTTP_X_REWRITE_URL');
		$unencodedUrl = $this->serverVar('HTTP_X_REWRITE_URL');
		if('1' == $iisUrlRewritten && '' !== $unencodedUrl) {
			$requestUri = $unencodedUrl;
		}

		// HTTP proxy requests setup request URI with scheme and host [and port]
		// + the URL path, only use URL path.
		if(!$requestUri && !$httpXRewriteUrl) {
			$requestUri = $this->serverVar('REQUEST_URI');
		}

		if($requestUri !== null) {
			$requestUri = preg_replace('#^[^:]+://[^/]+#', '', $requestUri);
		}

		// IIS 5.0, PHP as CGI.
		$origPathInfo = $this->serverVar('HTTP_X_REWRITE_URL');
		if(!$requestUri && $origPathInfo !== null) {
			$requestUri =  $origPathInfo;
		}

		if($baseUrl && strpos($requestUri, $baseUrl) === 0) {
			$requestUri = substr($requestUri, strlen($baseUrl));
		}

		if(($pos = strpos($requestUri, '?')) !== false) {
			$requestUri = substr($requestUri, 0, $pos);
		}

		return trim($requestUri, '/');
	}

	/**
	 * @deprecated 3.1 Use {@link getMethod()}.
	 */
	public function httpMethod() {
		Deprecation::notice('3.1', 'Use SS_HTTPRequest->getMethod().');
		return $this->getMethod();
	}

}
