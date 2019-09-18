<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan;

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
		$oRepairer = ( new Shield\Scans\Mal\Repair() )->setMod( $oMod );
		$oConverter = ( new Scan\Results\ConvertBetweenTypes() )
			->setScanActionVO( $this->getScanActionVO() );

		foreach ( $this->getEntriesRaw() as $nKey => $oEntry ) {
			/** @var Shield\Databases\Scanner\EntryVO $oEntry */
			/** @var Shield\Scans\Mal\ResultItem $oIt */
			$oIt = $oConverter->convertVoToResultItem( $oEntry );
			$aE = $oEntry->getRawDataAsArray();
			$aE[ 'path' ] = $oIt->path_fragment;
			$aE[ 'status' ] = __( 'Potential Malware Detected', 'wp-simple-firewall' );
			$aE[ 'ignored' ] = $this->formatIsIgnored( $oEntry );
			try {
				$bCanRepair = $oRepairer->canAutoRepairFromSource( $oIt );
			}
			catch ( \Exception $oE ) {
				$aE[ 'status' ] .= sprintf( '<br/>%s: %s',
					__( 'Repair Unavailable', 'wp-simple-firewall' ),
					$oE->getMessage()
				);
				$bCanRepair = false;
			}
			$aE[ 'can_repair' ] = $bCanRepair;
			$aE[ 'created_at' ] = $this->formatTimestampField( $oEntry->created_at );
			$aE[ 'href_download' ] = $oMod->createFileDownloadLink( $oEntry );
			$aEntries[ $nKey ] = $aE;
		}

		return $aEntries;
	}

	/**
	 * @return Shield\Tables\Render\ScanMal
	 */
	protected function getTableRenderer() {
		return new Shield\Tables\Render\ScanMal();
	}
}