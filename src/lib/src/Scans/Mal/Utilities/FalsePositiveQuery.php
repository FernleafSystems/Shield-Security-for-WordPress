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
			$sApiToken = $this->getCon()
							  ->getModule_License()
							  ->getWpHashesTokenManager()
							  ->getToken();
			$aData = ( new Malware\Confidence\Retrieve( $sApiToken ) )->retrieveForFile( $sFullPath );
			if ( isset( $aData[ 'score' ] ) ) {
				$nFpConfidence = (int)$aData[ 'score' ];
			}
		}
		return $nFpConfidence;
	}

	/**
	 * @param string $sFile - path to file containing line
	 * @param string $sLine
	 * @return int
	 */
	public function queryLine( $sFile, $sLine ) {
		$nFpConfidence = 0;

		/** @var Modules\HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		if ( $oOpts->isMalUseNetworkIntelligence() ) {
			$sApiToken = $this->getCon()
							  ->getModule_License()
							  ->getWpHashesTokenManager()
							  ->getToken();
			try {
				$aData = ( new Malware\Confidence\Retrieve( $sApiToken ) )->retrieveForFileLine( $sFile, $sLine );
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