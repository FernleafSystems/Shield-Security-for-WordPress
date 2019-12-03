<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal\Table;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Table\BaseFileEntryFormatter;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal;

class EntryFormatter extends BaseFileEntryFormatter {

	/**
	 * @return array
	 */
	public function format() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		/** @var HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		$oRepairer = ( new Mal\Repair() )->setMod( $oMod );

		/** @var Mal\ResultItem $oIt */
		$oIt = $this->getResultItem();
		$aE = $this->getBaseData();

		$aE[ 'status' ] = __( 'Potential Malware Detected', 'wp-simple-firewall' );
		$aE[ 'explanation' ] = [
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
			$aE[ 'explanation' ][] = sprintf( '%s: %s/100 [%s]',
				__( 'False Positive Confidence' ),
				sprintf( '<strong>%s</strong>', (int)$oIt->fp_confidence ),
				sprintf( '<a href="%s" target="_blank">%s&nearr;</a>', 'https://shsec.io/isthismalware', __( 'more info', 'wp-simple-firewall' ) )
			);
		}

		try {
			$bCanRepair = $oRepairer->canAutoRepairFromSource( $oIt );
		}
		catch ( \Exception $oE ) {
			$aE[ 'explanation' ][] = sprintf( '%s: %s', __( 'Repair Unavailable', 'wp-simple-firewall' ), $oE->getMessage() );
			$bCanRepair = false;
		}

		$aE[ 'can_repair' ] = $bCanRepair;
		return $aE;
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
			$sPattern = sprintf( '<code style="white-space: nowrap">%s</code>', esc_html( $sText ) );
		}

		return $sPattern;
	}
}