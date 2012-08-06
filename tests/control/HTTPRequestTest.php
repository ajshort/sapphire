<?php
/**
 * @package framework
 * @subpackage tests
 */
class HTTPRequestTest extends SapphireTest {

	public function testGetMethod() {
		$request = SS_HTTPRequest::create(
			'GET',
			'admin/crm'
		);
		$this->assertTrue(
			$request->isGET(),
			'GET with no method override'
		);

		$request = SS_HTTPRequest::create(
			'POST',
			'admin/crm'
		);
		$this->assertTrue(
			$request->isPOST(),
			'POST with no method override'
		);

		$request = SS_HTTPRequest::create(
			'GET',
			'admin/crm',
			array('_method' => 'DELETE')
		);
		$this->assertTrue(
			$request->isGET(),
			'GET with invalid POST method override'
		);

		$request = SS_HTTPRequest::create(
			'POST',
			'admin/crm',
			array(),
			array('_method' => 'DELETE')
		);
		$this->assertTrue(
			$request->isDELETE(),
			'POST with valid method override to DELETE'
		);

		$request = SS_HTTPRequest::create(
			'POST',
			'admin/crm',
			array(),
			array('_method' => 'put')
		);
		$this->assertTrue(
			$request->isPUT(),
			'POST with valid method override to PUT'
		);

		$request = SS_HTTPRequest::create(
			'POST',
			'admin/crm',
			array(),
			array('_method' => 'head')
		);
		$this->assertTrue(
			$request->isHEAD(),
			'POST with valid method override to HEAD '
		);

		$request = SS_HTTPRequest::create(
			'POST',
			'admin/crm',
			array(),
			array('_method' => 'head')
		);
		$this->assertTrue(
			$request->isHEAD(),
			'POST with valid method override to HEAD'
		);

		$request = SS_HTTPRequest::create(
			'POST',
			'admin/crm',
			array('_method' => 'head')
		);
		$this->assertTrue(
			$request->isPOST(),
			'POST with invalid method override by GET parameters to HEAD'
		);
	}

	public function testScheme() {
		$request = new SS_HTTPRequest('GET', '');
		$this->assertFalse($request->isSecure());
		$this->assertEquals('http', $request->getScheme());

		$request = new SS_HTTPRequest('GET', '', null, array(
			'server' => array('HTTP_X_FORWARDED_PROTOCOL' => 'https')
		));
		$this->assertTrue($request->isSecure());
		$this->assertEquals('https', $request->getScheme());

		$request = new SS_HTTPRequest('GET', '', null, array(
			'server' => array('SSL' => true)
		));
		$this->assertTrue($request->isSecure());
		$this->assertEquals('https', $request->getScheme());

		$request = new SS_HTTPRequest('GET', '', null, array(
			'server' => array('HTTPS' => 'off')
		));
		$this->assertFalse($request->isSecure());
		$this->assertEquals('http', $request->getScheme());

		$request = new SS_HTTPRequest('GET', '', null, array(
			'server' => array('HTTPS' => 'on')
		));
		$this->assertTrue($request->isSecure());
		$this->assertEquals('https', $request->getScheme());
	}

	public function testCredentials() {
		$request = new SS_HTTPRequest(null, null, null, array(
			'server' => array('PHP_AUTH_USER' => 'user', 'PHP_AUTH_PW' => 'pass')
		));
		$this->assertEquals('user', $request->getUser());
		$this->assertEquals('pass', $request->getPassword());
	}

	public function testGetPort() {
		$request = new SS_HTTPRequest(null, null, null, array(
			'server' => array('SERVER_PORT' => 8080)
		));
		$this->assertEquals(8080, $request->getPort());
	}

	public function testHeaders() {
		$request = new SS_HTTPRequest(null, null, null, array(
			'server' => array('HTTP_HEADER' => 'value')
		));

		$this->assertEquals('value', $request->getHeader('header'));
		$this->assertEquals('value', $request->getHeader('Header'));

		$request->setHeader('Another-Header', 'another');
		$this->assertEquals('another', $request->getHeader('Another-Header'));
		$this->assertEquals('another', $request->serverVar('HTTP_ANOTHER_HEADER'));
	}

