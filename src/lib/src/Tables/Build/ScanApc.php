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
	public function getEntriesFormatted() :array {
		$aEntries = [];

		/** @var Shield\Modules\HackGuard\ModCon $mod */
		$mod = $this->getMod();

		$oCarbon = Services::Request()->carbon();

		$oConverter = new Scan\Results\ConvertBetweenTypes();

		$oWpPlugins = Services::WpPlugins();
		foreach ( $this->getEntriesRaw() as $nKey => $entry ) {
			/** @var Shield\Databases\Scanner\EntryVO $entry */
			/** @var Shield\Scans\Apc\ResultItem $item */
			$item = $oConverter
				->setScanController( $mod->getScanCon( $entry->scan ) )
				->convertVoToResultItem( $entry );
			$oPlugin = $oWpPlugins->getPluginAsVo( $item->slug );
			$aE = $entry->getRawData();
			$aE[ 'plugin' ] = sprintf( '%s (%s)', $oPlugin->Name, $oPlugin->Version );
			$aE[ 'status' ] = sprintf( '%s: %s',
				__( 'Abandoned', 'wp-simple-firewall' ), $oCarbon->setTimestamp( $item->last_updated_at )
																 ->diffForHumans() );
			$aE[ 'ignored' ] = $this->formatIsIgnored( $entry );
			$aE[ 'created_at' ] = $this->formatTimestampField( $entry->created_at );
			$aEntries[ $nKey ] = $aE;
		}

		return $aEntries;
	}

	/**
	 * @return Shield\Tables\Render\WpListTable\ScanApc
	 */
	protected function getTableRenderer() {
		return new Shield\Tables\Render\WpListTable\ScanApc();
	}
}