<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\AutoUnblock;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\IpRuleRecord;

class BaseAutoUnblockShield extends BaseAutoUnblock {

	protected function getIpRecord() :IpRuleRecord {
		$theRecord = ( new IpRuleStatus( $this->getCon()->this_req->ip ) )
			->setMod( $this->getMod() )
			->getRuleForAutoBlock();

		if ( empty( $theRecord ) ) {
			throw new \Exception( "IP isn't on the automatic block list." );
		}
		return $theRecord;
	}

	public function isUnblockAvailable() :bool {
		return $this->getCon()->this_req->is_ip_blocked_shield_auto && parent::isUnblockAvailable();
	}
}