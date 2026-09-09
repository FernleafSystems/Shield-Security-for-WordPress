<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\NormalizeHashMap;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};
use FernleafSystems\Wordpress\Services\Services;

class Store {

	public const SEPARATOR = '=::=';

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

	private bool $includeVersion;

	/**
	 * @param WpPluginVo|WpThemeVo $asset
	 */
	public function __construct( $asset, bool $includeVersion = true ) {
		$this->asset = $asset;
		$this->includeVersion = $includeVersion;
	}

	/**
	 * @return WpPluginVo|WpThemeVo
	 */
	public function getAsset() {
		return $this->asset;
	}

	public function getContext() :string {
		return ( $this->asset->asset_type === 'plugin' ) ? 'plugins' : 'themes';
	}

	public function getSnapStorePath() :string {
		return $this->getBaseSnapPath().'.txt';
	}

	public function getSnapStoreMetaPath() :string {
		return $this->getBaseSnapPath().'_meta'.'.txt';
	}

	public function getBaseSnapPath() :string {
		$path = path_join( $this->getWorkingDir(), path_join( $this->getContext(), $this->getSlug() ) );
		if ( !empty( $path ) && $this->includeVersion ) {
			$version = $this->asset->Version;
			if ( !empty( $version ) ) {
				$path .= '-'.$version;
			}
		}
		return $path;
	}

	public function getWorkingDir() :string {
		return $this->workingDir;
	}

	protected function getSlug() :string {
		if ( $this->asset->asset_type === 'plugin' ) {
			$slug = \dirname( $this->asset->file );
			if ( $slug === '.' || empty( $slug ) ) {
				$slug = $this->asset->file;
			}
		}
		else {
			$slug = $this->asset->stylesheet;
		}
		return empty( $slug ) ? '' : $slug;
	}

	/**
	 * @return string[]
	 */
	public function getSnapData() :array {
		if ( !\is_array( $this->snapData ) ) {
			try {
				$this->snapData = $this->readSnapData();
			}
			catch ( \Exception $e ) {
				$this->snapData = [];
			}
		}
		return \is_array( $this->snapData ) ? $this->snapData : [];
	}

	public function getSnapMeta() :array {
		if ( empty( $this->snapMeta ) ) {
			try {
				$this->snapMeta = $this->readSnapMeta();
			}
			catch ( \Exception $e ) {
				$this->snapMeta = [];
			}
		}
		return \is_array( $this->snapMeta ) ? $this->snapMeta : [];
	}

	public function verify() :bool {
		return $this->verifyMeta( $this->getSnapMeta() );
	}

	public function isUsable() :bool {
		return $this->getUsableSnapshot() !== null;
	}

	/**
	 * @return array{meta:array,data:array<string,string>}|null
	 */
	public function getUsableSnapshot() :?array {
		try {
			$meta = $this->readSnapMetaStrict();
			if ( !$this->verifyMeta( $meta ) ) {
				return null;
			}

			$data = $this->readSnapDataStrict();
			if ( empty( $data ) || $data !== ( new NormalizeHashMap() )->toScalarMap( $data ) ) {
				return null;
			}

			$this->snapMeta = $meta;
			$this->snapData = $data;

			return [
				'meta' => $meta,
				'data' => $data,
			];
		}
		catch ( \Throwable $e ) {
			return null;
		}
	}

	/**
	 * @throws \Exception
	 */
	private function readSnapData() :array {
		$FS = Services::WpFs();
		$snap = [];

		if ( $this->isReady() && !$this->getSnapStoreExists() ) {
			throw new \Exception( sprintf( __( "Snapshot store does not exist: '%s'", 'wp-simple-firewall' ), $this->getSnapStorePath() ) );
		}

		$encoded = $FS->getFileContent( $this->getSnapStorePath(), true );
		if ( \is_string( $encoded ) && $encoded !== '' ) {
			foreach ( \array_map( '\trim', \explode( "\n", $encoded ) ) as $line ) {
				if ( $line === '' || \strpos( $line, self::SEPARATOR ) === false ) {
					continue;
				}
				[ $file, $hash ] = \explode( self::SEPARATOR, $line, 2 );
				if ( $file === '' || $hash === '' ) {
					continue;
				}
				$snap[ $file ] = $hash;
			}
		}
		if ( empty( $snap ) ) {
			throw new \Exception( __( 'Snapshot data could not be decoded.', 'wp-simple-firewall' ) );
		}

		return $snap;
	}

