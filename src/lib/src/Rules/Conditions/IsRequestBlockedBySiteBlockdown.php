<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Constants;

class IsRequestBlockedBySiteBlockdown extends Base {

	use Traits\RequestPath;

	public const SLUG = 'is_request_blocked_by_site_blockdown';

	protected function getPreviousResult() :?bool {
		return self::con()->this_req->is_site_lockdown_blocked;
	}

	protected function postExecConditionCheck( bool $result ) :void {
		self::con()->this_req->is_site_lockdown_blocked = $result;
	}

	protected function getSubConditions() :array {
		return [
			'logic' => Constants::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => RequestIsPublicWebOrigin::class,
				],
				[
					'conditions' => IsForceOff::class,
					'logic'      => Constants::LOGIC_INVERT,
				],
				[
					'conditions' => IsSiteLockdownActive::class,
				],
				[
					'conditions' => IsIpWhitelisted::class,
					'logic'      => Constants::LOGIC_INVERT,
				],
			]
		];
	}
}