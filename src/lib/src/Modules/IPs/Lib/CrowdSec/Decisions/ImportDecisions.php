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
		return ( Services::Request()->ts() - $this->getImportInterval() )
			   > $mod->getCrowdSecCon()->cfg()->decisions_update_attempt_at;
	}

	protected function run() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$csCon = $mod->getCrowdSecCon();

		$cfg = $csCon->cfg();
		$cfg->decisions_update_attempt_at = Services::Request()->ts();
		$csCon->storeCfg( $cfg );

		$this->runImport();

		$cfg = $csCon->cfg();
		$cfg->decisions_updated_at = Services::Request()->ts();
		$csCon->storeCfg( $cfg );
	}

	public function runImport() {
		// We currently only import decisions that have a TTL of at least 5 days.
		$minimumExpiresAt = Services::Request()
									->carbon()
									->addDays( 3 )->timestamp;
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

	/**
	 * @throws DownloadDecisionsStreamFailedException
	 */
	private function testDownloadDecisionsViaFile() :array {
		$FS = Services::WpFs();
		$file = ABSPATH.'csDec.txt';
		if ( $FS->exists( $file ) ) {
			error_log( 'csDec from file' );
			$csDec = json_decode( $FS->getFileContent( $file ), true );
		}
		else {
			/** @var ModCon $mod */
			$mod = $this->getMod();
			$api = $mod->getCrowdSecCon()->getApi();
			$csDec = ( new DecisionsDownload( $api->getAuthorizationToken(), $api->getApiUserAgent() ) )->run();
			$FS->putFileContent( $file, json_encode( $csDec ) );
		}
		return $csDec;
	}

	private function getImportInterval() {
		return $this->getCon()->isPremiumActive() ?
			apply_filters( 'shield/crowdsec/decisions_update_interval', HOUR_IN_SECONDS*2 ) : WEEK_IN_SECONDS;
	}
}