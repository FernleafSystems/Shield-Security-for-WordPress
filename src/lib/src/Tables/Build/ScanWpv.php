<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class ScanWpv
 * @package FernleafSystems\Wordpress\Plugin\Shield\Tables\Build
 */
class ScanWpv extends ScanBase {

	/**
	 * @return array[]
	 */
	protected function getEntriesFormatted() {
		$aEntries = array();

		$oWpPlugins = Services::WpPlugins();

		$nTs = Services::Request()->ts();
		foreach ( $this->getEntriesRaw() as $nKey => $oEntry ) {
			/** @var Shield\Databases\Scanner\EntryVO $oEntry */
			$oIt = ( new Shield\Scans\Wpv\ConvertVosToResults() )->convertItem( $oEntry );
			$aE = $oEntry->getRawDataAsArray();
			$aE[ 'asset' ] = $oWpPlugins->getPluginAsVo( $oIt->slug );
			$aE[ 'has_update' ] = $oWpPlugins->isUpdateAvailable( $oIt->slug );
			$aE[ 'is_active' ] = $oWpPlugins->isActive( $oIt->slug );
			$aE[ 'wpvuln_vo' ] = $oIt->getWpVulnVo();
			$aE[ 'ignored' ] = ( $oEntry->ignored_at > 0 && $nTs > $oEntry->ignored_at ) ? 'Yes' : 'No';
			$aE[ 'created_at' ] = $this->formatTimestampField( $oEntry->created_at );
			$aEntries[ $nKey ] = $aE;
		}

		return $aEntries;
	}

	/**
	 * @return Shield\Tables\Render\ScanWpv
	 */
	protected function getTableRenderer() {
		return new Shield\Tables\Render\ScanWpv();
	}
}