<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ChangeTrack\Report;

use FernleafSystems\Wordpress\Plugin\Shield\ChangeTrack;
use FernleafSystems\Wordpress\Services\Services;

class BuildReportUsers extends BuildReportBase {

	public function run() {
		$aDiff = ( new ChangeTrack\Diff\DiffUsers() )
			->setNewSnapshot( $this->getNewSnapshot() )
			->setOldSnapshot( $this->getOldSnapshot() )
			->run();

		$bHasChanges = !empty( $aDiff[ 'added' ] ) && !empty( $aDiff[ 'removed' ] ) && !empty( $aDiff[ 'changed' ] );

		if ( $bHasChanges ) {
			$aAdded = $this->processAdded( $aDiff[ 'added' ] );
			$aRemoved = $this->processRemoved( $aDiff[ 'removed' ] );
		}
	}

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
			$aReport[ 'title' ] = "The following users' attributes have changed";
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