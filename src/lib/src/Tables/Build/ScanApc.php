<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan;
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
		$aEntries = [];

		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();

		$oCarbon = Services::Request()->carbon();

		$oConverter = new Scan\Results\ConvertBetweenTypes();

		$oWpPlugins = Services::WpPlugins();
		foreach ( $this->getEntriesRaw() as $nKey => $oEntry ) {
			/** @var Shield\Databases\Scanner\EntryVO $oEntry */
			/** @var Shield\Scans\Apc\ResultItem $oIt */
			$oIt = $oConverter
				->setScanController( $oMod->getScanCon( $oEntry->scan ) )
				->convertVoToResultItem( $oEntry );
			$oPlugin = $oWpPlugins->getPluginAsVo( $oIt->slug );
			$aE = $oEntry->getRawDataAsArray();
			$aE[ 'plugin' ] = sprintf( '%s (%s)', $oPlugin->Name, $oPlugin->Version );
			$aE[ 'status' ] = sprintf( '%s: %s',
				__( 'Abandoned', 'wp-simple-firewall' ), $oCarbon->setTimestamp( $oIt->last_updated_at )
																 ->diffForHumans() );
			$aE[ 'ignored' ] = $this->formatIsIgnored( $oEntry );
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