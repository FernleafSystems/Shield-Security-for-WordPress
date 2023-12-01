<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Constants;

class RequestBypassesAllRestrictions extends Base {

	public const SLUG = 'request_bypasses_all_restrictions';

	protected function getPreviousResult() :?bool {
		return self::con()->this_req->request_bypasses_all_restrictions;
	}

	protected function postExecConditionCheck( bool $result ) :void {
		self::con()->this_req->request_bypasses_all_restrictions = $result;
	}

	protected function getSubConditions() :array {
		return [
			'logic' => Constants::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => RequestIsSiteBlockdownBlocked::class,
					'logic'      => Constants::LOGIC_INVERT,
				],
				[
					'logic' => Constants::LOGIC_OR,
					'conditions' => [
						[
							'conditions' => IsForceOff::class,
						],
						[
							'logic'      => Constants::LOGIC_INVERT,
							'conditions' => RequestIsPublicWebOrigin::class,
						],
						[
							'conditions' => RequestIsServerLoopback::class,
						],
						[
							'conditions' => RequestIsTrustedBot::class,
						],
						[
							'conditions' => RequestIsPathWhitelisted::class,
						],
					]
				],
			]
		];
	}
}