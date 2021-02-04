<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots;

use FernleafSystems\Wordpress\Services\Core\VOs\WpPluginVo;
use FernleafSystems\Wordpress\Services\Core\VOs\WpThemeVo;
use FernleafSystems\Wordpress\Services\Services;

class Store {

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
	 * @var string
	 */
	protected $sWorkingDir;

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
	public function getSnapStorePath() {
		return $this->getBaseSnapPath().'.txt';
	}

	/**
	 * @return string
	 */
	public function getSnapStoreMetaPath() {
		return $this->getBaseSnapPath().'_meta'.'.txt';
	}

	/**
	 * @return string
	 */
	private function getBaseSnapPath() {
		return path_join( $this->getWorkingDir(), path_join( $this->getContext(), $this->getSlug() ) );
	}

	/**
	 * @return string
	 */
	public function getWorkingDir() {
		return $this->sWorkingDir;
	}

	/**
	 * @return string
	 */
	protected function getSlug() {
		$oAs = $this->getAsset();
		return ( $oAs instanceof WpPluginVo ) ? dirname( $oAs->file ) : $oAs->stylesheet;
	}

	/**
	 * @return string[]
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
	 * @return $this
	 */
	private function loadSnapDataIfExists() {
		try {
			$this->aSnapData = $this->readSnapData();
		}
		catch ( \Exception $e ) {
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
		catch ( \Exception $e ) {
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

	/**
	 * @param string $sDir
	 * @return $this
	 */
	public function setWorkingDir( $sDir ) {
		$this->sWorkingDir = $sDir;
		return $this;
	}
}