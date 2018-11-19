<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class ScanUfc
 * @package FernleafSystems\Wordpress\Plugin\Shield\Tables\Build
 */
class ScanUfc extends ScanBase {

	/**
	 * @return array[]
	 */
	protected function getEntriesFormatted() {
		$aEntries = array();

		$nTs = Services::Request()->ts();
		foreach ( $this->getEntriesRaw() as $nKey => $oEntry ) {
			/** @var Shield\Databases\Scanner\EntryVO $oEntry */
			$oIt = ( new Shield\Scans\UnrecognisedCore\ConvertVosToResults() )->convertItem( $oEntry );
			$aE = $oEntry->getRawData();
			$aE[ 'path' ] = $oIt->path_fragment;
			$aE[ 'status' ] = 'Unrecognised File';
			$aE[ 'ignored' ] = ( $oEntry->ignored_at > 0 && $nTs > $oEntry->ignored_at ) ? 'Yes' : 'No';
			$aE[ 'created_at' ] = $this->formatTimestampField( $oEntry->created_at );
			$aEntries[ $nKey ] = $aE;
		}

		return $aEntries;
	}

	/**
	 * @return Shield\Tables\Render\ScanUfc
	 */
	protected function getTableRenderer() {
		return new Shield\Tables\Render\ScanUfc();
	}
}