	public function testGetHost() {
		$request = new SS_HTTPRequest(null, null, null, array(
			'server' => array('HTTP_X_FORWARDED_HOST' => 'first, second')
		));
		$this->assertEquals('first', $request->getHost());

		$request = new SS_HTTPRequest(null, null, null, array(
			'server' => array('HTTP_HOST' => 'host')
		));
		$this->assertEquals('host', $request->getHost());
	}

	public function testGetSchemeAndHost() {
		$request = new SS_HTTPRequest(null, null, null, array(
			'server' => array('SSL' => true, 'HTTP_HOST' => 'example.com')
		));
		$this->assertEquals('https://example.com', $request->getSchemeAndHost());
	}

	public function testGetBaseUrl() {
		$root = new SS_HTTPRequest(null, null, null, array(
			'server' => array(
				'SCRIPT_FILENAME' => BASE_PATH . '/framework/main.php',
				'SCRIPT_NAME' => '/framework/main.php'
			)
		));
		$this->assertEquals('/', $root->getBaseUrl());

		$sub = new SS_HTTPRequest(null, null, null, array(
			'server' => array(
				'SCRIPT_FILENAME' => BASE_PATH . '/framework/main.php',
				'SCRIPT_NAME' => '/subfolder/framework/main.php'
			)
		));
		$this->assertEquals('/subfolder/', $sub->getBaseUrl());
	}

	public function testGetUrl() {
		$req = SS_HTTPRequest::create('GET', '/');
		$this->assertEquals('', $req->getURL());

		$req = SS_HTTPRequest::create('GET', '/assets/somefile.gif');
		$this->assertEquals('assets/somefile.gif', $req->getURL());

		$req = SS_HTTPRequest::create('GET', '/home?test=1');
		$this->assertEquals('home?test=1', $req->getRequestUri());
		$this->assertEquals('home', $req->getURL());
	}

	public function testGetExtension() {
		$request = new SS_HTTPRequest('GET', '/page');
		$this->assertEquals('', $request->getExtension());

		$request = new SS_HTTPRequest('GET', '/assets/file.extension');
		$this->assertEquals('extension', $request->getExtension());
	}

	public function testGetIp() {
		$request = new SS_HTTPRequest(null, null, null, array(
			'server' => array('HTTP_CLIENT_IP' => '127.0.0.1')
		));
		$this->assertEquals('127.0.0.1', $request->getIp());

		$request = new SS_HTTPRequest(null, null, null, array(
			'server' => array('HTTP_X_FORWARDED_FOR' => '127.0.0.1')
		));
		$this->assertEquals('127.0.0.1', $request->getIp());

		$request = new SS_HTTPRequest(null, null, null, array(
			'server' => array('REMOTE_ADDR' => '127.0.0.1')
		));
		$this->assertEquals('127.0.0.1', $request->getIp());
	}

	public function testGetAcceptMimeTypes() {
		$request = new SS_HTTPRequest(null, null, null, array(
			'server' => array('HTTP_ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8')
		));

		$this->assertEquals(
			array('text/html', 'application/xhtml+xml', 'application/xml', '*/*'),
			$request->getAcceptMimeTypes()
		);

		$this->assertEquals(
			array(
				'text/html',
				'application/xhtml+xml',
				'application/xml;q=0.9',
				'*/*;q=0.8'
			),
			$request->getAcceptMimeTypes(true)
		);
	}

	public function testGetVars() {
		$request = new SS_HTTPRequest(null, null, null, array(
			'get' => array('param' => 1),
			'post' => array('param' => 2, 'post' => 3)
		));
		$this->assertEquals(array('param' => 1), $request->getVars());
		$this->assertEquals(1, $request->getVar('param'));
		$this->assertNull($request->getVar('post'));
	}

