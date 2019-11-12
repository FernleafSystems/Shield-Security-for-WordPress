<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Malware;

/**
 * Class FalsePositiveReporter
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal\Utilities
 */
class FalsePositiveReporter {

	use Modules\ModConsumer;

	/**
	 * To prevent duplicate reports, we cache what we report and only send the report
	 * if we've never sent this before.
	 * @param string $sFullPath
	 * @param string $sAlgo
	 * @param bool   $bIsFalsePositive
	 * @return mixed
	 */
	public function reportPath( $sFullPath, $sAlgo = 'sha1', $bIsFalsePositive = true ) {
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
					->report( $sFullPath, $sAlgo, $bIsFalsePositive );
			}
			$this->updateReportedCache( $sReportHash );
		}
		return $bReported;
	}

	/**
	 * @param string $sLine
	 * @param bool   $bIsFalsePositive
	 * @param string $sContainingPath - path to file containing line
	 * @return mixed
	 */
	public function reportLine( $sLine, $bIsFalsePositive = true, $sContainingPath = '' ) {
		$bReported = false;

		/** @var Modules\HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		if ( $oOpts->isMalUseNetworkIntelligence() ) {

			$sReportHash = md5( serialize( [
				$sLine,
				$sContainingPath,
				$bIsFalsePositive
			] ) );
			if ( !$oOpts->isMalFalsePositiveReported( $sReportHash ) ) {
				$bReported = ( new Malware\Signatures\ReportFalsePositive() )
					->report( $sLine, $bIsFalsePositive );
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
