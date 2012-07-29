<?php
/**
 * @package framework
 * @subpackage tests
 */
class RouterTest extends SapphireTest {

	public function testRouting() {
		$router = new Router();
		$router->setRules(array('GET ' => 'get', 'POST ' => 'post'));

		$get = new RoutedRequest('GET', '');
		$post = new RoutedRequest('POST', '');
		$del = new RoutedRequest('DELETE', '');

		$this->assertEquals('get', $router->route($get));
		$this->assertEquals('post', $router->route($post));
		$this->assertNull($router->route($del));
	}

	public function testRootController() {
		$router = new Router();
		$router->setRules(array('' => 'root'));

		$root = new RoutedRequest('GET', '/');
		$page = new RoutedRequest('POST', '/page');

		$this->assertEquals('root', $router->route($root));
		$this->assertNull($router->route($page));
	}

	public function testParams() {
		$router = new Router();
		$router->setRules(array('$Action//$ID!' => '$Action'));

		$meth = new RoutedRequest('GET', 'method');
		$id = new RoutedRequest('GET', 'method/1');

		$this->assertNull($router->route($meth));
		$this->assertEquals('$Action', $router->route($id));

		$this->assertTrue($id->isAllParsed());
		$this->assertEquals(1, $id->getUnshiftedButParsed());
		$this->assertEquals('1', $id->getParam('ID'));
	}

	public function testRepeatRouting() {
		$router = new Router();
		$request = new RoutedRequest('GET', 'page/Form/field/Name/action');
		$router->setRequest($request);

		$this->assertEquals('$Action', $router->route(null, array(
			'$URLSegment/$Action//$ID/$OtherID' => '$Action'
		)));
		$this->assertEquals(
			array(
				'URLSegment' => 'page',
				'Action' => 'Form',
				'ID' => 'field',
				'OtherID' => 'Name'
			),
			$request->getParams()
		);
		$this->assertEquals(2, $request->getUnshiftedButParsed());
		$this->assertEquals('field/Name/action', $request->getRemainingUrl());

		$this->assertEquals('handleField', $router->route(null, array(
			'field/$Name' => 'handleField'
		)));
		$this->assertEquals(
			array(
				'URLSegment' => 'page',
				'Action' => 'Form',
				'ID' => 'field',
				'OtherID' => 'Name',
				'Name' => 'Name'
			),
			$request->getParams()
		);
		$this->assertEquals('action', $request->getRemainingUrl());

		$this->assertEquals('get', $router->route(null, array(
			'POST action' => 'post',
			'GET action' => 'get'
		)));
		$this->assertTrue($request->isAllParsed());
	}

}
