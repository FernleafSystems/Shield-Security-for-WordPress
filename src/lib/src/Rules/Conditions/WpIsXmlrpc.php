<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Services\Services;

class WpIsXmlrpc extends Base {

	const SLUG = 'wp_is_xmlrpc';

	protected function execConditionCheck() :bool {
		return $this->getCon()->req->wp_is_xmlrpc ?? Services::WpGeneral()->isXmlrpc();
	}
}