<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ChangeTrack\Report\Build;

use FernleafSystems\Wordpress\Plugin\Shield\ChangeTrack;

class Base {

	use ChangeTrack\Snapshot\SnapshotsConsumer;

	public function run() {
		$aDiff = ( new ChangeTrack\Diff\DiffUsers() )
			->setNewSnapshot( $this->getNewSnapshot() )
			->setOldSnapshot( $this->getOldSnapshot() )
			->run();

		$bHasChanges = !empty( $aDiff[ 'added' ] ) && !empty( $aDiff[ 'removed' ] ) && !empty( $aDiff[ 'changed' ] );

		if ( $bHasChanges ) {
			$aAdded = $this->processAdded( $aDiff[ 'added' ] );
			$aRemoved = $this->processRemoved( $aDiff[ 'removed' ] );
			$aChanged = $this->processChanged( $aDiff[ 'changed' ] );
		}
	}

	/**
	 * @param array $aAdded
	 * @return array
	 */
	protected function processAdded( $aAdded ) {
		$aReport = [];
		if ( !empty( $aAdded ) ) {
			$aReport[ 'title' ] = 'Items Added';
			$aReport[ 'lines' ] = [];
			foreach ( $aAdded as $aNew ) {
				$aReport[ 'lines' ] = sprintf( 'New item added with unique ID "%s"', $aNew[ 'uniq' ] );
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
			$aReport[ 'title' ] = 'Items Changed';
			$aReport[ 'lines' ] = [];
			foreach ( $aChanged as $sUniqId => $aAttributes ) {
				$aReport[ 'lines' ] = sprintf(
					'The following attributes have changed on item with unique ID "%s": %s',
					$aAttributes[ 'uniq' ], implode( ', ', $aAttributes ) );
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
			$aReport[ 'title' ] = 'Items Removed';
			$aReport[ 'lines' ] = [];
			foreach ( $aRemoved as $aItem ) {
				$aReport[ 'lines' ] = sprintf( 'Item removed with uniqud ID "%s"', $aItem[ 'uniq' ] );
			}
		}
		return $aReport;
	}
}