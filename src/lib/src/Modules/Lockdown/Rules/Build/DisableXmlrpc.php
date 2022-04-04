<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Lockdown\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\BuildRuleCoreShieldBase,
	Conditions,
	Responses
};

class DisableXmlrpc extends BuildRuleCoreShieldBase {

	const SLUG = 'shield/disable_xmlrpc';

	protected function getName() :string {
		return 'Disable XMLRPC';
	}

	protected function getDescription() :string {
		return 'Disable XML-RPC if required.';
	}

	protected function getConditions() :array {
		return [
			'logic' => static::LOGIC_AND,
			'group' => [
				[
					'rule'         => Shield\Modules\Plugin\Rules\Build\RequestBypassesAllRestrictions::SLUG,
					'invert_match' => true
				],
				[
					'condition' => Conditions\WpIsXmlrpc::SLUG,
				],
			]
		];
	}

	protected function getResponses() :array {
		return [
			[
				'response' => Responses\DisableXmlrpc::SLUG,
			],
		];
	}
}