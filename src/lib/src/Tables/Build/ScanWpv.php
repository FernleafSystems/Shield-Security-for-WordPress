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
		$entries = [];

		/** @var ModCon $mod */
		$mod = $this->getMod();

		$WPP = Services::WpPlugins();
		$WPT = Services::WpThemes();

		// so that any available update will show
		$WPP->getUpdates( true );
		$WPT->getUpdates( true );

		$oConverter = new Scan\Results\ConvertBetweenTypes();
		foreach ( $this->getEntriesRaw() as $key => $entry ) {
			/** @var Shield\Databases\Scanner\EntryVO $entry */
			/** @var Shield\Scans\Wpv\ResultItem $item */
			$item = $oConverter
				->setScanController( $mod->getScanCon( $entry->scan ) )
				->convertVoToResultItem( $entry );
			$e = $entry->getRawData();
			if ( $item->context == 'plugins' ) {
				$asset = $WPP->getPluginAsVo( $item->slug );
				$e[ 'asset' ] = $asset;
				$e[ 'asset_name' ] = $asset->Name;
				$e[ 'asset_version' ] = $asset->Version;
				$e[ 'can_deactivate' ] = $WPP->isActive( $item->slug );
				$e[ 'has_update' ] = $WPP->isUpdateAvailable( $item->slug );
			}
			else {
				$asset = $WPT->getTheme( $item->slug );
				$e[ 'asset' ] = $asset;
				$e[ 'asset_name' ] = $asset->get( 'Name' );
				$e[ 'asset_version' ] = $asset->get( 'Version' );
				$e[ 'can_deactivate' ] = false;
				$e[ 'has_update' ] = $WPT->isUpdateAvailable( $item->slug );
			}
			$e[ 'slug' ] = $item->slug;
			$e[ 'wpvuln_vo' ] = $item->getWpVulnVo();
			$e[ 'ignored' ] = $this->formatIsIgnored( $entry );
			$e[ 'created_at' ] = $this->formatTimestampField( $entry->created_at );
			$entries[ $key ] = $e;
		}

		return $entries;
	}

	/**
	 * @return Shield\Tables\Render\WpListTable\ScanWpv
	 */
	protected function getTableRenderer() {
		return new Shield\Tables\Render\WpListTable\ScanWpv();
	}
}