<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\AutoUnblock;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\IpRuleRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Services\Services;

class AutoUnblockCrowdsec extends BaseAutoUnblock {

	public function canRunAutoUnblockProcess() :bool {
		return parent::canRunAutoUnblockProcess()
			   && Services::Request()->isPost()
			   && self::con()->this_req->is_ip_blocked_crowdsec
			   && self::con()->comps->opts_lookup->enabledCrowdSecAutoUnblock();
	}

	protected function getUnblockMethodName() :string {
		return 'CrowdSec Auto-Unblock';
	}

	protected function getIpRecord() :IpRuleRecord {
		$theRecord = null;
		foreach ( ( new IpRuleStatus( self::con()->this_req->ip ) )->getRulesForCrowdsec() as $record ) {
			if ( !$record->is_range ) {
				$theRecord = $record;
			}
		}

		if ( empty( $theRecord ) ) {
			throw new \Exception( "IP isn't on the CrowdSec block list." );
		}
		return $theRecord;
	}
}