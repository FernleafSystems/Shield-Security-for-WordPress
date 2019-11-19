<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Services\Utilities\File\ExtractLinesFromFile;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Malware;

/**
 * Class FalsePositiveQuery
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal\Utilities
 */
class FalsePositiveQuery {

	use Modules\ModConsumer;

	/**
	 * @param string $sFullPath
	 * @param int[]  $aLines
	 * @return int[] - key is the file line number, value is the false positive confidence score
	 */
	public function queryFileLines( $sFullPath, $aLines ) {
		$aScores = [];
		/** @var Modules\HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		if ( $oOpts->isMalUseNetworkIntelligence() ) {
			try {
				$aFile = ( new ExtractLinesFromFile() )->run( $sFullPath, $aLines );
				foreach ( $aFile as $nLineNum => $sLine ) {
					$aScores[ $nLineNum ] = $this->queryLine( $sFullPath, $sLine );
				}
			}
			catch ( \Exception $oE ) {
			}
		}
		return $aScores;
	}

	/**
	 * @param string $sFullPath
	 * @return int
	 */
	public function queryPath( $sFullPath ) {
		$nFpConfidence = 0;

		/** @var Modules\HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		if ( $oOpts->isMalUseNetworkIntelligence() ) {

			$aData = ( new Malware\Confidence\Retrieve() )->retrieveForFile( $sFullPath );
			if ( isset( $aData[ 'score' ] ) ) {
				$nFpConfidence = (int)$aData[ 'score' ];
			}
		}
		return $nFpConfidence;
	}

	/**
	 * Only reports lines if the files has more than 1 line. i.e. 1-liner false positive files are excluded.
	 * We still report 1-liner "true positive" files.
	 *
	 * @param string $sFile - path to file containing line
	 * @param string $sLine
	 * @return int
	 */
	public function queryLine( $sFile, $sLine ) {
		$nFpConfidence = 0;

		/** @var Modules\HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		if ( $oOpts->isMalUseNetworkIntelligence() ) {

			try {
				$aData = ( new Malware\Confidence\Retrieve() )->retrieveForFileLine( $sFile, $sLine );
				if ( isset( $aData[ 'score' ] ) ) {
					$nFpConfidence = (int)$aData[ 'score' ];
				}
			}
			catch ( \Exception $oE ) {
			}
		}
		return $nFpConfidence;
	}
}