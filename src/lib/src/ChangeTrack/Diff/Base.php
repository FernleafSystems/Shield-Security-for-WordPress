<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ChangeTrack\Diff;

use FernleafSystems\Wordpress\Plugin\Shield\ChangeTrack\Snapshot\SnapshotsConsumer;

class Base {

	use SnapshotsConsumer;

	/**
	 * @return array[]
	 */
	public function run() {
		return [
			'added'   => $this->getAdded(),
			'removed' => $this->getRemoved(),
			'changed' => $this->getChangedItems(),
		];
	}

	/**
	 * @return array
	 */
	protected function getAdded() {
		return array_diff_key( $this->getNewSnapshot(), $this->getOldSnapshot() );
	}

	/**
	 * @return array - key is the ID of the item, value is array of changed attributes
	 */
	protected function getChangedItems() {
		$aChanged = [];

		$aCompareAttrs = $this->getAttributesToCompare();
		foreach ( array_keys( $this->getCommonElements() ) as $nId ) {
			$aOld = $this->getOldSnapshot()[ $nId ];
			$aNew = $this->getNewSnapshot()[ $nId ];

			$aChanged[ $nId ] = [];
			foreach ( $aCompareAttrs as $sAttr ) {
				if ( isset( $aOld[ $sAttr ] ) && isset( $aNew[ $sAttr ] ) && $aOld[ $sAttr ] != $aNew[ $sAttr ] ) {
					$aChanged[ $nId ][] = $sAttr;
				}
			}
		}

		return array_filter( $aChanged );
	}

	/**
	 * @return array
	 */
	protected function getCommonElements() {
		return array_intersect_key( $this->getOldSnapshot(), $this->getNewSnapshot() );
	}

	/**
	 * @return array
	 */
	protected function getRemoved() {
		return array_diff_key( $this->getOldSnapshot(), $this->getNewSnapshot() );
	}

	/**
	 * @return string[]
	 */
	protected function getAttributesToCompare() {
		return [];
	}
}