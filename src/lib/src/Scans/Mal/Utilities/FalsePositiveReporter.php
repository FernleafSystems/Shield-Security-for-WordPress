<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal\ResultItem;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\ConvertLineEndings;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Malware;

class FalsePositiveReporter {

	const HASH_ALGO = 'sha1';
	use Modules\ModConsumer;

	public function reportResultItem( ResultItem $item, bool $isFalsePositive = true ) {
		$this->reportPath( $item->path_full, $isFalsePositive );
		$this->reportFileLines( $item->path_full, $item->file_lines, $isFalsePositive );
	}

	public function reportFileLines( string $fullPath, array $lines, bool $isFalsePositive = true ) {
		/** @var Modules\HackGuard\Options $opts */
		$opts = $this->getOptions();
		if ( $opts->isMalUseNetworkIntelligence() ) {
			$fileLines = array_intersect_key(
				explode( "\n", Services::WpFs()->getFileContent( $fullPath ) ),
				array_flip( $lines )
			);
			foreach ( $fileLines as $line ) {
				$this->reportLine( $fullPath, $line, $isFalsePositive );
			}
		}
	}

	/**
	 * To prevent duplicate reports, we cache what we report and only send the report
	 * if we've never sent this before.
	 */
	public function reportPath( string $fullPath, bool $isFalsePositive = true ) :bool {
		$reported = false;

		/** @var Modules\HackGuard\Options $opts */
		$opts = $this->getOptions();
		if ( $opts->isMalUseNetworkIntelligence() ) {

			$reportHash = md5( serialize( [
				basename( $fullPath ),
				sha1( ( new ConvertLineEndings() )->fileDosToLinux( $fullPath ) ),
				$isFalsePositive
			] ) );

			if ( !$opts->isMalFalsePositiveReported( $reportHash ) ) {
				$apiToken = $this->getCon()
								 ->getModule_License()
								 ->getWpHashesTokenManager()
								 ->getToken();
				$reported = !empty( $apiToken ) &&
							( new Malware\Whitelist\ReportFalsePositive( $apiToken ) )
								->report( $fullPath, static::HASH_ALGO, $isFalsePositive );
			}
			$this->updateReportedCache( $reportHash );
		}
		return $reported;
	}

	/**
	 * Only reports lines if the files has more than 1 line. i.e. 1-liner false positive files are excluded.
	 * We still report 1-liner "true positive" files.
	 */
	public function reportLine( string $fullPath, string $line, bool $isFalsePositive = true ) :bool {
		$isReported = false;

		/** @var Modules\HackGuard\Options $opts */
		$opts = $this->getOptions();
		if ( $opts->isMalUseNetworkIntelligence() ) {

			$reportHash = md5( $fullPath.$line.( $isFalsePositive ? 'true' : 'false' ) );
			if ( !$opts->isMalFalsePositiveReported( $reportHash ) ) {
				try {
					$token = $this->getCon()
								  ->getModule_License()
								  ->getWpHashesTokenManager()
								  ->getToken();
					if ( !empty( $token ) && !$isFalsePositive || count( file( $fullPath ) ) > 1 ) {
						$isReported = ( new Malware\Signatures\ReportFalsePositive( $token ) )
							->report( $fullPath, $line, $isFalsePositive );
					}
				}
				catch ( \Exception $e ) {
				}
			}
			$this->updateReportedCache( $reportHash );
		}
		return $isReported;
	}

	private function updateReportedCache( string $reportHash ) {
		/** @var Modules\HackGuard\Options $opts */
		$opts = $this->getOptions();

		$allReported = $opts->getMalFalsePositiveReports();
		$allReported[ $reportHash ] = Services::Request()->ts();

		// important to save immediately due to async nature
		$opts->setMalFalsePositiveReports( $allReported );
		$this->getMod()->saveModOptions();
	}
}