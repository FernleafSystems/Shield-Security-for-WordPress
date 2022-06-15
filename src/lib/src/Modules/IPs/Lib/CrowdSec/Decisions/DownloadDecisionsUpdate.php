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
		$hoursInterval = $this->getCon()->isPremiumActive() ?
			apply_filters( 'shield/crowdsec_decisions_update_hours', 23 ) // ~1 day
			: 166; // 6.90 days
		return ( Services::Request()->ts() - $mod->getCrowdSecCon()->cfg->decisions_update_attempt_at )
			   > HOUR_IN_SECONDS*min( $hoursInterval, 2 );
	}

	protected function run() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$csCon = $mod->getCrowdSecCon();
		try {
			$csCon->cfg->decisions_update_attempt_at = Services::Request()->ts();
			( new ProcessDecisionList() )
				->setMod( $this->getMod() )
				->run(
					( new DecisionsDownload( $csCon->getApi()->getAuthorizationToken() ) )->run()
				);
			$csCon->cfg->decisions_updated_at = $csCon->cfg->decisions_update_attempt_at;
		}
		catch ( \Exception $e ) {
			error_log( $e->getMessage() );
		}
		$csCon->storeCfg();
	}
}