<?php
/**
 * Outputs an error message if an error response has no body.
 *
 * @package framework
 * @subpackage view
 */
class DebugRequestFilter implements PostRequestFilter {

	public function postRequest(SS_HTTPRequest $request, SS_HTTPResponse $response, DataModel $model) {
		if($response->isError() && !$response->getBody()) {
			Debug::friendlyError($response->getStatusCode(), $response->getStatusDescription());
		}
	}

}
