<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Decisions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Api\DecisionsDownload;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Exceptions\DownloadDecisionsStreamFailedException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Services\Services;

class ImportDecisions extends ExecOnceModConsumer {

	protected function canRun() :bool {
		return true;
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$hoursInterval = $this->getCon()->isPremiumActive() ?
			apply_filters( 'shield/crowdsec/decisions_update_interval', 23 ) // ~1 day
			: 166; // 6.90 days
		return ( Services::Request()->ts() - $mod->getCrowdSecCon()->cfg->decisions_update_attempt_at )
			   > HOUR_IN_SECONDS*min( $hoursInterval, 2 );
	}

	protected function run() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$csCon = $mod->getCrowdSecCon();

		$csCon->cfg->decisions_update_attempt_at = Services::Request()->ts();
		$this->runImport();
		$csCon->cfg->decisions_updated_at = $csCon->cfg->decisions_update_attempt_at;

		$csCon->storeCfg();
	}

	public function runImport() {
		try {
			$decisionStream = $this->downloadDecisions();
			foreach ( $this->enumSupportedScopeProcessors() as $supportedScopeProcessor ) {
				try {
					$processor = new $supportedScopeProcessor( $decisionStream );
					$processor->setMod( $this->getMod() )->run();
				}
				catch ( \Exception $e ) {
				}
			}
		}
		catch ( \Exception $e ) {
			error_log( $e->getMessage() );
		}
	}

	/**
	 * @return Scopes\ProcessBase[]
	 */
	private function enumSupportedScopeProcessors() :array {
		return [
			Scopes\ProcessIPs::class,
		];
	}

	/**
	 * @throws DownloadDecisionsStreamFailedException
	 */
	private function downloadDecisions() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$csCon = $mod->getCrowdSecCon();
		return ( new DecisionsDownload( $csCon->getApi()->getAuthorizationToken() ) )->run();
	}
}