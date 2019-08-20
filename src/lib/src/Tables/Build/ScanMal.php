<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Build;

use FernleafSystems\Wordpress\Plugin\Shield;

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
		foreach ( $this->getEntriesRaw() as $nKey => $oEntry ) {
			/** @var Shield\Databases\Scanner\EntryVO $oEntry */
			$oIt = ( new Shield\Scans\Mal\ConvertVosToResults() )->convertItem( $oEntry );
			$aE = $oEntry->getRawDataAsArray();
			$aE[ 'path' ] = $oIt->path_fragment;
			$aE[ 'status' ] = __( 'Potential Malware Detected', 'wp-simple-firewall' );
			$aE[ 'ignored' ] = $this->formatIsIgnored( $oEntry );
			try {
				$bCanRepair = $oRepairer->canAutoRepairFromSource( $oIt );
			}
			catch ( \Exception $oE ) {
				$aE[ 'status' ] .= sprintf( '<br/>%s: %s',
					__( "Repair Unavailable", 'wp-simple-firewall' ),
					__( "Plugin developer doesn't use SVN tags for official releases.", 'wp-simple-firewall' )
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