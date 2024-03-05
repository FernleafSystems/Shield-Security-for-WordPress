<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\AutoUnblock;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\IpRuleRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;

class BaseAutoUnblockShield extends BaseAutoUnblock {

	protected function getIpRecord() :IpRuleRecord {
		$theRecord = ( new IpRuleStatus( self::con()->this_req->ip ) )->getRuleForAutoBlock();
		if ( empty( $theRecord ) ) {
			throw new \Exception( "IP isn't on the automatic block list." );
		}
		return $theRecord;
	}

	public function isUnblockAvailable() :bool {
		return self::con()->this_req->is_ip_blocked_shield_auto && parent::isUnblockAvailable();
	}
}