<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\BuildRuleCoreShieldBase,
	Build\RuleTraits,
	Conditions,
	Responses
};

class IpWhitelisted extends BuildRuleCoreShieldBase {

	use RuleTraits\InstantExec;

	public const SLUG = 'shield/is_ip_whitelisted';

	protected function getName() :string {
		return 'Is IP Whitelisted';
	}

	protected function getDescription() :string {
		return 'Test whether the current Request IP is whitelisted.';
	}

	protected function getConditions() :array {
		return [
			'logic' => static::LOGIC_AND,
			'group' => [
				[
					'condition' => Conditions\IsIpWhitelisted::SLUG
				],
			]
		];
	}

	protected function getResponses() :array {
		return [
			[
				'response' => Responses\SetIpWhitelisted::SLUG,
			],
		];
	}
}