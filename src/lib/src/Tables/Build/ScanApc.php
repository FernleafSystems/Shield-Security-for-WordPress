<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class ScanApc
 * @package FernleafSystems\Wordpress\Plugin\Shield\Tables\Build
 */
class ScanApc extends ScanBase {

	/**
	 * @return array[]
	 */
	protected function getEntriesFormatted() {
		$aEntries = array();

		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();

		$oWpPlugins = Services::WpPlugins();
		$nTs = Services::Request()->ts();
		foreach ( $this->getEntriesRaw() as $nKey => $oEntry ) {
			/** @var Shield\Databases\Scanner\EntryVO $oEntry */
			$oIt = ( new Shield\Scans\Apc\ConvertVosToResults() )->convertItem( $oEntry );
			$oPlugin = $oWpPlugins->getPluginAsVo( $oIt->slug );
			$aE = $oEntry->getRawDataAsArray();
			$aE[ 'plugin' ] = sprintf( '%s (%s)', $oPlugin->Name, $oPlugin->Version );
			$aE[ 'status' ] = 'Abandoned';
			$aE[ 'ignored' ] = ( $oEntry->ignored_at > 0 && $nTs > $oEntry->ignored_at ) ? 'Yes' : 'No';
			$aE[ 'created_at' ] = $this->formatTimestampField( $oEntry->created_at );
			$aEntries[ $nKey ] = $aE;
		}

		return $aEntries;
	}

	/**
	 * @return Shield\Tables\Render\ScanApc
	 */
	protected function getTableRenderer() {
		return new Shield\Tables\Render\ScanApc();
	}
}