<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg\Snapshots;

use FernleafSystems\Wordpress\Services\Services;

class Store {

	/**
	 * @var string
	 */
	private $sStorePath;

	/**
	 * @var string
	 */
	private $sContext;

	/**
	 * @var array
	 */
	private $aSnapData;

	/**
	 * @return string
	 */
	public function getContext() {
		return $this->sContext;
	}

	/**
	 * @return array[]
	 */
	public function getSnapData() {
		if ( !is_array( $this->aSnapData ) ) {
			$this->loadSnapDataIfExists();
		}
		return is_array( $this->aSnapData ) ? $this->aSnapData : array();
	}

	/**
	 * @return string
	 */
	public function getStorePath() {
		return $this->sStorePath;
	}

	/**
	 * @param string $sKey
	 * @param array  $aData
	 * @return $this
	 * @throws \Exception
	 */
	public function addSnapItem( $sKey, $aData ) {
		if ( empty( $aData ) || !is_array( $aData ) ) {
			throw new \Exception( 'Attempting to store invalid or empty snapshot data' );
		}
		$aSnaps = $this->getSnapData();
		$aSnaps[ $sKey ] = $aData;
		return $this->setSnapData( $aSnaps );
	}

	/**
	 * @return $this
	 */
	public function clearSnapshots() {
		$this->aSnapData = array();
		return $this;
	}

	/**
	 * @param string $sKey
	 * @return array|null
	 */
	public function getSnapItem( $sKey ) {
		return $this->itemExists( $sKey ) ? $this->getSnapData()[ $sKey ] : null;
	}

	/**
	 * All Snapshot items have 2x parts: meta & hashes.
	 * @return array
	 */
	public function getSnapDataHashesOnly() {
		return array_map(
			function ( $aSnap ) {
				return $aSnap[ 'hashes' ];
			},
			$this->getSnapData()
		);
	}

	/**
	 * @param string $sKey
	 * @return bool
	 */
	public function itemExists( $sKey ) {
		$aSnapData = $this->getSnapData();
		return isset( $aSnapData[ $sKey ] );
	}

	/**
	 * @param string $sKey
	 * @return $this
	 */
	public function removeItemSnapshot( $sKey ) {
		$aSnapData = $this->getSnapData();
		if ( isset( $aSnapData[ $sKey ] ) ) {
			unset( $aSnapData[ $sKey ] );
		}
		return $this->setSnapData( $aSnapData );
	}

	/**
	 * @throws \Exception
	 */
	public function deleteSnapshots() {
		$oFS = Services::WpFs();
		if ( $this->isReady() ) {
			$sSnapPath = $this->getSnapStorePath();
			if ( Services::WpFs()->exists( $sSnapPath ) ) {
				$oFS->deleteFile( $sSnapPath );
			}
		}
		return $this->clearSnapshots();
	}

	/**
	 * @return $this
	 */
	private function loadSnapDataIfExists() {
		try {
			$this->aSnapData = $this->readSnapData();
		}
		catch ( \Exception $oE ) {
			$this->aSnapData = array();
		}
		return $this;
	}

	/**
	 * @throws \Exception
	 */
	private function readSnapData() {
		$oFS = Services::WpFs();

		if ( $this->isReady() && !$this->getSnapStoreExists() ) {
			throw new \Exception( sprintf( 'Snapshot store does not exist: "%s"', $this->getSnapStorePath() ) );
		}

		$sEncoded = $oFS->getFileContent( $this->getSnapStorePath() );
		$aSnap = json_decode( base64_decode( $sEncoded ), true );
		if ( empty( $aSnap ) ) {
			throw new \Exception( 'Snapshot data could not be decoded' );
		}

		return $aSnap;
	}

	/**
	 * @return $this
	 * @throws \Exception
	 */
	public function save() {
		if ( $this->isReady() ) {
			Services::WpFs()->putFileContent(
				$this->getSnapStorePath(),
				base64_encode( json_encode( $this->getSnapData() ) )
			);
		}
		return $this;
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	protected function isReady() {
		$oFS = Services::WpFs();
		$sDir = $this->getStorePath();

		if ( strlen( $this->getContext() ) < 1 ) {
			throw new \Exception( 'Context has not been specified' );
		}
		if ( !$oFS->mkdir( $sDir ) ) {
			throw new \Exception( sprintf( 'Store directory could not be created: %s', $sDir ) );
		}
		if ( !$oFS->exists( $sDir ) ) {
			throw new \Exception( sprintf( 'Store directory path does not exist: %s', $sDir ) );
		}
		return true;
	}

	/**
	 * @return bool
	 */
	public function getSnapStoreExists() {
		return Services::WpFs()->exists( $this->getSnapStorePath() ) && $this->isSnapStoreRelevant();
	}

	/**
	 * We try to capture periods wherein which the plugin may have been deactivated and tracking has paused.
	 * @return bool
	 */
	public function isSnapStoreRelevant() {
		$bRelevant = true;
		$oFs = Services::WpFs();
		$mTime = Services::Request()->ts() - $oFs->getModifiedTime( $this->getSnapStorePath() );
		if ( $mTime > DAY_IN_SECONDS ) {
			$bRelevant = false;
		}
		else if ( $mTime > DAY_IN_SECONDS/2 ) {
			$oFs->touch( $this->getSnapStorePath() );
		}
		return $bRelevant;
	}

	/**
	 * @return string
	 */
	protected function getSnapStorePath() {
		return path_join( $this->getStorePath(), $this->getContext().'.txt' );
	}

	/**
	 * @param string $sContext
	 * @return $this
	 */
	public function setContext( $sContext ) {
		$this->sContext = $sContext;
		return $this;
	}

	/**
	 * @param array $aData
	 * @return $this
	 */
	public function setSnapData( $aData ) {
		$this->aSnapData = $aData;
		return $this;
	}

	/**
	 * @param string $sStorePath
	 * @return $this
	 */
	public function setStorePath( $sStorePath ) {
		$this->sStorePath = $sStorePath;
		return $this;
	}
}