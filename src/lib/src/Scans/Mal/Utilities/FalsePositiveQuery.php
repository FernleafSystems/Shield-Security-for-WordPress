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
	 * @param string $fullPath
	 * @param int[]  $aLines
	 * @return int[] - key is the file line number, value is the false positive confidence score
	 */
	public function queryFileLines( $fullPath, $aLines ) {
		$scores = [];
		/** @var Modules\HackGuard\Options $opts */
		$opts = $this->getOptions();
		if ( $opts->isMalUseNetworkIntelligence() ) {
			try {
				$aFile = ( new ExtractLinesFromFile() )->run( $fullPath, $aLines );
				foreach ( $aFile as $nLineNum => $sLine ) {
					$scores[ $nLineNum ] = $this->queryLine( $fullPath, $sLine );
				}
			}
			catch ( \Exception $e ) {
			}
		}
		return $scores;
	}

	public function queryPath( string $fullPath ) :int {
		$nFpConfidence = 0;

		/** @var Modules\HackGuard\Options $opts */
		$opts = $this->getOptions();
		if ( $opts->isMalUseNetworkIntelligence() ) {
			$apiToken = $this->getCon()
							 ->getModule_License()
							 ->getWpHashesTokenManager()
							 ->getToken();
			$data = ( new Malware\Confidence\Retrieve( $apiToken ) )->retrieveForFile( $fullPath );
			if ( isset( $data[ 'score' ] ) ) {
				$nFpConfidence = (int)$data[ 'score' ];
			}
		}
		return $nFpConfidence;
	}

	/**
	 * @param string $file - path to file containing line
	 * @param string $line
	 * @return int
	 */
	public function queryLine( $file, $line ) {
		$nFpConfidence = 0;

		/** @var Modules\HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		if ( $oOpts->isMalUseNetworkIntelligence() ) {
			$token = $this->getCon()
							  ->getModule_License()
							  ->getWpHashesTokenManager()
							  ->getToken();
			try {
				$aData = ( new Malware\Confidence\Retrieve( $token ) )->retrieveForFileLine( $file, $line );
				if ( isset( $aData[ 'score' ] ) ) {
					$nFpConfidence = (int)$aData[ 'score' ];
				}
			}
			catch ( \Exception $e ) {
			}
		}
		return $nFpConfidence;
	}
}