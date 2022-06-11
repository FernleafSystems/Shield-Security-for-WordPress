<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Decisions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Api\DecisionsDownload;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Services\Services;

class DownloadDecisionsUpdate extends ExecOnceModConsumer {

	protected function canRun() :bool {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$cfg = $mod->getCrowdSecCon()->cfg;
		return Services::Request()->ts() - $cfg->last_decision_update_at >
			   ( $this->getCon()->isPremiumActive() ? DAY_IN_SECONDS : WEEK_IN_SECONDS );
	}

	protected function run() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		try {
			( new ProcessDecisionList() )
				->setMod( $this->getMod() )
				->run(
					( new DecisionsDownload( $mod->getCrowdSecCon()->getApi()->getAuthorizationToken() ) )->run()
				);
		}
		catch ( \Exception $e ) {
		}
	}
}