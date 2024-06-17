<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Decisions;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Api\DecisionsDownload;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Exceptions\DownloadDecisionsStreamFailedException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class ImportDecisions {

	use ExecOnce;
	use PluginControllerConsumer;

	protected function canRun() :bool {
		$csCon = self::con()->comps->crowdsec;
		return ( Services::Request()->ts() - $this->getImportInterval() > $csCon->cfg()->decisions_update_attempt_at )
			   && $csCon->getApi()->isReady();
	}

	protected function run() {
		$csCon = self::con()->comps->crowdsec;

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
					$processor->run( $decisionStream );
				}
				catch ( \Exception $e ) {
				}
			}
		}
		catch ( \Exception $e ) {
			error_log( 'Auth token: '.self::con()->comps->crowdsec->getApi()->getAuthorizationToken() );
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
		$api = self::con()->comps->crowdsec->getApi();
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
			$csDec = \json_decode( $FS->getFileContent( $file ), true );
		}
		else {
			$csDec = $this->downloadDecisions();
			$FS->putFileContent( $file, \wp_json_encode( $csDec ) );
		}
		return $csDec;
	}

	private function getImportInterval() {
		return self::con()->isPremiumActive() ?
			apply_filters( 'shield/crowdsec/decisions_update_interval', \HOUR_IN_SECONDS*2 ) : \WEEK_IN_SECONDS;
	}
}