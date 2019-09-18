<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan;

/**
 * Class ScanWcf
 * @package FernleafSystems\Wordpress\Plugin\Shield\Tables\Build
 */
class ScanWcf extends ScanBase {

	/**
	 * @return array[]
	 */
	protected function getEntriesFormatted() {
		$aEntries = [];

		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		$oConverter = ( new Scan\Results\ConvertBetweenTypes() )
			->setScanActionVO( $this->getScanActionVO() );
		foreach ( $this->getEntriesRaw() as $nKey => $oEntry ) {
			/** @var Shield\Databases\Scanner\EntryVO $oEntry */
			/** @var Shield\Scans\Wcf\ResultItem $oIt */
			$oIt = $oConverter->convertVoToResultItem( $oEntry );
			$aE = $oEntry->getRawDataAsArray();
			$aE[ 'path' ] = $oIt->path_fragment;
			$aE[ 'status' ] = $oIt->is_checksumfail ? __( 'Modified', 'wp-simple-firewall' )
				: ( $oIt->is_missing ? __( 'Missing', 'wp-simple-firewall' ) : __( 'Unknown', 'wp-simple-firewall' ) );
			$aE[ 'ignored' ] = $this->formatIsIgnored( $oEntry );
			$aE[ 'created_at' ] = $this->formatTimestampField( $oEntry->created_at );
			$aE[ 'href_download' ] = $oIt->is_missing ? false : $oMod->createFileDownloadLink( $oEntry );
			$aEntries[ $nKey ] = $aE;
		}

		return $aEntries;
	}

	/**
	 * @return Shield\Tables\Render\ScanWcf
	 */
	protected function getTableRenderer() {
		return new Shield\Tables\Render\ScanWcf();
	}
}