<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\AutoUnblock;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\IpRuleRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Services\Services;

class AutoUnblockCrowdsec extends BaseAutoUnblock {

	protected function canRun() :bool {
		/** @var IPs\Options $opts */
		$opts = $this->getOptions();
		return parent::canRun()
			   && Services::Request()->isPost()
			   && $this->getCon()->this_req->is_ip_crowdsec_blocked && $opts->isEnabledCrowdSecAutoVisitorUnblock();
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

	protected function getNonceAction() :string {
		return 'uau-cs-'.$this->getCon()->this_req->ip;
	}
}