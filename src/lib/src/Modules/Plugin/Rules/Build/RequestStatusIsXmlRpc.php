<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\BuildRuleCoreShieldBase,
	Conditions
};

class RequestStatusIsXmlRpc extends BuildRuleCoreShieldBase {

	const SLUG = 'shield/request_status_is_xmlrpc';

	protected function getName() :string {
		return 'Is XML-RPC';
	}

	protected function getDescription() :string {
		return 'Request Status - Is XML-RPC.';
	}

	protected function getConditions() :array {
		return [
			'logic' => static::LOGIC_OR,
			'group' => [
				[
					'condition' => Conditions\WpIsXmlrpc::SLUG,
				],
			]
		];
	}
}