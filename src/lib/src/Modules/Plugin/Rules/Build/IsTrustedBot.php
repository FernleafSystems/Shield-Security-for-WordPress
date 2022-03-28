<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\BuildRuleCoreShieldBase,
	Conditions,
	Responses
};
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

class IsTrustedBot extends BuildRuleCoreShieldBase {

	const SLUG = 'shield/is_trusted_bot';

	protected function getName() :string {
		return 'Is Trusted Bot';
	}

	protected function getDescription() :string {
		return 'Test whether the visitor is a trusted bot.';
	}

	protected function getSlug() :string {
		return static::SLUG;
	}

	protected function getPriority() :int {
		return 12;
	}

	protected function getConditions() :array {
		return [
			'logic' => static::LOGIC_AND,
			'group' => [
				[
					'action'       => IsServerLoopback::SLUG,
					'invert_match' => true,
				],
				[
					'action' => Conditions\MatchRequestIpIdentity::SLUG,
					'params' => [
						'match_not_ip_ids' => (array)apply_filters( 'shield/untrusted_service_providers', [
							IpID::UNKNOWN,
							IpID::THIS_SERVER,
							IpID::VISITOR,
						] ),
					],
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