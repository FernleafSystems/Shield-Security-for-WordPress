<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;

class IsIpWhitelisted extends Base {

	use Traits\TypeShield;

	public const SLUG = 'is_ip_whitelisted';

	public function getDescription() :string {
		return __( "Is the request IP on Shield's manual bypass-list/whitelist.", 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		return ( new IpRuleStatus( $this->req->ip ) )->isBypass();
	}

	protected function getPreviousResult() :?bool {
		return $this->req->is_ip_whitelisted;
	}

	protected function postExecConditionCheck( bool $result ) :void {
		$this->req->is_ip_whitelisted = $result;
	}
}