<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ChangeTrack\Report\Build;

class Comments extends Base {

	/**
	 * @param array $aAdded
	 * @return array
	 */
	protected function processAdded( $aAdded ) {
		$aReport = [];
		if ( !empty( $aAdded ) ) {
			$aReport[ 'title' ] = 'New Comments';
			$aReport[ 'lines' ] = [];
			foreach ( $aAdded as $aItem ) {
				$aReport[ 'lines' ] = sprintf( 'Comment Published ID:%s', $aItem[ 'uniq' ] );
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
			$aReport[ 'title' ] = "Comments Changed";
			$aReport[ 'lines' ] = [];
			foreach ( $aChanged as $sUniqId => $aAttributes ) {
				$aReport[ 'lines' ] = sprintf( 'Comment (ID:%s) changed attributes: %s',
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
			$aReport[ 'title' ] = 'Comments Removed';
			$aReport[ 'lines' ] = [];
			foreach ( $aRemoved as $aItem ) {
				$aReport[ 'lines' ] = sprintf( 'Comment Removed ID: %s', $aItem[ 'uniq' ] );
			}
		}
		return $aReport;
	}
}