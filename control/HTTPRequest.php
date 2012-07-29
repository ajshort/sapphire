<?php

/**
 * Represents a HTTP-request, including a URL that is tokenised for parsing, and a request method (GET/POST/PUT/DELETE).
 * This is used by {@link RequestHandler} objects to decide what to do.
 * 
 * The intention is that a single SS_HTTPRequest object can be passed from one object to another, each object calling
 * match() to get the information that they need out of the URL.  This is generally handled by 
 * {@link RequestHandler::handleRequest()}.
 * 
 * @todo Accept X_HTTP_METHOD_OVERRIDE http header and $_REQUEST['_method'] to override request types (useful for webclients
 *   not supporting PUT and DELETE)
 * 
 * @package framework
 * @subpackage control
 */
class SS_HTTPRequest extends SS_HttpMessage implements ArrayAccess {

	/**
	 * @var string $url
	 */
	protected $url;

	/**
	 * The non-extension parts of the passed URL as an array, originally exploded by the "/" separator.
	 * All elements of the URL are loaded in here,
	 * and subsequently popped out of the array by {@link shift()}.
	 * Only use this structure for internal request handling purposes.
	 */
	protected $dirParts;

	/**
	 * @var string $httpMethod The HTTP method in all uppercase: GET/PUT/POST/DELETE/HEAD
	 */
	protected $httpMethod;
	
	/**
	 * @var array $getVars Contains alls HTTP GET parameters passed into this request.
	 */
	protected $getVars = array();
	
	/**
	 * @var array $postVars Contains alls HTTP POST parameters passed into this request.
	 */
	protected $postVars = array();

	/**
	 * Construct a SS_HTTPRequest from a URL relative to the site root.
	 */
	function __construct($httpMethod, $url, $getVars = array(), $postVars = array(), $body = null) {
		$this->httpMethod = strtoupper(self::detect_method($httpMethod, $postVars));
		$this->url = $url;

		// Normalize URL if its relative (strictly speaking), or has leading slashes
		if(Director::is_relative_url($url) || preg_match('/^\//', $url)) {
			$this->url = preg_replace(array('/\/+/','/^\//', '/\/$/'),array('/','',''), $this->url);
		}

		if($this->url) $this->dirParts = preg_split('|/+|', $this->url);
		else $this->dirParts = array();
		
		$this->getVars = (array)$getVars;
		$this->postVars = (array)$postVars;
		$this->body = $body;
	}
	
	function isGet() {
		return $this->httpMethod == 'GET';
	}
	
	function isPost() {
		return $this->httpMethod == 'POST';
	}
	
	function isPut() {
		return $this->httpMethod == 'PUT';
	}

	function isDelete() {
		return $this->httpMethod == 'DELETE';
	}	

	function isHead() {
		return $this->httpMethod == 'HEAD';
	}	

	function getVars() {
		return $this->getVars;
	}
	function postVars() {
		return $this->postVars;
	}
	
	/**
	 * Returns all combined HTTP GET and POST parameters
	 * passed into this request. If a parameter with the same
	 * name exists in both arrays, the POST value is returned.
	 * 
	 * @return array
	 */
	function requestVars() {
		return ArrayLib::array_merge_recursive($this->getVars, $this->postVars);
	}
	
	function getVar($name) {
		if(isset($this->getVars[$name])) return $this->getVars[$name];
	}
	
	function postVar($name) {
		if(isset($this->postVars[$name])) return $this->postVars[$name];
	}
	
	function requestVar($name) {
		if(isset($this->postVars[$name])) return $this->postVars[$name];
		if(isset($this->getVars[$name])) return $this->getVars[$name];
	}

	/**
	 * Returns the extension included in the URL.
	 *
	 * @return string
	 */
	public function getExtension() {
		return pathinfo($this->getURL(), PATHINFO_EXTENSION);
	}

	/**
	 * Checks if the {@link SS_HTTPRequest->getExtension()} on this request matches one of the more common media types
	 * embedded into a webpage - e.g. css, png.
	 *
	 * This is useful for things like determining wether to display a fully rendered error page or not. Note that the
	 * media file types is not at all comprehensive.
	 *
	 * @return bool
	 */
	public function isMedia() {
		return in_array($this->getExtension(), array('css', 'js', 'jpg', 'jpeg', 'gif', 'png', 'bmp', 'ico'));
	}

	/**
	 * Returns the URL used to generate the page
	 *
	 * @param bool $includeGetVars whether or not to include the get parameters\
	 * 
	 * @return string
	 */
	function getUrl($includeGetVars = false) {
		$url = ($this->getExtension()) ? $this->url . '.' . $this->getExtension() : $this->url; 

		 if ($includeGetVars) { 
		 	// if we don't unset $vars['url'] we end up with /my/url?url=my/url&foo=bar etc 
 			
 			$vars = $this->getVars();
 			unset($vars['url']);

 			if (count($vars)) {
 				$url .= '?' . http_build_query($vars);
 			}
 		}
 		else if(strpos($url, "?") !== false) {
 			$url = substr($url, 0, strpos($url, "?"));
 		}

 		return $url; 
	}

