<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Decisions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Api\DecisionsDownload;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Exceptions\DownloadDecisionsStreamFailedException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Services\Services;

class ImportDecisions extends ExecOnceModConsumer {

	protected function canRun() :bool {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$interval = $this->getCon()->isPremiumActive() ?
			apply_filters( 'shield/crowdsec/decisions_update_interval', HOUR_IN_SECONDS*2 ) : DAY_IN_SECONDS*2;
		return ( Services::Request()->ts() - $mod->getCrowdSecCon()->cfg->decisions_update_attempt_at ) > $interval;
	}

	protected function run() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$csCon = $mod->getCrowdSecCon();

		$csCon->cfg->decisions_update_attempt_at = Services::Request()->ts();
		$csCon->storeCfg();

		$this->runImport();

		$csCon->cfg->decisions_updated_at = $csCon->cfg->decisions_update_attempt_at;
		$csCon->storeCfg();
	}

	public function runImport() {
		// We currently only import decisions that have a TTL of at least 5 days.
		$minimumExpiresAt = Services::Request()
									->carbon()
									->addDays( 5 )->timestamp;
		try {
			$decisionStream = $this->downloadDecisions();
			foreach ( $this->enumSupportedScopeProcessors() as $supportedScopeProcessor ) {
				try {
					$processor = new $supportedScopeProcessor();
					$processor->minimum_expires_at = $minimumExpiresAt;
					$processor->setMod( $this->getMod() )->run( $decisionStream );
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
		$api = $mod->getCrowdSecCon()->getApi();
		return ( new DecisionsDownload( $api->getAuthorizationToken(), $api->getApiUserAgent() ) )->run();
	}
}