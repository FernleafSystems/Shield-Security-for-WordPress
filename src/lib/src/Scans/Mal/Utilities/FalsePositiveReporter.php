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
				$lines
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

		$reportHash = md5( serialize( [
			basename( $fullPath ),
			sha1( ( new ConvertLineEndings() )->fileDosToLinux( $fullPath ) ),
			$isFalsePositive
		] ) );

		if ( $this->canSendReport( $reportHash ) ) {
			$apiToken = $this->getCon()
							 ->getModule_License()
							 ->getWpHashesTokenManager()
							 ->getToken();
			$reported = !empty( $apiToken ) &&
						( new Malware\Whitelist\ReportFalsePositive( $apiToken ) )
							->report( $fullPath, static::HASH_ALGO, $isFalsePositive );

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

		$reportHash = md5( $fullPath.$line.( $isFalsePositive ? 'true' : 'false' ) );
		if ( $this->canSendReport( $reportHash ) ) {
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
			$this->updateReportedCache( $reportHash );
		}

		return $isReported;
	}

	private function canSendReport( string $reportHash ) :bool {
		/** @var Modules\HackGuard\Options $opts */
		$opts = $this->getOptions();
		return $this->getCon()->is_mode_live
			   && $opts->isMalUseNetworkIntelligence()
			   && !isset( $this->getMalFalsePositiveReports()[ $reportHash ] );
	}

	private function updateReportedCache( string $reportHash ) {
		/** @var Modules\HackGuard\Options $opts */
		$opts = $this->getOptions();

		$allReported = $this->getMalFalsePositiveReports();
		$allReported[ $reportHash ] = Services::Request()->ts();

		$opts->setOpt( 'mal_fp_reports', array_filter(
			$allReported,
			function ( $ts ) {
				return $ts > Services::Request()->carbon()->subMonth()->timestamp;
			}
		) );
		// important to save immediately due to async nature
		$this->getMod()->saveModOptions();
	}

	/**
	 * @return int[] - keys are the unique report hash
	 */
	private function getMalFalsePositiveReports() :array {
		$FP = $this->getOptions()->getOpt( 'mal_fp_reports', [] );
		return is_array( $FP ) ? $FP : [];
	}
}