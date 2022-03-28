<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\BuildRuleCoreShieldBase,
	Conditions
};
use FernleafSystems\Wordpress\Services\Services;

class IsPublicWebRequest extends BuildRuleCoreShieldBase {

	protected function getName() :string {
		return 'Is Public Web Request';
	}

	protected function getDescription() :string {
		return 'Is a public web request.';
	}

	protected function getSlug() :string {
		return 'shield/is_public_web_request';
	}

	protected function getPriority() :int {
		return 10;
	}

	protected function getConditions() :array {
		return [
			'logic' => static::LOGIC_AND,
			'group' => [
				[
					'action' => Conditions\MatchRequestIp::SLUG,
					'params' => [
						'match_ips' => Services::IP()->getServerPublicIPs(),
					],
				],
			]
		];
	}
}