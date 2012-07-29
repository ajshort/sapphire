<?php
/**
 * @package framework
 * @subpackage tests
 */
class RoutedRequestTest extends SapphireTest {

	public function testShifting() {
		$request = new RoutedRequest('GET', '/first/second/third');
		$this->assertEquals(array('first', 'second', 'third'), $request->getUrlParts());
		$this->assertEquals('first/second/third', $request->getRemainingUrl());
		$this->assertFalse($request->isAllParsed());

		$this->assertEquals('first', $request->shift());
		$this->assertEquals(array('second', 'third'), $request->getUrlParts());
		$this->assertEquals('second/third', $request->getRemainingUrl());
		$this->assertFalse($request->isAllParsed());

		$this->assertEquals(array('second', 'third'), $request->shift(2));
		$this->assertEquals('', $request->getRemainingUrl());
		$this->assertEquals(array(), $request->getUrlParts());
		$this->assertTrue($request->isAllParsed());
	}

	public function testParams() {
		$request = new RoutedRequest('GET', '');

		$first = array('a' => '1');
		$second = array('a' => '', 'b' => '2');
		$third = array('c' => '3', 'd' => '4');

		$this->assertEquals(array(), $request->getParams());
		$this->assertEquals(array(), $request->getLatestParams());

		$request->pushParams($first);
		$this->assertEquals($first, $request->getParams());
		$this->assertEquals($first, $request->getLatestParams());

		$request->pushParams($second);
		$this->assertEquals(array('a' => '1', 'b' => '2'), $request->getParams());
		$this->assertEquals($second, $request->getLatestParams());
		$this->assertEquals('1', $request->getParam('a'));
		$this->assertEquals('', $request->getLatestParam('a'));

		$request->pushParams($third);
		$request->shiftParams();
		$this->assertEquals(array('a' => '2', 'b' => '3', 'c' => '4', 'd' => null), $request->getParams());
		$request->shiftParams();
		$this->assertEquals(array('a' => '3', 'b' => '4', 'c' => null, 'd' => null), $request->getParams());
	}

	public function testIsAllParsed() {
		$request = new RoutedRequest('GET', 'first/second');
		$this->assertFalse($request->isAllParsed());

		$request->setUnshiftedButParsed(1);
		$this->assertFalse($request->isAllParsed());

		$request->setUnshiftedButParsed(2);
		$this->assertTrue($request->isAllParsed());
	}

}
