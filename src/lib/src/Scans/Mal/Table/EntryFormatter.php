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
		$aE = $this->getBaseData();
		$aE[ 'status' ] = __( 'Potential Malware Detected', 'wp-simple-firewall' );
		if ( !in_array( 'repair', $aE[ 'actions' ] ) ) {
			$aE[ 'explanation' ][] = __( 'Repair Unavailable', 'wp-simple-firewall' );
		}

		return $aE;
	}

	/**
	 * @return string[]
	 */
	protected function getExplanation() {
		/** @var Mal\ResultItem $oIt */
		$oIt = $this->getResultItem();

		$aExpl = [
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

		/** @var HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		if ( $oOpts->isMalUseNetworkIntelligence() ) {
			$aExpl[] = sprintf( '%s: %s/100 [%s]',
				__( 'False Positive Confidence' ),
				sprintf( '<strong>%s</strong>', (int)$oIt->fp_confidence ),
				sprintf( '<a href="%s" target="_blank">%s&nearr;</a>', 'https://shsec.io/isthismalware', __( 'more info', 'wp-simple-firewall' ) )
			);
		}

		return $aExpl;
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

	/**
	 * @inheritDoc
	 */
	protected function getSupportedActions() {
		$aActions = parent::getSupportedActions();

		/** @var Mal\ResultItem $oIt */
		$oIt = $this->getResultItem();

		try {
			$bCanRepair = ( new Mal\Utilities\Repair() )
				->setMod( $this->getMod() )
				->setScanItem( $oIt )
				->canRepair();
		}
		catch ( \Exception $oE ) {
			$bCanRepair = false;
		}

		$aActions[] = $bCanRepair ? 'repair' : 'delete';
		$aActions[] = 'download';

		return $aActions;
	}
}