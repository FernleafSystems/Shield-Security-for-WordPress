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
			foreach ( $aCompareAttrs as $sAttribute ) {
				if ( $aOld[ $sAttribute ] != $aNew[ $sAttribute ] ) {
					$aChanged[ $nId ][] = $sAttribute;
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

	/**
	 * @return array
	 */
	public function getOldSnapshot() {
		return $this->aOldSnapshot;
	}

	/**
	 * @param array $aOldSnapshot
	 * @return $this
	 */
	public function setOldSnapshot( $aOldSnapshot ) {
		$this->aOldSnapshot = $aOldSnapshot;
		return $this;
	}

	/**
	 * @return array
	 */
	public function getNewSnapshot() {
		return $this->aNewSnapshot;
	}

	/**
	 * @param array $aNewSnapshot
	 * @return $this
	 */
	public function setNewSnapshot( $aNewSnapshot ) {
		$this->aNewSnapshot = $aNewSnapshot;
		return $this;
	}
}