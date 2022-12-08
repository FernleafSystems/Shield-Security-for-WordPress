<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Services\Services;

class WpIsXmlrpc extends Base {

	public const SLUG = 'wp_is_xmlrpc';

	protected function execConditionCheck() :bool {
		$thisReq = $this->getCon()->this_req;
		if ( !isset( $thisReq->wp_is_xmlrpc ) ) {
			$thisReq->wp_is_xmlrpc = Services::WpGeneral()->isXmlrpc();
		}
		return $thisReq->wp_is_xmlrpc;
	}
}