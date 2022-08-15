<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\AutoUnblock;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\IpRuleRecord;
use FernleafSystems\Wordpress\Services\Services;

class AutoUnblockShield extends BaseAutoUnblock {

	protected function canRun() :bool {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return parent::canRun()
			   && Services::Request()->isPost()
			   && $this->getCon()->this_req->is_ip_blocked
			   && $opts->isEnabledAutoVisitorRecover();
	}

	protected function getIpRecord() :IpRuleRecord {
		$theRecord = ( new IpRuleStatus( $this->getCon()->this_req->ip ) )
			->setMod( $this->getMod() )
			->getRuleForAutoBlock();

		if ( empty( $theRecord ) ) {
			throw new \Exception( "IP isn't on the automatic block list." );
		}
		return $theRecord;
	}
}