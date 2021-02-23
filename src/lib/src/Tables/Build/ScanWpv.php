<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class ScanWpv
 * @package FernleafSystems\Wordpress\Plugin\Shield\Tables\Build
 */
class ScanWpv extends ScanBase {

	/**
	 * @return array[]
	 */
	public function getEntriesFormatted() :array {
		$aEntries = [];

		/** @var ModCon $mod */
		$mod = $this->getMod();

		$oWpPlugins = Services::WpPlugins();
		$oWpThemes = Services::WpThemes();

		// so that any available update will show
		$oWpPlugins->getUpdates( true );
		$oWpThemes->getUpdates( true );

		$oConverter = new Scan\Results\ConvertBetweenTypes();
		foreach ( $this->getEntriesRaw() as $nKey => $entry ) {
			/** @var Shield\Databases\Scanner\EntryVO $entry */
			/** @var Shield\Scans\Wpv\ResultItem $oIt */
			$oIt = $oConverter
				->setScanController( $mod->getScanCon( $entry->scan ) )
				->convertVoToResultItem( $entry );
			$aE = $entry->getRawData();
			if ( $oIt->context == 'plugins' ) {
				$oAsset = $oWpPlugins->getPluginAsVo( $oIt->slug );
				$aE[ 'asset' ] = $oAsset;
				$aE[ 'asset_name' ] = $oAsset->Name;
				$aE[ 'asset_version' ] = $oAsset->Version;
				$aE[ 'can_deactivate' ] = $oWpPlugins->isActive( $oIt->slug );
				$aE[ 'has_update' ] = $oWpPlugins->isUpdateAvailable( $oIt->slug );
			}
			else {
				$oAsset = $oWpThemes->getTheme( $oIt->slug );
				$aE[ 'asset' ] = $oAsset;
				$aE[ 'asset_name' ] = $oAsset->get( 'Name' );
				$aE[ 'asset_version' ] = $oAsset->get( 'Version' );
				$aE[ 'can_deactivate' ] = false;
				$aE[ 'has_update' ] = $oWpThemes->isUpdateAvailable( $oIt->slug );
			}
			$aE[ 'slug' ] = $oIt->slug;
			$aE[ 'wpvuln_vo' ] = $oIt->getWpVulnVo();
			$aE[ 'ignored' ] = $this->formatIsIgnored( $entry );
			$aE[ 'created_at' ] = $this->formatTimestampField( $entry->created_at );
			$aEntries[ $nKey ] = $aE;
		}

		return $aEntries;
	}

	/**
	 * @return Shield\Tables\Render\WpListTable\ScanWpv
	 */
	protected function getTableRenderer() {
		return new Shield\Tables\Render\WpListTable\ScanWpv();
	}
}