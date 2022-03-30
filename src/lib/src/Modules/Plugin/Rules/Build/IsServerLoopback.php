<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\BuildRuleCoreShieldBase,
	Conditions
};
use FernleafSystems\Wordpress\Services\Services;

class IsServerLoopback extends BuildRuleCoreShieldBase {

	const SLUG = 'shield/is_server_loopback';

	protected function getName() :string {
		return 'Is Server Loopback';
	}

	protected function getDescription() :string {
		return 'Is Server Loopback request.';
	}

	protected function getPriority() :int {
		return 10;
	}

	protected function getConditions() :array {
		return [
			'logic' => static::LOGIC_AND,
			'group' => [
				[
					'condition' => Conditions\MatchRequestIp::SLUG,
					'params' => [
						'match_ips' => Services::IP()->getServerPublicIPs(),
					],
				],
			]
		];
	}
}