	public function testPostVars() {
		$request = new SS_HTTPRequest(null, null, null, array(
			'get' => array('param' => 1, 'get' => 2),
			'post' => array('param' => 3)
		));
		$this->assertEquals(array('param' => 3), $request->postVars());
		$this->assertEquals(3, $request->postVar('param'));
		$this->assertNull($request->postVar('get'));
	}

	public function testRequestVars() {
		$getVars = array(
			'first' => 'a',
			'second' => 'b',
		);
		$postVars = array(
			'third' => 'c',
			'fourth' => 'd',
		);
		$requestVars = array(
			'first' => 'a',
			'second' => 'b',
			'third' => 'c',
			'fourth' => 'd',
		);
		$request = SS_HTTPRequest::create(
			'POST',
			'admin/crm',
			$getVars,
			$postVars
		);
		$this->assertEquals(
			$requestVars,
			$request->requestVars(),
			'GET parameters should supplement POST parameters'
		);

		$getVars = array(
			'first' => 'a',
			'second' => 'b',
		);
		$postVars = array(
			'first' => 'c',
			'third' => 'd',
		);
		$requestVars = array(
			'first' => 'c',
			'second' => 'b',
			'third' => 'd',
		);
		$request = SS_HTTPRequest::create(
			'POST',
			'admin/crm',
			$getVars,
			$postVars
		);
		$this->assertEquals(
			$requestVars,
			$request->requestVars(),
			'POST parameters should override GET parameters'
		);

		$getVars = array(
			'first' => array(
				'first' => 'a',
			),
			'second' => array(
				'second' => 'b',
			),
		);
		$postVars = array(
			'first' => array(
				'first' => 'c',
			),
			'third' => array(
				'third' => 'd',
			),
		);
		$requestVars = array(
			'first' => array(
				'first' => 'c',
			),
			'second' => array(
				'second' => 'b',
			),
			'third' => array(
				'third' => 'd',
			),
		);
		$request = SS_HTTPRequest::create(
			'POST',
			'admin/crm',
			$getVars,
			$postVars
		);
		$this->assertEquals(
			$requestVars,
			$request->requestVars(),
			'Nested POST parameters should override GET parameters'
		);

		$getVars = array(
			'first' => array(
				'first' => 'a',
			),
			'second' => array(
				'second' => 'b',
			),
		);
		$postVars = array(
			'first' => array(
				'second' => 'c',
			),
			'third' => array(
				'third' => 'd',
			),
		);
		$requestVars = array(
			'first' => array(
				'first' => 'a',
				'second' => 'c',
			),
			'second' => array(
				'second' => 'b',
			),
			'third' => array(
				'third' => 'd',
			),
		);
		$request = SS_HTTPRequest::create(
			'POST',
			'admin/crm',
			$getVars,
			$postVars
		);
		$this->assertEquals(
			$requestVars,
			$request->requestVars(),
			'Nested GET parameters should supplement POST parameters'
		);
	}

	public function testSetGlobals() {
		$get = $_GET;
		$post = $_POST;
		$files = $_FILES;
		$server = $_SERVER;

		$req = new SS_HTTPRequest('GET', '', null, array(
			'get' => array('get'),
			'post' => array('post'),
			'files' => array('files'),
			'server' => array('server')
		));
		$req->setGlobals();

		$this->assertEquals(array('get'), $_GET);
		$this->assertEquals(array('post'), $_POST);
		$this->assertEquals(array('files'), $_FILES);
		$this->assertEquals(array('server'), $_SERVER);

		$_GET = $get;
		$_POST = $post;
		$_FILES = $files;
		$_SERVER = $server;
	}

	public function testIsAjax() {
		$req = SS_HTTPRequest::create('GET', '/', array('ajax' => 0));
		$this->assertFalse($req->isAjax());

		$req = SS_HTTPRequest::create('GET', '/', array('ajax' => 1));
		$this->assertTrue($req->isAjax());

		$req = SS_HTTPRequest::create('GET', '/');
		$req->addHeader('X-Requested-With', 'XMLHttpRequest');
		$this->assertTrue($req->isAjax());
	}

}
