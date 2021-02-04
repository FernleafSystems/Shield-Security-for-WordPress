<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal\ResultItem;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Malware;

/**
 * Class FalsePositiveReporter
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal\Utilities
 */
class FalsePositiveReporter {

	const HASH_ALGO = 'sha1';
	use Modules\ModConsumer;

	/**
	 * @param ResultItem $oIt
	 * @param bool       $bIsFalsePositive
	 */
	public function reportResultItem( ResultItem $oIt, $bIsFalsePositive = true ) {
		$this->reportPath( $oIt->path_full, $bIsFalsePositive );
		$this->reportFileLines( $oIt->path_full, $oIt->file_lines, $bIsFalsePositive );
	}

	/**
	 * @param string $sFullPath
	 * @param int[]  $aLines
	 * @param bool   $bIsFalsePositive
	 */
	public function reportFileLines( $sFullPath, $aLines, $bIsFalsePositive = true ) {
		/** @var Modules\HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		if ( $oOpts->isMalUseNetworkIntelligence() ) {
			$aFile = array_intersect_key(
				explode( "\n", Services::WpFs()->getFileContent( $sFullPath ) ),
				array_flip( $aLines )
			);
			foreach ( $aFile as $sLine ) {
				$this->reportLine( $sFullPath, $sLine, $bIsFalsePositive );
			}
		}
	}

	/**
	 * To prevent duplicate reports, we cache what we report and only send the report
	 * if we've never sent this before.
	 * @param string $sFullPath
	 * @param bool   $bIsFalsePositive
	 * @return mixed
	 */
	public function reportPath( $sFullPath, $bIsFalsePositive = true ) {
		$bReported = false;

		/** @var Modules\HackGuard\Options $opts */
		$opts = $this->getOptions();
		if ( $opts->isMalUseNetworkIntelligence() ) {

			$reportHash = md5( serialize( [
				basename( $sFullPath ),
				sha1( Services::DataManipulation()->convertLineEndingsDosToLinux( $sFullPath ) ),
				$bIsFalsePositive
			] ) );

			if ( !$opts->isMalFalsePositiveReported( $reportHash ) ) {
				$sApiToken = $this->getCon()
								  ->getModule_License()
								  ->getWpHashesTokenManager()
								  ->getToken();
				$bReported = !empty( $sApiToken ) &&
							 ( new Malware\Whitelist\ReportFalsePositive( $sApiToken ) )
								 ->report( $sFullPath, static::HASH_ALGO, $bIsFalsePositive );
			}
			$this->updateReportedCache( $reportHash );
		}
		return $bReported;
	}

	/**
	 * Only reports lines if the files has more than 1 line. i.e. 1-liner false positive files are excluded.
	 * We still report 1-liner "true positive" files.
	 *
	 * @param string $sFile - path to file containing line
	 * @param string $sLine
	 * @param bool   $bIsFalsePositive
	 * @return mixed
	 */
	public function reportLine( $sFile, $sLine, $bIsFalsePositive = true ) {
		$bReported = false;

		/** @var Modules\HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		if ( $oOpts->isMalUseNetworkIntelligence() ) {

			$sReportHash = md5( $sFile.$sLine.( $bIsFalsePositive ? 'true' : 'false' ) );
			if ( !$oOpts->isMalFalsePositiveReported( $sReportHash ) ) {
				try {
					$sApiToken = $this->getCon()
									  ->getModule_License()
									  ->getWpHashesTokenManager()
									  ->getToken();
					if ( !empty( $sApiToken ) && !$bIsFalsePositive || count( file( $sFile ) ) > 1 ) {
						$bReported = ( new Malware\Signatures\ReportFalsePositive( $sApiToken ) )
							->report( $sFile, $sLine, $bIsFalsePositive );
					}
				}
				catch ( \Exception $e ) {
				}
			}
			$this->updateReportedCache( $sReportHash );
		}
		return $bReported;
	}

	/**
	 * @param string $sReportHash
	 */
	private function updateReportedCache( $sReportHash ) {
		/** @var Modules\HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();

		$aReported = $oOpts->getMalFalsePositiveReports();
		$aReported[ $sReportHash ] = Services::Request()->ts();
		$oOpts->setMalFalsePositiveReports( $aReported );

		$this->getMod()->saveModOptions(); // important to save immediately due to async nature
	}
}