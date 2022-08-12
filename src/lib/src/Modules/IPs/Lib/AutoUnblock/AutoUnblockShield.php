<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\AutoUnblock;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\{
	IpRuleRecord,
	Ops\Handler
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops\LookupIP;
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
		$record = ( new LookupIP() )
			->setMod( $this->getMod() )
			->setIP( $this->getCon()->this_req->ip )
			->setListTypeBlock()
			->lookup();
		if ( !empty( $record ) && $record->type !== Handler::T_AUTO_BLACK ) {
			$record = null;
		}
		if ( empty( $record ) ) {
			throw new \Exception( "IP isn't on the appropriate block list." );
		}
		return $record;
	}
}