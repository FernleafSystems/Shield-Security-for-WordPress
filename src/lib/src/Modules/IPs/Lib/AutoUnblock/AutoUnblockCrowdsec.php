<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\AutoUnblock;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops\LookupIP;
use FernleafSystems\Wordpress\Services\Services;

class AutoUnblockCrowdsec extends BaseAutoUnblock {

	protected function canRun() :bool {
		/** @var IPs\Options $opts */
		$opts = $this->getOptions();
		return parent::canRun()
			   && $this->getCon()->this_req->is_ip_crowdsec_blocked && $opts->isEnabledCrowdSecAutoVisitorUnblock();
	}

	/**
	 * @throws \Exception
	 */
	protected function processAutoUnblockRequest() :bool {
		/** @var IPs\ModCon $mod */
		$mod = $this->getMod();
		$req = Services::Request();

		$unblocked = false;

		if ( $this->canRunUnblock() ) {
			$record = ( new LookupIP() )
				->setMod( $mod )
				->setIP( $this->getCon()->this_req->ip )
				->setListTypeCrowdsec()
				->lookup();
			if ( !empty( $record ) ) {
				$unblocked = $mod->getDbH_IPRules()
								 ->getQueryUpdater()
								 ->updateById( $record->id, [
									 'unblocked_at' => $req->ts()
								 ] );
			}
		}

		return $unblocked;
	}

	protected function getNonceAction() :string {
		return 'uau-cs-'.Services::Request()->ip();
	}
}