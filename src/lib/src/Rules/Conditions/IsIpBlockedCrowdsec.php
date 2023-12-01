<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Constants;

class IsIpBlockedCrowdsec extends Base {

	use Traits\RequestIP;

	public const SLUG = 'is_ip_blocked_crowdsec';

	protected function execConditionCheck() :bool {
		return ( new IpRuleStatus( $this->getRequestIP() ) )->hasCrowdsecBlock();
	}

	protected function getPreviousResult() :?bool {
		return self::con()->this_req->is_ip_blocked_crowdsec;
	}

	protected function postExecConditionCheck( bool $result ) :void {
		self::con()->this_req->is_ip_blocked_crowdsec = $result;
	}

	protected function getSubConditions() :array {
		return [
			'logic'      => Constants::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => RequestBypassesAllRestrictions::class,
					'logic'      => Constants::LOGIC_INVERT,
				],
				[
					'conditions' => IsIpBlockedByShield::class,
					'logic'      => Constants::LOGIC_INVERT
				],
				[
					'conditions' => IsIpHighReputation::class,
					'logic'      => Constants::LOGIC_INVERT
				],
				[
					'conditions' => RequestIsServerLoopback::class,
					'logic'      => Constants::LOGIC_INVERT
				],
				[
					'conditions' => $this->getDefaultConditionCheckCallable(),
				],
			]
		];
	}
}