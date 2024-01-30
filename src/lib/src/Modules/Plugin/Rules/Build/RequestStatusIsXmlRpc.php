<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\WpIsXmlrpc;

/**
 * @deprecated 18.6
 */
class RequestStatusIsXmlRpc extends RequestStatusBase {

	public const SLUG = 'shield/request_status_is_xmlrpc';

	protected function getName() :string {
		return 'Is XML-RPC?';
	}

	protected function getConditions() :array {
		return [
			'conditions' => WpIsXmlrpc::class,
		];
	}
}