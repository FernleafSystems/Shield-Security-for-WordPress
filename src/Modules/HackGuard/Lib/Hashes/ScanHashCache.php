<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\ScansStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\CrowdSourcedHashes\Query;

/**
 * @phpstan-type ScanHashData array{meta:array{type:string,key:string,version:string,source:'crowd_sourced'|'snapshot',trusted:bool,created_at:int},hashes:array<string,list<string>>}
 */
class ScanHashCache {

	use PluginControllerConsumer;

	private const DIRECTORY = 'scan-hashes';
	private const MAX_REQUEST_FAILURES = 2;
	private const MAX_REQUEST_SECONDS = 30;

	private int $requestFailures = 0;
	private float $requestSeconds = 0;

	/** @var array<string,ScanHashData|null> */
	private static array $files = [];

	public static function resetMemoization() :void {
		self::$files = [];
	}

	/**
	 * Preflight only. A cache failure must not affect snapshot eligibility or the scan.
	 * @param WpPluginVo|WpThemeVo $asset
	 */
	public function fill( $asset ) :void {
		$error = null;
		try {
			if ( !\is_main_network() || !\is_main_site() || !self::con()->caps->canScanPluginsThemesLocal() ) {
				return;
			}
			$context = new AssetFileContext( $asset->asset_type, $asset->unique_id, $asset->Version, '' );
			$dir = self::con()->cache_dir_handler->buildSubDir( self::DIRECTORY );
			if ( $dir === '' ) {
				throw new \RuntimeException( 'Scan hash cache directory is unavailable.' );
			}

			$hashes = [];
			if ( $this->requestFailures < self::MAX_REQUEST_FAILURES && $this->requestSeconds < self::MAX_REQUEST_SECONDS ) {
				$started = $this->requestTime();
				try {
					$hashes = $this->fetchHashes( $asset );
					$this->requestFailures = 0;
				}
				catch ( \Throwable $e ) {
					$this->requestFailures++;
					$error = $e;
				}
				finally {
					$this->requestSeconds += $this->requestTime() - $started;
				}
			}
			$source = 'crowd_sourced';
			$trusted = true;
			if ( empty( $hashes ) ) {
				$snapshot = ( new Retrieve() )->byVOFromStoredSnapshot( $asset );
				if ( $snapshot === null ) {
					throw new \RuntimeException( 'No usable stored snapshot for scan hash cache.', 0, $error );
				}
				$hashes = $snapshot[ 'hashes' ];
				$source = 'snapshot';
				$trusted = $snapshot[ 'trusted_source' ];
			}

			$this->write( $dir, $context, $hashes, $source, $trusted );
		}
		catch ( \Throwable $e ) {
			$error = $e;
		}
		finally {
			if ( $error !== null ) {
				error_log( \sprintf(
					'Shield scan hash cache fill failed: type=%s key=%s version=%s message=%s',
					$asset->asset_type,
					$asset->unique_id,
					$asset->Version,
					\substr( (string)\preg_replace( '#\s+#', ' ', $error->getMessage() ), 0, 300 )
				) );
			}
		}
	}

	protected function requestTime() :float {
		return \hrtime( true )/1e9;
	}

	/**
	 * The existing client collapses transport failures into empty hash results.
	 * Observe only this lookup's HTTP responses to distinguish errors from normal misses.
	 * @param WpPluginVo|WpThemeVo $asset
	 * @return array<string,list<string>>
	 */
	private function fetchHashes( $asset ) :array {
		$error = null;
		$inspect = static function ( $response, string $url ) use ( &$error ) :void {
			if ( \strpos( $url, '/cshashes/' ) !== false ) {
				if ( is_wp_error( $response ) ) {
					$error = new \RuntimeException( $response->get_error_message() );
				}
				elseif ( \is_array( $response ) && (int)( $response[ 'response' ][ 'code' ] ?? 0 ) >= 400 ) {
					$error = new \RuntimeException( 'Crowd-sourced hashes request returned HTTP '.$response[ 'response' ][ 'code' ].'.' );
				}
			}
		};
		$preRequest = static function ( $response, array $args, string $url ) use ( $inspect ) {
			$inspect( $response, $url );
			return $response;
		};
		$debug = static function ( $response, string $context, string $transport, array $args, string $url ) use ( $inspect ) :void {
			$inspect( $response, $url );
		};
		\add_filter( 'pre_http_request', $preRequest, \PHP_INT_MAX, 3 );
		\add_action( 'http_api_debug', $debug, 10, 5 );
		try {
			$hashes = ( $asset instanceof WpPluginVo ? new Query\Plugin() : new Query\Theme() )->getHashesFromVO( $asset );
			if ( $error !== null ) {
				throw $error;
			}
			return ( new NormalizeHashMap() )->run( $hashes );
		}
		finally {
			\remove_filter( 'pre_http_request', $preRequest, \PHP_INT_MAX );
			\remove_action( 'http_api_debug', $debug, 10 );
		}
	}

