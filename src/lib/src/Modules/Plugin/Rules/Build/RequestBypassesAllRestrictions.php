<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\BuildRuleCoreShieldBase,
	Build\RuleTraits,
	Conditions,
	Constants,
	Responses
};

/**
 * @deprecated 18.5.8
 */
class RequestBypassesAllRestrictions extends BuildRuleCoreShieldBase {

	use RuleTraits\InstantExec;

	public const SLUG = 'shield/request_bypasses_all_restrictions';

	protected function getName() :string {
		return 'A Request That Bypasses Restrictions';
	}

	protected function getDescription() :string {
		return 'Does the request bypass all plugin restrictions.';
	}

	protected function getConditions2() :array {
		return [
			'logic'      => Constants::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => Conditions\RequestIsSiteBlockdownBlocked::class,
					'logic'      => Constants::LOGIC_INVERT,
				],
				[
					'logic'      => Constants::LOGIC_OR,
					'conditions' => [
						[
							'conditions' => Conditions\IsForceOff::class,
						],
						[
							'conditions' => Conditions\RequestIsPublicWebOrigin::class,
							'logic'      => Constants::LOGIC_INVERT,
						],
						[
							'conditions' => Conditions\RequestIsTrustedBot::class,
						],
						[
							'conditions' => Conditions\RequestIsPathWhitelisted::class,
						],
					]
				],
			]
		];
	}

	protected function getConditions() :array {
		return [
			'logic' => Constants::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => Conditions\RequestIsSiteBlockdownBlocked::class,
					'logic'      => Constants::LOGIC_INVERT,
				],
				[
					'logic' => Constants::LOGIC_OR,
					'conditions' => [
						[
							'conditions' => Conditions\IsForceOff::class,
						],
						[
							'conditions' => Conditions\RequestIsPublicWebOrigin::class,
							'logic'      => Constants::LOGIC_INVERT,
						],
						[
							'conditions' => Conditions\RequestIsTrustedBot::class,
						],
						[
							'conditions' => Conditions\RequestIsPathWhitelisted::class,
						],
						[
							'conditions' => Conditions\RequestIsPathWhitelisted::class,
						],
					]
				],
			]
		];
	}
}