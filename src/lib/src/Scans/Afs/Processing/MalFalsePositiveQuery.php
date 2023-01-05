<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Processing;

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Services\Utilities\File\ExtractLinesFromFile;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Malware;

class MalFalsePositiveQuery {

	use Modules\ModConsumer;

	/**
	 * @param int[] $lines
	 * @return int[] - key is the file line number, value is the false positive confidence score
	 */
	public function queryFileLines( string $fullPath, array $lines ) :array {
		$scores = [];
		/** @var Modules\HackGuard\Options $opts */
		$opts = $this->getOptions();
		if ( $opts->isMalUseNetworkIntelligence() ) {
			try {
				foreach ( ( new ExtractLinesFromFile() )->run( $fullPath, $lines ) as $lineNumber => $line ) {
					$scores[ $lineNumber ] = $this->queryLine( $fullPath, $line );
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
	public function queryLine( $file, $line ) :int {
		$falsePositiveConfidence = 0;

		/** @var Modules\HackGuard\Options $opts */
		$opts = $this->getOptions();
		if ( $opts->isMalUseNetworkIntelligence() ) {
			$token = $this->getCon()
						  ->getModule_License()
						  ->getWpHashesTokenManager()
						  ->getToken();
			try {
				$response = ( new Malware\Confidence\Retrieve( $token ) )->retrieveForFileLine( $file, $line );
				if ( isset( $response[ 'score' ] ) ) {
					$falsePositiveConfidence = (int)$response[ 'score' ];
				}
			}
			catch ( \Exception $e ) {
			}
		}
		return $falsePositiveConfidence;
	}
}