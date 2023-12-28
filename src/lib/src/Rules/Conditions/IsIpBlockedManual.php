<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;

class IsIpBlockedManual extends Base {

	use Traits\TypeShield;

	public const SLUG = 'is_ip_blocked_manual';

	public function getDescription() :string {
		return __( "Is the request IP on Shield's Manual block list.", 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		return ( new IpRuleStatus( $this->req->ip ) )->hasManualBlock();
	}

	protected function getPreviousResult() :?bool {
		return $this->req->is_ip_blocked_shield_manual;
	}

	protected function postExecConditionCheck( bool $result ) :void {
		$this->req->is_ip_blocked_shield_manual = $result;
	}
}