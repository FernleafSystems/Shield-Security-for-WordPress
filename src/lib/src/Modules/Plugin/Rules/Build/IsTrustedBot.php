<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\BuildRuleCoreShieldBase,
	Build\RuleTraits,
	Conditions,
	Constants,
	Responses
};
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

/**
 * @deprecated 18.5.8
 */
class IsTrustedBot extends BuildRuleCoreShieldBase {

	use RuleTraits\InstantExec;

	public const SLUG = 'shield/is_trusted_bot';

	protected function getName() :string {
		return 'Is Trusted Bot';
	}

	protected function getDescription() :string {
		return 'Test whether the visitor is a trusted bot.';
	}

	protected function getConditions() :array {
		return [
			'logic' => static::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => Conditions\RequestIsServerLoopback::class,
					'logic'      => Constants::LOGIC_INVERT,
				],
				[
					'conditions' => Conditions\MatchRequestIpIdentity::class,
					'params'    => [
						'match_ip_ids' => (array)apply_filters( 'shield/untrusted_service_providers', [
							IpID::UNKNOWN,
							IpID::THIS_SERVER,
							IpID::VISITOR,
						] ),
					],
				],
			]
		];
	}
}