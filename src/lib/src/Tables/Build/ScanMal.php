<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

/**
 * Class ScanMal
 * @package FernleafSystems\Wordpress\Plugin\Shield\Tables\Build
 */
class ScanMal extends ScanBase {

	/**
	 * @return array[]
	 */
	protected function getEntriesFormatted() {
		$aEntries = [];

		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		/** @var HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();

		$oRepairer = ( new Shield\Scans\Mal\Repair() )->setMod( $oMod );
		$oConverter = ( new HackGuard\Scan\Results\ConvertBetweenTypes() )
			->setScanActionVO( $this->getScanActionVO() );

		foreach ( $this->getEntriesRaw() as $nKey => $oEntry ) {
			/** @var Shield\Databases\Scanner\EntryVO $oEntry */
			/** @var Shield\Scans\Mal\ResultItem $oIt */
			$oIt = $oConverter->convertVoToResultItem( $oEntry );
			$aE = $oEntry->getRawDataAsArray();

			$aE[ 'path' ] = $oIt->path_fragment;
			$aE[ 'ignored' ] = $this->formatIsIgnored( $oEntry );

			$aStatus = [
				__( 'Potential Malware Detected', 'wp-simple-firewall' ),
				sprintf( '%s: %s', __( 'Pattern Detected' ), $this->getPatternForDisplay( base64_decode( $oIt->mal_sig ) ) ),
				sprintf( '%s: %s', __( 'Affected line numbers' ),
					implode( ', ', array_map(
						function ( $nLineNumber ) {
							return $nLineNumber + 1;
						},
						$oIt->file_lines // because lines start at ZERO
					) )
				),
			];

			if ( $oOpts->isMalUseNetworkIntelligence() ) {
				$aStatus[] = sprintf( '%s: %s/100', __( 'False Positive Confidence' ), sprintf( '<strong>%s</strong>', (int)$oIt->fp_confidence ) );
			}

			try {
				$bCanRepair = $oRepairer->canAutoRepairFromSource( $oIt );
			}
			catch ( \Exception $oE ) {
				$aStatus[] = sprintf( '%s: %s', __( 'Repair Unavailable', 'wp-simple-firewall' ), $oE->getMessage() );
				$bCanRepair = false;
			}

			$aE[ 'status' ] = implode( '<br/>', $aStatus );
			$aE[ 'can_repair' ] = $bCanRepair;
			$aE[ 'created_at' ] = $this->formatTimestampField( $oEntry->created_at );
			$aE[ 'href_download' ] = $oMod->createFileDownloadLink( $oEntry );
			$aEntries[ $nKey ] = $aE;
		}

		return $aEntries;
	}

	/**
	 * @param string $sText
	 * @return string
	 */
	private function getPatternForDisplay( $sText ) {
		if ( false && function_exists( 'imagecreate' ) ) {
			$oImg = imagecreate( 400, 20 );
			imagecolorallocate( $oImg, 255, 255, 255 );
			$oTxtColour = imagecolorallocate( $oImg, 25, 25, 25 );
			imagestring( $oImg, 5, 1, 1, $sText, $oTxtColour );
			ob_start();
			imagepng( $oImg );
			$sImg = ob_get_clean();
			imagedestroy( $oImg );
			$sPattern = sprintf( '<img src="data:image/png;base64,%s" alt="Pattern" />', base64_encode( $sImg ) );
		}
		else {
			$sPattern = sprintf( '<code>%s</code>', esc_html( $sText ) );
		}

		return $sPattern;
	}

	/**
	 * @return Shield\Tables\Render\ScanMal
	 */
	protected function getTableRenderer() {
		return new Shield\Tables\Render\ScanMal();
	}
}