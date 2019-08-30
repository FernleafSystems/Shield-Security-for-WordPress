<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Common;

use FernleafSystems\Utilities\Data\Adapter\StdClassAdapter;

/**
 * Class ScanActionVO
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Common
 * @property bool  $is_cron
 * @property array $scans
 */
class ScansJobVO {

	use StdClassAdapter;

	/**
	 * @return $this
	 */
	public function clearScans() {
		return $this->setScans( [] );
	}

	/**
	 * @return array|null
	 */
	public function getCurrentScan() {
		$aCurrent = null;
		foreach ( $this->getInitiatedScans() as $aScanInfo ) {
			if ( isset( $aScanInfo[ 'current' ] ) && $aScanInfo[ 'current' ] ) {
				$aCurrent = $aScanInfo;
				break;
			}
		}
		return $aCurrent;
	}

	/**
	 * @return array
	 */
	public function getInitiatedScans() {
		return array_filter(
			$this->getScans(),
			function ( $aScan ) {
				return !empty( $aScan[ 'created_at' ] );
			}
		);
	}

	/**
	 * @return array[] - keys: scan slugs; values: array of created_at, id
	 */
	public function getUnfinishedScans() {
		return array_filter(
			$this->getInitiatedScans(),
			function ( $aScan ) {
				return empty( $aScan[ 'finished_at' ] );
			}
		);
	}

	/**
	 * @param string $sScanSlug
	 * @param array  $aScanInfo
	 * @return $this
	 */
	public function setScanInfo( $sScanSlug, $aScanInfo ) {
		$aScans = $this->getScans();
		$aScans[ $sScanSlug ] = array_merge(
			[
				'scan'       => $sScanSlug,
				'created_at' => 0,
				'current'    => false,
			],
			$aScanInfo
		);
		return $this->setScans( $aScans );
	}

	/**
	 * @param string $sScanSlug
	 * @return array
	 */
	public function getScanInfo( $sScanSlug ) {
		$aScn = $this->getScans();
		$aScanInfo = isset( $aScn[ $sScanSlug ] ) ? $aScn[ $sScanSlug ] : [];
		return array_merge(
			[
				'scan'       => $sScanSlug,
				'created_at' => 0,
				'current'    => false,
			],
			$aScanInfo
		);
	}

	/**
	 * @param string $sScanSlug
	 * @return int
	 */
	public function getScanInitTime( $sScanSlug ) {
		return $this->getScanInfo( $sScanSlug )[ 'created_at' ];
	}

	/**
	 * @return float
	 */
	public function getScanJobProgress() {
		if ( $this->hasScansToRun() ) {
			$nProgress = 1 - ( count( $this->getUnfinishedScans() )/count( $this->getInitiatedScans() ) );
		}
		else {
			$nProgress = 1;
		}
		return $nProgress;
	}

	/**
	 * @return bool
	 */
	public function hasScansToRun() {
		return count( $this->getUnfinishedScans() ) > 0;
	}

	/**
	 * @param string $sScanSlug
	 * @return bool
	 */
	public function isScanInited( $sScanSlug ) {
		return $this->getScanInitTime( $sScanSlug ) > 0;
	}

	/**
	 * @param string $sScanSlug
	 * @return $this
	 */
	public function removeInitiatedScan( $sScanSlug ) {
		if ( $this->isScanInited( $sScanSlug ) ) {
			$aScan = $this->getScanInfo( $sScanSlug );
			$aScan[ 'created_at' ] = 0;
			$this->setScanInfo( $sScanSlug, $aScan );
		}
		return $this;
	}

	/**
	 * @param string $sScanSlug
	 * @param bool   $bSetAsCurrent
	 * @return $this
	 */
	public function setScanAsCurrent( $sScanSlug, $bSetAsCurrent = true ) {
		$aScanInfo = $this->getScanInfo( $sScanSlug );
		if ( !empty( $aScanInfo ) && $aScanInfo[ 'created_at' ] > 0 ) {
			if ( is_null( $this->getCurrentScan() ) && $bSetAsCurrent ) {
				$aScanInfo[ 'current' ] = true;
				$this->setScanInfo( $sScanSlug, $aScanInfo );
			}
			else if ( !$bSetAsCurrent ) {
				$aScanInfo[ 'current' ] = false;
				$this->setScanInfo( $sScanSlug, $aScanInfo );
			}
		}
		return $this;
	}

	/**
	 * @param array $aScans
	 * @return $this
	 */
	private function setScans( $aScans ) {
		if ( !is_array( $aScans ) ) {
			$aScans = [];
		}
		ksort( $aScans );
		$this->scans = $aScans;
		return $this;
	}

	/**
	 * @return array
	 */
	public function getScans() {
		if ( !is_array( $this->scans ) ) {
			$this->scans = [];
		}
		return $this->scans;
	}
}