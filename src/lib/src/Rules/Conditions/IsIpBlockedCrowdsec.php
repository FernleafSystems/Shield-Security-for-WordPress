<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumLogic;

class IsIpBlockedCrowdsec extends Base {

	use Traits\RequestIP;
	use Traits\TypeShield;

	public const SLUG = 'is_ip_blocked_crowdsec';

	public function getDescription() :string {
		return __( "Is the request IP on Shield's CrowdSec block list.", 'wp-simple-firewall' );
	}

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
			'logic'      => EnumLogic::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => RequestBypassesAllRestrictions::class,
					'logic'      => EnumLogic::LOGIC_INVERT,
				],
				[
					'conditions' => RequestIsServerLoopback::class,
					'logic'      => EnumLogic::LOGIC_INVERT
				],
				[
					'conditions' => IsIpBlockedByShield::class,
					'logic'      => EnumLogic::LOGIC_INVERT
				],
				[
					'conditions' => IsIpHighReputation::class,
					'logic'      => EnumLogic::LOGIC_INVERT
				],
				[
					'conditions' => $this->getDefaultConditionCheckCallable(),
				],
			]
		];
	}
}