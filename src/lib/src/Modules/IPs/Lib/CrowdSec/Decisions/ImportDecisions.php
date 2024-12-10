<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Decisions;

use AptowebDeps\CrowdSec\CapiClient\ClientException;
use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Dependencies\Exceptions\LibraryPrefixedAutoloadNotFoundException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class ImportDecisions {

	use ExecOnce;
	use PluginControllerConsumer;

	protected function canRun() :bool {
		return ( Services::Request()->carbon()->subSeconds( $this->getImportInterval() )->timestamp
				 > self::con()->comps->crowdsec->cfg()->decisions_update_attempt_at );
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
		catch ( ClientException $ce ) {
			// TODO: if 403 reset auth.
			error_log( sprintf( 'client exception: "%s" : "%s"', $ce->getCode(), $ce->getMessage() ) );
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
	 * @throws LibraryPrefixedAutoloadNotFoundException
	 * @throws ClientException
	 */
	private function downloadDecisions() :array {
		return self::con()->comps->crowdsec->getCApiWatcher()->getStreamDecisions();
	}

	/**
	 * @throws LibraryPrefixedAutoloadNotFoundException
	 */
	public function testDownloadDecisionsViaFile() :array {
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

	private function getImportInterval() :int {
		$pro = self::con()->isPremiumActive();
		return (int)\max( \HOUR_IN_SECONDS, $pro ? apply_filters( 'shield/crowdsec/decisions_update_interval', \HOUR_IN_SECONDS*2 ) : \WEEK_IN_SECONDS );
	}
}