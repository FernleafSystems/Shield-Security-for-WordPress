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

		/** @var Modules\HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		if ( $oOpts->isMalUseNetworkIntelligence() ) {

			$sReportHash = md5( serialize( [
				basename( $sFullPath ),
				sha1( Services::DataManipulation()->convertLineEndingsDosToLinux( $sFullPath ) ),
				$bIsFalsePositive
			] ) );
			if ( !$oOpts->isMalFalsePositiveReported( $sReportHash ) ) {
				$bReported = ( new Malware\Whitelist\ReportFalsePositive() )
					->report( $sFullPath, static::HASH_ALGO, $bIsFalsePositive );
			}
			$this->updateReportedCache( $sReportHash );
		}
		return $bReported;
	}

	/**
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

			$sReportHash = md5( serialize( [
				$sLine,
				$sFile,
				$bIsFalsePositive
			] ) );
			if ( true || !$oOpts->isMalFalsePositiveReported( $sReportHash ) ) {
				$bReported = ( new Malware\Signatures\ReportFalsePositive() )
					->report( $sFile, $sLine, $bIsFalsePositive );
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