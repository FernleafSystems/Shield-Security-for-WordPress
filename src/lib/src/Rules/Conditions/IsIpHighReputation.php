<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;

class IsIpHighReputation extends Base {

	use Traits\RequestIP;
	use Traits\TypeShield;

	public const SLUG = 'is_ip_high_reputation';

	public function getDescription() :string {
		return __( 'Is the current IP address considered "high reputation".', 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		return ( new IpRuleStatus( $this->getRequestIP() ) )->hasHighReputation();
	}

	protected function getPreviousResult() :?bool {
		return self::con()->this_req->is_ip_high_reputation;
	}

	protected function postExecConditionCheck( bool $result ) :void {
		self::con()->this_req->is_ip_high_reputation = $result;
	}
}