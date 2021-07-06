<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal\Table;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Table\BaseFileEntryFormatter;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal;

class EntryFormatter extends BaseFileEntryFormatter {

	public function format() :array {
		$e = $this->getBaseData();
		$e[ 'status' ] = __( 'Potential Malware Detected', 'wp-simple-firewall' );
		if ( !array_key_exists( 'repair', $e[ 'actions' ] ) ) {
			$e[ 'explanation' ][] = __( 'Repair Unavailable', 'wp-simple-firewall' );
		}
		return $e;
	}

	/**
	 * @return string[]
	 */
	protected function getExplanation() :array {
		/** @var Mal\ResultItem $item */
		$item = $this->getResultItem();

		$exp = [
			sprintf( '%s: %s', __( 'Pattern Detected' ), $this->getPatternForDisplay( base64_decode( $item->mal_sig ) ) ),
			sprintf( '%s: %s', __( 'Affected line numbers' ),
				implode( ', ', array_map(
					function ( $nLineNumber ) {
						return $nLineNumber + 1;
					},
					$item->file_lines // because lines start at ZERO
				) )
			),
		];

		/** @var HackGuard\Options $opts */
		$opts = $this->getOptions();
		if ( $opts->isMalUseNetworkIntelligence() ) {
			$exp[] = sprintf( '%s: %s/100 [%s]',
				__( 'Likelihood That This Is A False Positive' ),
				sprintf( '<strong>%s</strong>', (int)$item->fp_confidence ),
				sprintf( '<a href="%s" target="_blank">%s</a>', 'https://shsec.io/isthismalware', __( 'more info', 'wp-simple-firewall' ) )
			);
		}

		return $exp;
	}

	/**
	 * @param string $text
	 * @return string
	 */
	private function getPatternForDisplay( $text ) :string {
		return sprintf( '<code style="white-space: nowrap">%s</code>', esc_html( $text ) );
	}

	/**
	 * @inheritDoc
	 */
	protected function getSupportedActions() :array {
		$actions = parent::getSupportedActions();

		/** @var Mal\ResultItem $item */
		$item = $this->getResultItem();

		try {
			$bCanRepair = ( new Mal\Utilities\Repair() )
				->setMod( $this->getMod() )
				->setScanItem( $item )
				->canRepair();
		}
		catch ( \Exception $e ) {
			$bCanRepair = false;
		}

		$actions[] = $bCanRepair ? 'repair' : 'delete';
		$actions[] = 'download';

		return $actions;
	}
}