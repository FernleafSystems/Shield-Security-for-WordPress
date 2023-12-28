<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;

class IsIpBlockedAuto extends Base {

	use Traits\TypeShield;

	public const SLUG = 'is_ip_blocked_auto';

	public function getDescription() :string {
		return __( "Is the request IP on Shield's auto-block list .", 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		/** Note: Don't be tempted to set the flag on $this_req for auto block as we must first consider High Reputation */
		return apply_filters( 'shield/is_ip_blocked_auto', ( new IpRuleStatus( $this->req->ip ) )->hasAutoBlock() );
	}

	protected function getPreviousResult() :?bool {
		return $this->req->is_ip_blocked_shield_auto;
	}

	protected function postExecConditionCheck( bool $result ) :void {
		$this->req->is_ip_blocked_shield_auto = $result;
	}
}