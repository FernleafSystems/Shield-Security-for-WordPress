<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\BuildRuleCoreShieldBase,
	Conditions,
	Responses
};

class IpBlocked extends BuildRuleCoreShieldBase {

	protected function getName() :string {
		return 'Is IP Blocked';
	}

	protected function getDescription() :string {
		return 'Test whether the current Request IP is Blocked.';
	}

	protected function getSlug() :string {
		return 'shield/is_ip_blocked';
	}

	protected function getPriority() :int {
		return 20;
	}

	protected function getConditions() :array {
		return [
			'logic' => static::LOGIC_AND,
			'group' => [
				[
					'action'       => Conditions\IsIpWhitelisted::SLUG,
					'invert_match' => true
				],
				[
					'action' => Conditions\IsIpBlocked::SLUG,
				],
				[
					'action'       => Conditions\IsIpHighReputation::SLUG,
					'invert_match' => true
				],
			]
		];
	}

	protected function getResponses() :array {
		return [
			[
				'action' => Responses\IsIpBlocked::SLUG,
			],
		];
	}
}