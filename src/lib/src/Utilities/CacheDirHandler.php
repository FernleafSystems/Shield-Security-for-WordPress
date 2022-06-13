<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\AssessDirWrite;
use FernleafSystems\Wordpress\Services\Services;

class CacheDirHandler {

	use PluginControllerConsumer;

	private $cacheDir;

	private $lastKnownBaseDir;

	public function __construct( string $lastKnownBaseDir = '' ) {
		$this->lastKnownBaseDir = $lastKnownBaseDir;
	}

	public function exists() :bool {
		$dir = $this->dir();
		return !empty( $dir ) && Services::WpFs()->isDir( $dir );
	}

	public function findWorkableDir( bool $retest = false ) :string {
		$FS = Services::WpFs();

		if ( !isset( $this->cacheDir ) || $retest ) {

			$this->cacheDir = '';
			foreach ( $this->getBaseDirCandidates() as $baseDir ) {
				$maybeDir = path_join( $baseDir, $this->getCon()->cfg->paths[ 'cache' ] );
				try {
					if ( $maybeDir === '/' ) {
						throw new \Exception( 'Should never have the root as the cache dir.' ); // just to be ultra safe.
					}
					if ( !$FS->mkdir( $maybeDir ) || !$FS->isDir( $maybeDir ) ) {
						throw new \Exception( sprintf( 'Failed to mkdir cache dir: %s', $maybeDir ) );
					}

					$assessedFlag = path_join( $maybeDir, 'assessed.flag' );
					if ( !$FS->isFile( $assessedFlag )
						 || Services::Request()->ts() - $FS->getModifiedTime( $assessedFlag ) > HOUR_IN_SECONDS ) {

						$assess = ( new AssessDirWrite( $maybeDir ) )->test();
						if ( count( array_filter( $assess ) ) !== 3 ) {
							throw new \Exception( sprintf( 'Failed writeable assessment for cache dir: "%s"; Results: %s ',
								$maybeDir, var_export( $assess, true ) ) );
						}

						if ( $maybeDir !== '/tmp' ) {
							$this->addProtections( $maybeDir );
						}

						$FS->touch( path_join( $maybeDir, 'assessed.flag' ) );
					}

					$this->cacheDir = $maybeDir;
					break;
				}
				catch ( \Exception $e ) {
				}
			}
		}

		return $this->cacheDir;
	}

	public function dir() :string {
		return $this->findWorkableDir();
	}

	public function buildSubDir( string $subDir ) :string {
		$finalDir = '';
		if ( $this->exists() ) {
			$finalDir = path_join( $this->dir(), $subDir );
			if ( !Services::WpFs()->mkdir( $finalDir ) ) {
				$finalDir = '';
			}
		}
		return $finalDir;
	}

	public function cacheItemPath( string $itemPath ) :string {
		$rootDir = $this->dir();
		if ( empty( $rootDir ) ) {
			$path = '';
		}
		elseif ( empty( $itemPath ) ) {
			$path = $rootDir;
		}
		else {
			$path = path_join( $rootDir, $itemPath );
		}
		return $path;
	}

	/**
	 * @throws \Exception
	 */
	private function addProtections( string $cacheDir ) :bool {
		$FS = Services::WpFs();

		$htFile = path_join( $cacheDir, '.htaccess' );
		$htContent = implode( "\n", [
			"# BEGIN SHIELD",
			"Options -Indexes",
			"Order allow,deny",
			"Deny from all",
			'<FilesMatch "^.*\.(css|js)$">',
			" Allow from all",
			'</FilesMatch>',
			"# END SHIELD"
		] );
		if ( !$FS->exists( $htFile ) || ( md5_file( $htFile ) !== md5( $htContent ) ) ) {
			$FS->putFileContent( $htFile, $htContent );
		}
		$index = path_join( $cacheDir, 'index.php' );
		$indexContent = "<?php\nhttp_response_code(404);";
		if ( !$FS->exists( $index ) || ( md5_file( $index ) !== md5( $indexContent ) ) ) {
			$FS->putFileContent( $index, $indexContent );
		}

		$FS->putFileContent( path_join( $cacheDir, 'README.txt' ),
			"This is a temporary caching folder used by the Shield Security plugin. You can safely delete it, but it'll be recreated if required.\n" );

		return true;
	}

	private function getBaseDirCandidates() :array {
		$candidates = [
			WP_CONTENT_DIR,
			path_join( ABSPATH, 'wp-content' ),
			path_join( WP_CONTENT_DIR, 'tmp' ),
			path_join( WP_CONTENT_DIR, 'cache' ),
			path_join( WP_CONTENT_DIR, 'uploads' ),
			'/tmp'
		];

		if ( !empty( $this->lastKnownBaseDir ) ) {
			array_unshift( $candidates, $this->lastKnownBaseDir );
		}

		return array_filter(
			array_unique( array_map(
				function ( $path ) {
					return wp_normalize_path( $path );
				},
				$candidates
			) ),
			function ( $path ) {
				return Services::WpFs()->isDir( $path ) && wp_is_writable( $path );
			}
		);
	}

	/**
	 * @deprecated 15.1
	 */
	public function dirExists() :bool {
		return $this->exists();
	}

	/**
	 * @deprecated 15.1
	 */
	public function build() :string {
		return $this->dir();
	}
}