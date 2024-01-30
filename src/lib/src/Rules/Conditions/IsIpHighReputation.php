<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IsHighReputationIP;

class IsIpHighReputation extends Base {

	use Traits\TypeShield;

	public const SLUG = 'is_ip_high_reputation';

	public function getDescription() :string {
		return __( 'Is the current IP address considered "high reputation".', 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		return ( new IsHighReputationIP() )
			->setIP( $this->req->ip )
			->query();
	}

	protected function getPreviousResult() :?bool {
		return $this->req->is_ip_high_reputation;
	}

	protected function postExecConditionCheck( bool $result ) :void {
		$this->req->is_ip_high_reputation = $result;
	}
}