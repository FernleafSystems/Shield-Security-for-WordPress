<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots;

use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};
use FernleafSystems\Wordpress\Services\Services;

class Store {

	const SEPARATOR = '=::=';

	/**
	 * @var array
	 */
	private $snapMeta;

	/**
	 * @var array
	 */
	private $snapData;

	/**
	 * @var WpPluginVo|WpThemeVo
	 */
	private $asset;

	/**
	 * @var string
	 */
	protected $workingDir;

	/**
	 * @param WpPluginVo|WpThemeVo $asset
	 */
	public function __construct( $asset ) {
		$this->asset = $asset;
	}

	/**
	 * @return WpPluginVo|WpThemeVo
	 */
	public function getAsset() {
		return $this->asset;
	}

	public function getContext() :string {
		return ( $this->asset instanceof WpPluginVo ) ? 'plugins' : 'themes';
	}

	public function getSnapStorePath() :string {
		return $this->getBaseSnapPath().'.txt';
	}

	public function getSnapStoreMetaPath() :string {
		return $this->getBaseSnapPath().'_meta'.'.txt';
	}

	private function getBaseSnapPath() :string {
		return path_join( $this->getWorkingDir(), path_join( $this->getContext(), $this->getSlug() ) );
	}

	public function getWorkingDir() :string {
		return $this->workingDir;
	}

	protected function getSlug() :string {
		return ( $this->asset instanceof WpPluginVo ) ? dirname( $this->asset->file ) : $this->asset->stylesheet;
	}

	/**
	 * @return string[]
	 */
	public function getSnapData() :array {
		if ( !is_array( $this->snapData ) ) {
			$this->loadSnapDataIfExists();
		}
		return is_array( $this->snapData ) ? $this->snapData : [];
	}

	public function getSnapMeta() :array {
		if ( empty( $this->snapMeta ) ) {
			$this->loadSnapMetaIfExists();
		}
		return is_array( $this->snapMeta ) ? $this->snapMeta : [];
	}

	/**
	 * @return $this
	 */
	private function loadSnapDataIfExists() {
		try {
			$this->snapData = $this->readSnapData();
		}
		catch ( \Exception $e ) {
			$this->snapData = [];
		}
		return $this;
	}

	/**
	 * @return $this
	 */
	private function loadSnapMetaIfExists() {
		try {
			$this->snapMeta = $this->readSnapMeta();
		}
		catch ( \Exception $e ) {
			$this->snapMeta = [];
		}
		return $this;
	}

	/**
	 * @throws \Exception
	 */
	private function readSnapData() :array {
		$FS = Services::WpFs();

		if ( $this->isReady() && !$this->getSnapStoreExists() ) {
			throw new \Exception( sprintf( 'Snapshot store does not exist: "%s"', $this->getSnapStorePath() ) );
		}

		$encoded = $FS->getFileContent( $this->getSnapStorePath(), true );
		if ( !empty( $encoded ) ) {
			$snap = [];
			foreach ( array_map( 'trim', explode( "\n", $encoded ) ) as $line ) {
				list( $file, $hash ) = explode( self::SEPARATOR, $line, 2 );
				$snap[ $file ] = $hash;
			}
		}
		if ( empty( $snap ) ) {
			throw new \Exception( 'Snapshot data could not be decoded' );
		}

		return $snap;
	}

	/**
	 * @throws \Exception
	 */
	private function readSnapMeta() {
		$FS = Services::WpFs();

		if ( $this->isReady() && !$this->getSnapStoreExists() ) {
			throw new \Exception( sprintf( 'Snapshot store does not exist: "%s"', $this->getSnapStorePath() ) );
		}

		$encoded = $FS->getFileContent( $this->getSnapStoreMetaPath(), true );
		if ( !empty( $encoded ) ) {
			$data = json_decode( $encoded, true );
		}
		if ( empty( $data ) || !is_array( $data ) ) {
			throw new \Exception( 'Snapshot data could not be decoded' );
		}

		return $data;
	}

	/**
	 * @return $this
	 * @throws \Exception
	 */
	public function save() {
		if ( $this->isReady() ) {
			$toWrite = [];
			foreach ( $this->getSnapData() as $file => $hash ) {
				$toWrite[] = sprintf( '%s%s%s', $file, self::SEPARATOR, $hash );
			}
			Services::WpFs()->putFileContent(
				$this->getSnapStorePath(),
				implode( "\n", $toWrite ),
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
	protected function isReady() :bool {
		$FS = Services::WpFs();
		$dir = dirname( $this->getSnapStorePath() );

		if ( strlen( $this->getContext() ) < 1 ) {
			throw new \Exception( 'Context has not been specified' );
		}
		if ( !$FS->mkdir( $dir ) ) {
			throw new \Exception( sprintf( 'Store directory could not be created: %s', $dir ) );
		}
		if ( !$FS->exists( $dir ) ) {
			throw new \Exception( sprintf( 'Store directory path does not exist: %s', $dir ) );
		}
		return true;
	}

	public function getSnapStoreExists() :bool {
		return Services::WpFs()->exists( $this->getSnapStorePath() ) && $this->isSnapStoreRelevant();
	}

	/**
	 * We try to capture periods wherein which the plugin may have been deactivated and tracking has paused.
	 * @return bool
	 */
	private function isSnapStoreRelevant() :bool {
		$relevant = true;
		$FS = Services::WpFs();
		$mTime = Services::Request()->ts() - $FS->getModifiedTime( $this->getSnapStorePath() );
		if ( $mTime > DAY_IN_SECONDS ) {
			$relevant = false;
		}
		elseif ( $mTime > DAY_IN_SECONDS/2 ) {
			$FS->touch( $this->getSnapStorePath() );
		}
		return $relevant;
	}

	/**
	 * @param array $data
	 * @return $this
	 */
	public function setSnapData( array $data ) {
		$this->snapData = $data;
		return $this;
	}

	/**
	 * @param array $meta
	 * @return $this
	 */
	public function setSnapMeta( array $meta ) {
		$this->snapMeta = $meta;
		return $this;
	}

	/**
	 * @param string $dir
	 * @return $this
	 */
	public function setWorkingDir( string $dir ) {
		$this->workingDir = $dir;
		return $this;
	}
}