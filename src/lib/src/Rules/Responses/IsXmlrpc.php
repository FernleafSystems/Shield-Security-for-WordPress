<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

class IsXmlrpc extends Base {

	const SLUG = 'is_xmlrpc';

	protected function execResponse() :bool {
		$this->getCon()->req->is_xmlrpc = true;
		return true;
	}
}