<?php
/**
 * Filters can be attached to this to run either pre or post-request.
 *
 * @package framework
 * @subpackage control
 */
class RequestProcessor {

	private $filters = array();

	public function __construct($filters = array()) {
		$this->filters = $filters;
	}

	public function setFilters($filters) {
		$this->filters = $filters;
	}

	public function preRequest(SS_HTTPRequest $request, Session $session, DataModel $model) {
		foreach($this->filters as $filter) {
			if($filter instanceof PreRequestFilter) {
				$res = $filter->preRequest($request, $session, $model);

				if($res === false) {
					return false;
				}
			}
		}
	}

	/**
	 * Filter executed AFTER a request
	 */
	public function postRequest(SS_HTTPRequest $request, SS_HTTPResponse $response, DataModel $model) {
		foreach($this->filters as $filter) {
			if($filter instanceof PostRequestFilter) {
				$res = $filter->postRequest($request, $response, $model);

				if($res === false) {
					return false;
				}
			}
		}
	}

}