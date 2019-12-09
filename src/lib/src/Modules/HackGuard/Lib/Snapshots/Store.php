<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots;

use FernleafSystems\Wordpress\Services\Core\VOs\WpPluginVo;
use FernleafSystems\Wordpress\Services\Core\VOs\WpThemeVo;
use FernleafSystems\Wordpress\Services\Services;

class Store extends Base {

	const SEPARATOR = '=::=';

	/**
	 * @var array
	 */
	private $aSnapMeta;

	/**
	 * @var array
	 */
	private $aSnapData;

	/**
	 * @var WpPluginVo|WpThemeVo
	 */
	private $oAsset;

	/**
	 * Store constructor.
	 * @param WpPluginVo|WpThemeVo $oAsset
	 */
	public function __construct( $oAsset ) {
		$this->oAsset = $oAsset;
	}

	/**
	 * @return WpPluginVo|WpThemeVo
	 */
	public function getAsset() {
		return $this->oAsset;
	}

	/**
	 * @return string
	 */
	public function getContext() {
		return ( $this->getAsset() instanceof WpPluginVo ) ? 'plugins' : 'themes';
	}

	/**
	 * @return string
	 */
	protected function getSnapStorePath() {
		return path_join( $this->getStorePath(), path_join( $this->getContext(), $this->getSlug() ) ).'.txt';
	}

	/**
	 * @return string
	 */
	protected function getSnapStoreMetaPath() {
		return path_join( $this->getStorePath(), path_join( $this->getContext(), $this->getSlug().'_meta' ) ).'.txt';
	}

	/**
	 * @return string
	 */
	protected function getSlug() {
		$oAs = $this->getAsset();
		return ( $oAs instanceof WpPluginVo ) ? dirname( $oAs->file ) : $oAs->stylesheet;
	}

	/**
	 * @return array[]
	 */
	public function getSnapData() {
		if ( !is_array( $this->aSnapData ) ) {
			$this->loadSnapDataIfExists();
		}
		return is_array( $this->aSnapData ) ? $this->aSnapData : [];
	}

	/**
	 * @return array[]
	 */
	public function getSnapMeta() {
		if ( empty( $this->aSnapMeta ) ) {
			$this->loadSnapMetaIfExists();
		}
		return is_array( $this->aSnapMeta ) ? $this->aSnapMeta : [];
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
		$this->aSnapData = [];
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
			$this->aSnapData = [];
		}
		return $this;
	}

	/**
	 * @return $this
	 */
	private function loadSnapMetaIfExists() {
		try {
			$this->aSnapMeta = $this->readSnapMeta();
		}
		catch ( \Exception $oE ) {
			$this->aSnapMeta = [];
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

		$sEncoded = $oFS->getFileContent( $this->getSnapStorePath(), true );
		if ( !empty( $sEncoded ) ) {
			$aSnap = [];
			foreach ( array_map( 'trim', explode( "\n", $sEncoded ) ) as $sLine ) {
				list( $sFile, $sHash ) = explode( self::SEPARATOR, $sLine, 2 );
				$aSnap[ $sFile ] = $sHash;
			}
		}
		if ( empty( $aSnap ) ) {
			throw new \Exception( 'Snapshot data could not be decoded' );
		}

		return $aSnap;
	}

	/**
	 * @throws \Exception
	 */
	private function readSnapMeta() {
		$oFS = Services::WpFs();

		if ( $this->isReady() && !$this->getSnapStoreExists() ) {
			throw new \Exception( sprintf( 'Snapshot store does not exist: "%s"', $this->getSnapStorePath() ) );
		}

		$sEncoded = $oFS->getFileContent( $this->getSnapStoreMetaPath(), true );
		if ( !empty( $sEncoded ) ) {
			$aData = json_decode( $sEncoded, true );
		}
		if ( empty( $aData ) || !is_array( $aData ) ) {
			throw new \Exception( 'Snapshot data could not be decoded' );
		}

		return $aData;
	}

	/**
	 * @return $this
	 * @throws \Exception
	 */
	public function save() {
		if ( $this->isReady() ) {
			$aToWrite = [];
			foreach ( $this->getSnapData() as $sFile => $sHash ) {
				$aToWrite[] = sprintf( '%s%s%s', $sFile, self::SEPARATOR, $sHash );
			}
			Services::WpFs()->putFileContent(
				$this->getSnapStorePath(),
				implode( "\n", $aToWrite ),
				true
			);
			Services::WpFs()->putFileContent(
				$this->getSnapStoreMetaPath(),
				json_encode( $this->getSnapMeta() ),
				true
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
		$sDir = dirname( $this->getSnapStorePath() );

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
	private function isSnapStoreRelevant() {
		$bRelevant = true;
		$oFs = Services::WpFs();
		$mTime = Services::Request()->ts() - $oFs->getModifiedTime( $this->getSnapStorePath() );
		if ( $mTime > DAY_IN_SECONDS ) {
			$bRelevant = false;
		}
		elseif ( $mTime > DAY_IN_SECONDS/2 ) {
			$oFs->touch( $this->getSnapStorePath() );
		}
		return $bRelevant;
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
	 * @param array $aMeta
	 * @return $this
	 */
	public function setSnapMeta( $aMeta ) {
		$this->aSnapMeta = $aMeta;
		return $this;
	}
}