	/**
	 * @return array<string,string>
	 * @throws \Exception
	 */
	private function readSnapDataStrict() :array {
		$FS = Services::WpFs();
		if ( !$FS->exists( $this->getSnapStorePath() ) ) {
			throw new \Exception( __( 'Snapshot store does not exist.', 'wp-simple-firewall' ) );
		}

		$encoded = $FS->getFileContent( $this->getSnapStorePath(), true );
		if ( !\is_string( $encoded ) || trim( $encoded ) === '' ) {
			throw new \Exception( __( 'Snapshot data could not be decoded.', 'wp-simple-firewall' ) );
		}

		$snap = [];
		foreach ( \explode( "\n", $encoded ) as $line ) {
			$line = \rtrim( $line, "\r" );
			if ( trim( $line ) === '' ) {
				continue;
			}
			if ( \substr_count( $line, self::SEPARATOR ) !== 1 ) {
				throw new \Exception( __( 'Snapshot data could not be decoded.', 'wp-simple-firewall' ) );
			}

			[ $file, $hash ] = \explode( self::SEPARATOR, $line, 2 );
			if ( $file === '' || $hash === '' || \array_key_exists( $file, $snap ) ) {
				throw new \Exception( __( 'Snapshot data could not be decoded.', 'wp-simple-firewall' ) );
			}
			$snap[ $file ] = $hash;
		}

		if ( empty( $snap ) ) {
			throw new \Exception( __( 'Snapshot data could not be decoded.', 'wp-simple-firewall' ) );
		}

		return $snap;
	}

	/**
	 * @throws \Exception
	 */
	private function readSnapMetaStrict() :array {
		$FS = Services::WpFs();
		if ( !$FS->exists( $this->getSnapStoreMetaPath() ) ) {
			throw new \Exception( __( 'Snapshot metadata does not exist.', 'wp-simple-firewall' ) );
		}

		$encoded = $FS->getFileContent( $this->getSnapStoreMetaPath(), true );
		$meta = \is_string( $encoded ) ? \json_decode( $encoded, true ) : null;
		if ( !\is_array( $meta ) || empty( $meta ) ) {
			throw new \Exception( __( 'Snapshot metadata could not be decoded.', 'wp-simple-firewall' ) );
		}
		return $meta;
	}

	private function verifyMeta( array $meta ) :bool {
		$asset = $this->getAsset();
		return !empty( $meta )
			   && ( $meta[ 'version' ] ?? null ) === $asset->Version
			   && ( $meta[ 'unique_id' ] ?? null ) ===
				  ( $asset->asset_type === 'plugin' ? $asset->file : $asset->stylesheet );
	}

	/**
	 * @throws \Exception
	 */
	private function readSnapMeta() :array {
		$FS = Services::WpFs();

		if ( $this->isReady() && !$this->getSnapStoreExists() ) {
			throw new \Exception( sprintf( __( "Snapshot store does not exist: '%s'", 'wp-simple-firewall' ), $this->getSnapStorePath() ) );
		}

		$encoded = $FS->getFileContent( $this->getSnapStoreMetaPath(), true );
		if ( !empty( $encoded ) ) {
			$data = \json_decode( $encoded, true );
		}
		if ( empty( $data ) || !\is_array( $data ) ) {
			throw new \Exception( __( 'Snapshot data could not be decoded.', 'wp-simple-firewall' ) );
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
				\implode( "\n", $toWrite ),
				true
			);
			$this->saveMeta();
		}
		return $this;
	}

	/**
	 * @throws \Exception
	 */
	public function saveMeta() :bool {
		return $this->isReady() &&
			   Services::WpFs()->putFileContent(
				   $this->getSnapStoreMetaPath(),
				   \wp_json_encode( $this->getSnapMeta() ),
				   true
			   );
	}

	/**
	 * @throws \Exception
	 */
	protected function isReady() :bool {
		$FS = Services::WpFs();
		$dir = \dirname( $this->getSnapStorePath() );

		if ( \strlen( $this->getContext() ) < 1 ) {
			throw new \Exception( __( 'Context has not been specified.', 'wp-simple-firewall' ) );
		}
		if ( !$FS->mkdir( $dir ) ) {
			throw new \Exception( sprintf( __( 'Store directory could not be created: %s', 'wp-simple-firewall' ), $dir ) );
		}
		if ( !$FS->exists( $dir ) ) {
			throw new \Exception( sprintf( __( 'Store directory path does not exist: %s', 'wp-simple-firewall' ), $dir ) );
		}
		return true;
	}

	public function getSnapStoreExists() :bool {
		return Services::WpFs()->exists( $this->getSnapStorePath() ) && $this->isSnapStoreRelevant();
	}

	/**
	 * We try to capture periods wherein which the plugin may have been deactivated and tracking has paused.
	 */
	private function isSnapStoreRelevant() :bool {
		$relevant = true;
		$FS = Services::WpFs();
		$mTime = Services::Request()->ts() - $FS->getModifiedTime( $this->getSnapStorePath() );
		if ( $mTime > \DAY_IN_SECONDS ) {
			$relevant = false;
		}
		elseif ( $mTime > \DAY_IN_SECONDS/2 ) {
			$FS->touch( $this->getSnapStorePath() );
		}
		return $relevant;
	}

	/**
	 * @return $this
	 */
	public function setSnapData( array $data ) {
		$this->snapData = $data;
		return $this;
	}

	/**
	 * @return $this
	 */
	public function setSnapMeta( array $meta ) {
		$this->snapMeta = $meta;
		return $this;
	}

	/**
	 * @return $this
	 */
	public function setWorkingDir( string $dir ) {
		$this->workingDir = $dir;
		return $this;
	}
}
