<?php
/**
 * A class can implement this to be used as a post-request filter in
 * {@link RequestProcessor}.
 *
 * @package framework
 * @subpackage control
 */
interface PostRequestFilter {

	public function postRequest(SS_HTTPRequest $request, SS_HTTPResponse $response, DataModel $model);

}
