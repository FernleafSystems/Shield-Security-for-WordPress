<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ChangeTrack\Report\Build;

class Media extends Base {

	/**
	 * @param array $aAdded
	 * @return array
	 */
	protected function processAdded( $aAdded ) {
		$aReport = [];
		if ( !empty( $aAdded ) ) {
			$aReport[ 'title' ] = 'Media Published';
			$aReport[ 'lines' ] = [];
			foreach ( $aAdded as $aItem ) {
				$aReport[ 'lines' ] = sprintf( 'Media added (slug:%s): "%s"', $aItem[ 'slug' ], $aItem[ 'title' ] );
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
			$aReport[ 'title' ] = "Media Changed";
			$aReport[ 'lines' ] = [];
			foreach ( $aChanged as $sUniqId => $aAttributes ) {
				$aReport[ 'lines' ] = sprintf( 'Media change (ID:%s) changed attributes: %s',
					$sUniqId, implode( ', ', $aAttributes ) );
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
			$aReport[ 'title' ] = 'Media Removed';
			$aReport[ 'lines' ] = [];
			foreach ( $aRemoved as $aItem ) {
				$aReport[ 'lines' ] = sprintf( 'Media removed (slug:%s): "%s"', $aItem[ 'slug' ], $aItem[ 'title' ] );
			}
		}
		return $aReport;
	}
}