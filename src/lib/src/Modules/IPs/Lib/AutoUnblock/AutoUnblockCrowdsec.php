<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\AutoUnblock;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\IpRuleRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Options;
use FernleafSystems\Wordpress\Services\Services;

class AutoUnblockCrowdsec extends BaseAutoUnblock {

	public function canRunAutoUnblockProcess() :bool {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return parent::canRunAutoUnblockProcess()
			   && Services::Request()->isPost()
			   && $this->getCon()->this_req->is_ip_blocked_crowdsec
			   && $opts->isEnabledCrowdSecAutoVisitorUnblock();
	}

	protected function getUnblockMethodName() :string {
		return 'CrowdSec Auto-Unblock';
	}

	protected function getIpRecord() :IpRuleRecord {
		$theRecord = null;
		$status = ( new IpRuleStatus( $this->getCon()->this_req->ip ) )->setMod( $this->getMod() );
		foreach ( $status->getRulesForCrowdsec() as $record ) {
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