	/**
	 * Returns true if this request an ajax request,
	 * based on custom HTTP ajax added by common JavaScript libraries,
	 * or based on an explicit "ajax" request parameter.
	 * 
	 * @return boolean
	 */
	function isAjax() {
		return (
			$this->requestVar('ajax') ||
			$this->getHeader('X-Requested-With') && $this->getHeader('X-Requested-With') == "XMLHttpRequest"
		);
	}
	
	/**
	 * Enables the existence of a key-value pair in the request to be checked using
	 * array syntax, so isset($request['title']) will check for $_POST['title'] and $_GET['title']
	 *
	 * @param unknown_type $offset
	 * @return boolean
	 */
	function offsetExists($offset) {
		if(isset($this->postVars[$offset])) return true;
		if(isset($this->getVars[$offset])) return true;
		return false;
	}
	
	/**
	 * Access a request variable using array syntax. eg: $request['title'] instead of $request->postVar('title')
	 *
	 * @param unknown_type $offset
	 * @return unknown
	 */
	function offsetGet($offset) {
		return $this->requestVar($offset);
	}
	
	/**
	 * @ignore
	 */
	function offsetSet($offset, $value) {}
	
	/**
	 * @ignore
	 */
	function offsetUnset($offset) {}
	
	/**
	 * Construct an SS_HTTPResponse that will deliver a file to the client
	 */
	static function send_file($fileData, $fileName, $mimeType = null) {
		if(!$mimeType) {
			$mimeType = HTTP::get_mime_type($fileName);
		}
		$response = new SS_HTTPResponse($fileData);
		$response->addHeader("Content-Type", "$mimeType; name=\"" . addslashes($fileName) . "\"");
		$response->addHeader("Content-disposition", "attachment; filename=" . addslashes($fileName));
		$response->addHeader("Content-Length", strlen($fileData));
		$response->addHeader("Pragma", ""); // Necessary because IE has issues sending files over SSL
		
		if(strstr($_SERVER["HTTP_USER_AGENT"],"MSIE") == true) {
			$response->addHeader('Cache-Control', 'max-age=3, must-revalidate'); // Workaround for IE6 and 7
		}
		
		return $response;
	}

	/**
	 * Returns the client IP address which
	 * originated this request.
	 *
	 * @return string
	 */
	function getIp() {
		if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
	  		//check ip from share internet
			return $_SERVER['HTTP_CLIENT_IP'];
		} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
	  		//to check ip is pass from proxy
			return  $_SERVER['HTTP_X_FORWARDED_FOR'];
		} elseif(isset($_SERVER['REMOTE_ADDR'])) {
			return $_SERVER['REMOTE_ADDR'];
		}
	}
	
	/**
	 * Returns all mimetypes from the HTTP "Accept" header
	 * as an array.
	 * 
	 * @param boolean $includeQuality Don't strip away optional "quality indicators", e.g. "application/xml;q=0.9" (Default: false)
	 * @return array
	 */
	function getAcceptMimeTypes($includeQuality = false) {
	   $mimetypes = array();
	   $mimetypesWithQuality = explode(',',$this->getHeader('Accept'));
	   foreach($mimetypesWithQuality as $mimetypeWithQuality) {
	      $mimetypes[] = ($includeQuality) ? $mimetypeWithQuality : preg_replace('/;.*/', '', $mimetypeWithQuality);
	   }
	   return $mimetypes;
	}
	
	/**
	 * @return string HTTP method (all uppercase)
	 */
	public function getMethod() {
		return $this->httpMethod;
	}
	
	/**
	 * Gets the "real" HTTP method for a request.
	 * 
	 * Used to work around browser limitations of form
	 * submissions to GET and POST, by overriding the HTTP method
	 * with a POST parameter called "_method" for PUT, DELETE, HEAD.
	 * Using GET for the "_method" override is not supported,
	 * as GET should never carry out state changes.
	 * Alternatively you can use a custom HTTP header 'X-HTTP-Method-Override'
	 * to override the original method in {@link Director::direct()}. 
	 * The '_method' POST parameter overrules the custom HTTP header.
	 *
	 * @param string $origMethod Original HTTP method from the browser request
	 * @param array $postVars
	 * @return string HTTP method (all uppercase)
	 */
	public static function detect_method($origMethod, $postVars) {
		if(isset($postVars['_method'])) {
			if(!in_array(strtoupper($postVars['_method']), array('GET','POST','PUT','DELETE','HEAD'))) {
				user_error('Director::direct(): Invalid "_method" parameter', E_USER_ERROR);
			}
			return strtoupper($postVars['_method']);
		} else {
			return $origMethod;
		}
	}

	/**
	 * @deprecated 3.1 Use {@link getMethod()}.
	 */
	public function httpMethod() {
		Deprecation::notice('3.1', 'Use getMethod().');
		return $this->getMethod();
	}

}
