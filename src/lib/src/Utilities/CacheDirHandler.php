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

	public function dir( bool $retest = false ) :string {
		$FS = Services::WpFs();

		if ( !isset( $this->cacheDir ) || $retest ) {

			$this->cacheDir = '';

			foreach ( $this->getCandidates() as $maybeDir ) {
				if ( $this->testDir( $maybeDir ) ) {
					$this->cacheDir = $maybeDir;
					if ( !\str_starts_with( $maybeDir, '/tmp' ) ) {
						$this->addProtections( $maybeDir );
					}
					break;
				}
				elseif ( $FS->isDir( $maybeDir ) ) {
					$FS->deleteDir( $maybeDir );
				}
			}
		}

		return $this->cacheDir;
	}

	public function exists() :bool {
		$dir = $this->dir();
		return !empty( $dir ) && Services::WpFs()->isDir( $dir );
	}

	private function testDir( string $dir ) :bool {
		$FS = Services::WpFs();
		try {
			if ( !$FS->mkdir( $dir ) || !$FS->isDir( $dir ) ) {
				throw new \Exception( sprintf( 'Failed to mkdir cache dir: %s', $dir ) );
			}

			$flag = path_join( $dir, 'assessed.flag' );
			if ( !$FS->isAccessibleFile( $flag )
				 || Services::Request()->ts() - $FS->getModifiedTime( $flag ) > \HOUR_IN_SECONDS ) {

				$assess = ( new AssessDirWrite( $dir ) )->test();
				if ( \count( \array_filter( $assess ) ) !== 3 ) {
					throw new \Exception( sprintf( 'Failed writeable assessment for cache dir: "%s"; Results: %s ',
						$dir, var_export( $assess, true ) ) );
				}

				$FS->touch( $flag );
			}

			$testSuccess = true;
		}
		catch ( \Exception $e ) {
			$testSuccess = false;
		}
		return $testSuccess;
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

	private function addProtections( string $cacheDir ) :bool {
		$FS = Services::WpFs();

		$htFile = path_join( $cacheDir, '.htaccess' );
		$htContent = \implode( "\n", [
			"# BEGIN SHIELD",
			"Options -Indexes",
			"Order allow,deny",
			"Deny from all",
			'<FilesMatch "^.*\.(css|js)$">',
			" Allow from all",
			'</FilesMatch>',
			"# END SHIELD"
		] );
		if ( !$FS->exists( $htFile ) || ( \md5_file( $htFile ) !== \md5( $htContent ) ) ) {
			$FS->putFileContent( $htFile, $htContent );
		}
		$index = path_join( $cacheDir, 'index.php' );
		$indexContent = "<?php\n\http_response_code(404);";
		if ( !$FS->exists( $index ) || ( \md5_file( $index ) !== \md5( $indexContent ) ) ) {
			$FS->putFileContent( $index, $indexContent );
		}

		$FS->putFileContent( path_join( $cacheDir, 'README.txt' ),
			"This is a temporary caching folder used by the Shield Security plugin. You can safely delete it, but it'll be recreated if required.\n" );

		return true;
	}

	private function getCandidates() :array {
		$candidates = [];
		$cacheBasename = (string)( self::con()->cfg->paths[ 'cache' ] ?? '' );
		if ( \preg_match( '#^[a-z]+$#i', $cacheBasename ) ) {
			$candidates = \array_values( \array_filter(
				\array_map( function ( string $baseDir ) use ( $cacheBasename ) {
					return untrailingslashit( wp_normalize_path( path_join( $baseDir, $cacheBasename ) ) );
				}, $this->getBaseDirCandidates() ),
				function ( string $dir ) use ( $cacheBasename ) {
					return !empty( $dir ) && \str_ends_with( $dir, $cacheBasename );
				}
			) );
		}
		return $candidates;
	}

	private function getBaseDirCandidates() :array {
		return \array_filter(
			\array_unique( \array_map(
				function ( $path ) {
					return wp_normalize_path( $path );
				},
				\array_filter( [
					$this->lastKnownBaseDir,
					WP_CONTENT_DIR,
					path_join( ABSPATH, 'wp-content' ),
					path_join( WP_CONTENT_DIR, 'uploads' ),
					path_join( WP_CONTENT_DIR, 'cache' ),
					path_join( WP_CONTENT_DIR, 'tmp' ),
					get_temp_dir(),
					'/tmp'
				] )
			) ),
			function ( $path ) {
				return Services::WpFs()->isAccessibleDir( $path ) && wp_is_writable( $path );
			}
		);
	}
}