	/** @return ScanHashData|null */
	public function read( AssetFileContext $context ) :?array {
		try {
			if ( !\is_main_network() || !\is_main_site() ) {
				return null;
			}
			$dir = $this->existingDirectory();
			if ( $dir === '' ) {
				return null;
			}
			$path = path_join( $dir, $this->filename( $context ) );
			$cacheKey = \implode( "\0", [ $path, $context->assetType, $context->assetKey, $context->assetVersion ] );
			if ( !\array_key_exists( $cacheKey, self::$files ) ) {
				self::$files[ $cacheKey ] = null;
				$contents = Services::WpFs()->getFileContent( $path );
				if ( \is_string( $contents ) ) {
					$data = \json_decode( $contents, true );
					if ( $this->isValid( $data, $context ) ) {
						self::$files[ $cacheKey ] = $data;
					}
				}
			}
			return self::$files[ $cacheKey ];
		}
		catch ( \Throwable $e ) {
			return null;
		}
	}

	public function emptyDirectory( int $scanID ) :void {
		self::resetMemoization();
		try {
			if ( !\is_main_network() || !\is_main_site() ) {
				return;
			}
			foreach ( ( new ScansStatus() )->activeScans() as $scan ) {
				if ( $scan[ 'scan' ] === 'afs' && $scan[ 'id' ] !== $scanID ) {
					return;
				}
			}
			$dir = $this->existingDirectory();
			if ( $dir !== '' ) {
				Services::WpFs()->emptyDir( $dir );
			}
		}
		catch ( \Throwable $e ) {
			// Optional scan references never prevent initialisation.
		}
	}

	public function deleteDirectory() :void {
		self::resetMemoization();
		try {
			if ( \is_main_network() && \is_main_site() ) {
				$dir = $this->existingDirectory();
				if ( $dir !== '' ) {
					Services::WpFs()->deleteDir( $dir );
				}
			}
		}
		catch ( \Throwable $e ) {
			// Hourly maintenance will attempt abandoned-cache cleanup again.
		}
	}

	private function existingDirectory() :string {
		$root = self::con()->cache_dir_handler->locateExistingDir();
		$dir = $root === '' ? '' : path_join( $root, self::DIRECTORY );
		return $dir !== '' && Services::WpFs()->isDir( $dir ) ? $dir : '';
	}

	private function filename( AssetFileContext $context ) :string {
		return $context->assetType.'-'.\substr( \sha1( $context->assetKey ), 0, 16 ).'-'
			.(string)\preg_replace( '#[^A-Za-z0-9._-]#', '_', $context->assetVersion ).'.json';
	}

	/**
	 * @param array<string,list<string>> $hashes
	 * @param 'crowd_sourced'|'snapshot' $source
	 */
	private function write( string $dir, AssetFileContext $context, array $hashes, string $source, bool $trusted ) :void {
		$json = \json_encode( [
			'meta'   => [
				'type'       => $context->assetType,
				'key'        => $context->assetKey,
				'version'    => $context->assetVersion,
				'source'     => $source,
				'trusted'    => $trusted,
				'created_at' => Services::Request()->ts(),
			],
			'hashes' => $hashes,
		], \JSON_THROW_ON_ERROR );
		$tmp = @\tempnam( $dir, 'hash-' );
		if ( $tmp === false ) {
			throw new \RuntimeException( 'Could not create scan hash cache temporary file.' );
		}
		try {
			if ( wp_normalize_path( \dirname( $tmp ) ) !== wp_normalize_path( \rtrim( $dir, '/\\' ) )
				 || !Services::WpFs()->putFileContent( $tmp, $json )
				 || !@\rename( $tmp, path_join( $dir, $this->filename( $context ) ) ) ) {
				throw new \RuntimeException( 'Could not write scan hash cache file.' );
			}
			self::resetMemoization();
		}
		finally {
			if ( \is_file( $tmp ) ) {
				Services::WpFs()->deleteFile( $tmp );
			}
		}
	}

	/** @param mixed $data */
	private function isValid( $data, AssetFileContext $context ) :bool {
		if ( !\is_array( $data ) || !\is_array( $data[ 'meta' ] ?? null ) ) {
			return false;
		}
		$meta = $data[ 'meta' ];
		$hashes = $data[ 'hashes' ] ?? null;
		if ( !\in_array( $context->assetType, [ 'plugin', 'theme' ], true )
			 || ( $meta[ 'type' ] ?? null ) !== $context->assetType
			 || ( $meta[ 'key' ] ?? null ) !== $context->assetKey
			 || ( $meta[ 'version' ] ?? null ) !== $context->assetVersion
			 || !\in_array( $meta[ 'source' ] ?? null, [ 'crowd_sourced', 'snapshot' ], true )
			 || !\is_bool( $meta[ 'trusted' ] ?? null )
			 || ( $meta[ 'source' ] === 'crowd_sourced' && !$meta[ 'trusted' ] )
			 || !\is_int( $meta[ 'created_at' ] ?? null )
			 || !\is_array( $hashes ) || empty( $hashes ) ) {
			return false;
		}
		return ( new NormalizeHashMap() )->run( $hashes ) === $hashes;
	}
}
