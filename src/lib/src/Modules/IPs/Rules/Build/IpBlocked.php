<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\BuildRuleCoreShieldBase,
	Conditions,
	Responses
};

class IpBlocked extends BuildRuleCoreShieldBase {

	const SLUG = 'shield/is_ip_blocked';

	protected function getName() :string {
		return 'Is IP Blocked';
	}

	protected function getDescription() :string {
		return 'Test whether the current Request IP is Blocked.';
	}

	protected function getConditions() :array {
		return [
			'logic' => static::LOGIC_AND,
			'group' => [
				[
					'rule'         => Plugin\Rules\Build\RequestBypassesAllRestrictions::SLUG,
					'invert_match' => true
				],
				[
					'condition' => Conditions\IsIpBlocked::SLUG,
				],
				[
					'condition'    => Conditions\IsIpHighReputation::SLUG,
					'invert_match' => true
				],
			]
		];
	}

	protected function getResponses() :array {
		return [
			[
				'response' => Responses\SetIpBlocked::SLUG,
			],
		];
	}
}