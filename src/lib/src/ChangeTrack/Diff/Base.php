<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ChangeTrack\Diff;

class Base {

	/**
	 * @var array
	 */
	private $aOldSnapshot;

	/**
	 * @var array
	 */
	private $aNewSnapshot;

	public function run() {
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
		return [
			'slug',
			'modified_at',
			'hash_title',
			'hash_content',
		];
	}

	/**
	 * @return array
	 */
	public function getOldSnapshot() {
		return $this->aOldSnapshot;
	}

	/**
	 * @param array $aSnapshot
	 * @return $this
	 */
	public function setOldSnapshot( $aSnapshot ) {
		$this->aOldSnapshot = $this->structureSnapshotItems( $aSnapshot );
		return $this;
	}

	/**
	 * @return array
	 */
	public function getNewSnapshot() {
		return $this->aNewSnapshot;
	}

	/**
	 * @param array $aSnapshot
	 * @return $this
	 */
	public function setNewSnapshot( $aSnapshot ) {
		$this->aNewSnapshot = $this->structureSnapshotItems( $aSnapshot );
		return $this;
	}

	/**
	 * Ensures that the items in the snapshot array have keys that correspond to their uniq IDs.
	 * @param array[] $aSnapshotItems
	 * @return array[]
	 */
	private function structureSnapshotItems( $aSnapshotItems ) {
		$aStructured = [];
		foreach ( $aSnapshotItems as $aItem ) {
			$aStructured[ $aItem[ 'uniq' ] ] = $aItem;
		}
		return $aStructured;
	}
}