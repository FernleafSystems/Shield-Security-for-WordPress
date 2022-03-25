<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

class WpIsXmlrpc extends Base {

	const SLUG = 'wp_is_xmlrpc';

	protected function execResponse() :bool {
		$this->getCon()->req->wp_is_xmlrpc = true;
		return true;
	}
}