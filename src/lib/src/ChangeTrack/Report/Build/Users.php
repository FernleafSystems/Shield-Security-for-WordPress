<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ChangeTrack\Report\Build;

use FernleafSystems\Wordpress\Services\Services;

class Users extends Base {

	/**
	 * @param array $aAdded
	 * @return array
	 */
	protected function processAdded( $aAdded ) {
		$aReport = [];
		if ( !empty( $aAdded ) ) {
			$aReport[ 'title' ] = 'Users Created';
			$aReport[ 'lines' ] = [];
			foreach ( $aAdded as $aItem ) {
				$aReport[ 'lines' ] = sprintf( 'New user added with ID %s and username "%s"', $aItem[ 'uniq' ], $aItem[ 'user_login' ] );
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
			$oWpUsers = Services::WpUsers();
			$aReport[ 'title' ] = "Users Changed";
			$aReport[ 'lines' ] = [];
			foreach ( $aChanged as $sUniqId => $aAttributes ) {
				$aReport[ 'lines' ] = sprintf( 'User "%s" (ID:%s) changed attributes: %s',
					$oWpUsers->getUserById( $sUniqId )->user_login, $sUniqId, implode( ', ', $aAttributes ) );
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
			$aReport[ 'title' ] = 'Users Removed';
			$aReport[ 'lines' ] = [];
			foreach ( $aRemoved as $aItem ) {
				$aReport[ 'lines' ] = sprintf( 'User removed with ID %s and username "%s"', $aItem[ 'uniq' ], $aItem[ 'user_login' ] );
			}
		}
		return $aReport;
	}
}