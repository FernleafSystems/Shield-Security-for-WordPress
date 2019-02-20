<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ChangeTrack\Report\Build;

use FernleafSystems\Wordpress\Services\Services;

class Plugins extends Base {

	/**
	 * @param array $aAdded
	 * @return array
	 */
	protected function processAdded( $aAdded ) {
		$aReport = [];
		if ( !empty( $aAdded ) ) {
			$aReport[ 'title' ] = 'Plugins Installed';
			$aReport[ 'lines' ] = [];
			$oWpPlugins = Services::WpPlugins();
			foreach ( $aAdded as $aItem ) {
				$oPlugin = $oWpPlugins->getPluginAsVo( $aItem[ 'uniq' ] );
				$aReport[ 'lines' ] = sprintf( 'Plugin added (File:%s): "%s"', $oPlugin->file, $oPlugin->Name );
			}
		}
		return $aReport;
	}

	/**
	 * @param array $aChanged
	 * @return array
	 */
	protected function processChanged( $aChanged ) {
		$aReport = [];
		if ( !empty( $aChanged ) ) {
			$aReport[ 'title' ] = "Plugins Changed";
			$aReport[ 'lines' ] = [];
			$oWpPlugins = Services::WpPlugins();
			foreach ( $aChanged as $sUniqId => $aAttributes ) {
				$oPlugin = $oWpPlugins->getPluginAsVo( $sUniqId );
				$aReport[ 'lines' ] = sprintf( 'Plugin "%s" (File:%s) changed attributes: %s',
					$oPlugin->Name, $sUniqId, implode( ', ', $aAttributes ) );
			}
		}
		return $aReport;
	}

	/**
	 * @param array $aRemoved
	 * @return array
	 */
	protected function processRemoved( $aRemoved ) {
		$aReport = [];
		if ( !empty( $aRemoved ) ) {
			$aReport[ 'title' ] = 'Plugins Removed';
			$aReport[ 'lines' ] = [];
			$oWpPlugins = Services::WpPlugins();
			foreach ( $aRemoved as $aItem ) {
				$oPlugin = $oWpPlugins->getPluginAsVo( $aItem[ 'uniq' ] );
				$aReport[ 'lines' ] = sprintf( 'Plugin removed (File:%s): "%s"', $oPlugin->file, $oPlugin->Name );
			}
		}
		return $aReport;
	}
}