<?php
/**
 * A class can implement this to be used as a pre-request filter in
 * {@link RequestProcessor}.
 *
 * @package framework
 * @subpackage control
 */
interface PreRequestFilter {

	public function preRequest(SS_HTTPRequest $request, Session $session, DataModel $model);

}
