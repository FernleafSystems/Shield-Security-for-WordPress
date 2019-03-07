<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ChangeTrack\Snapshot;

trait SnapshotsConsumer {

	/**
	 * @var array
	 */
	private $aOldSnapshot;

	/**
	 * @var array
	 */
	private $aNewSnapshot;

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