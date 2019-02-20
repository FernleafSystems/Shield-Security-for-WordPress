<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ChangeTrack\Report\Build;

use FernleafSystems\Wordpress\Services\Services;

class Themes extends Base {

	/**
	 * @param array $aAdded
	 * @return array
	 */
	protected function processAdded( $aAdded ) {
		$aReport = [];
		if ( !empty( $aAdded ) ) {
			$aReport[ 'title' ] = 'Themes Installed';
			$aReport[ 'lines' ] = [];
			$oWpThemes = Services::WpThemes();
			foreach ( $aAdded as $aItem ) {
				$oItem = $oWpThemes->getTheme( $aItem[ 'uniq' ] );
				$aReport[ 'lines' ] = sprintf( 'Theme added (dir:%s): "%s"', $oItem->get_stylesheet(), $oItem->get( 'Name' ) );
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
			$aReport[ 'title' ] = "Themes Changed";
			$aReport[ 'lines' ] = [];
			$oWpThemes = Services::WpThemes();
			foreach ( $aChanged as $sUniqId => $aAttributes ) {
				$oItem = $oWpThemes->getTheme( $sUniqId );
				$aReport[ 'lines' ] = sprintf( 'Theme "%s" (dir:%s) changed attributes: %s',
					$oItem->get( 'Name' ), $oItem->get_stylesheet(), implode( ', ', $aAttributes ) );
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
			$aReport[ 'title' ] = 'Themes Removed';
			$aReport[ 'lines' ] = [];
			foreach ( $aRemoved as $aItem ) {
				$aReport[ 'lines' ] = sprintf( 'Theme removed (dir:%s): "%s"', $aItem[ 'uniq' ], $aItem[ 'name' ] );
			}
		}
		return $aReport;
	}
}