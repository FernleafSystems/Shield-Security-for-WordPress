<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\AutoUnblock;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
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
			$csRecords = ( new IPs\DB\CrowdSecDecisions\LoadCrowdsecDecisions() )
				->setMod( $mod )
				->setIP( $req->ip() )
				->select();
			if ( !empty( $csRecords ) ) {
				$mod->getDbH_CrowdSecDecisions()
					->getQueryUpdater()
					->updateById( $csRecords[ 0 ]->id, [
						'auto_unblock_at' => $req->ts()
					] );
				$unblocked = true;
			}
		}

		return $unblocked;
	}

	protected function getNonceAction() :string {
		return 'uau-cs-'.Services::Request()->ip();
	}
}