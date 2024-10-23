<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\AssessDirWrite;

class CacheDirHandler {

	use PluginControllerConsumer;

	private $cacheDir;

	private $lastKnownBaseDir;

	private $preferredDir;

	public function __construct( string $lastKnownBaseDir = '', string $preferredDir = '' ) {
		$this->lastKnownBaseDir = $lastKnownBaseDir;
		$this->preferredDir = $preferredDir;
	}

	public function dir( bool $retest = false ) :string {
		if ( !isset( $this->cacheDir ) || $retest ) {
			$this->cacheDir = '';

			$dir = $this->assessCandidates( $this->buildCandidates( $this->getBaseDirCandidates() ) );
			if ( empty( $dir ) && empty( \ini_get( 'open_basedir' ) ) ) {
				$dir = $this->assessCandidates( $this->buildCandidates( [ '/tmp' ] ) );
			}

			if ( !empty( $dir ) ) {
				$this->cacheDir = $dir;
			}
		}
		return $this->cacheDir;
	}

	private function assessCandidates( array $candidates ) :?string {
		$FS = Services::WpFs();
		$chosenDir = null;
		foreach ( $candidates as $maybeDir ) {
			if ( $this->testDir( $maybeDir ) ) {
				$chosenDir = $maybeDir;
				if ( !\str_starts_with( $maybeDir, '/tmp' ) ) {
					$this->addProtections( $maybeDir );
				}
				break;
			}
			elseif ( $FS->isDir( $maybeDir ) ) {
				$FS->deleteDir( $maybeDir );
			}
		}
		return $chosenDir;
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
		if ( !$FS->exists( $htFile ) || !\hash_equals( \hash( 'sha256', $htContent ), \hash_file( 'sha256', $htFile ) ) ) {
			$FS->putFileContent( $htFile, $htContent );
		}
		$index = path_join( $cacheDir, 'index.php' );
		$indexContent = "<?php\n\http_response_code(404);";
		if ( !$FS->exists( $index ) || !\hash_equals( \hash( 'sha256', $indexContent ), \hash_file( 'sha256', $index ) ) ) {
			$FS->putFileContent( $index, $indexContent );
		}

		$FS->putFileContent( path_join( $cacheDir, 'README.txt' ),
			"This is a temporary caching folder used by the Shield Security plugin. You can safely delete it, but it'll be recreated if required.\n" );

		return true;
	}

	private function buildCandidates( array $baseDirCandidates ) :array {
		$candidates = [];
		$cacheBasename = (string)( self::con()->cfg->paths[ 'cache' ] ?? '' );
		if ( \preg_match( '#^[a-z]+$#i', $cacheBasename ) ) {
			$candidates = \array_filter(
				\array_map( function ( string $baseDir ) use ( $cacheBasename ) {
					return untrailingslashit( wp_normalize_path( path_join( $baseDir, $cacheBasename ) ) );
				}, $baseDirCandidates ),
				function ( string $dir ) use ( $cacheBasename ) {
					return !empty( $dir ) && \str_ends_with( $dir, $cacheBasename );
				}
			);
		}
		return $candidates;
	}

	private function getBaseDirCandidates() :array {
		return \array_filter(
			\array_unique( \array_map(
				function ( $path ) {
					return untrailingslashit( wp_normalize_path( $path ) );
				},
				\array_filter( [
					$this->preferredDir,
					$this->lastKnownBaseDir,
					WP_CONTENT_DIR,
					path_join( ABSPATH, 'wp-content' ),
					path_join( WP_CONTENT_DIR, 'uploads' ),
					path_join( WP_CONTENT_DIR, 'cache' ),
					path_join( WP_CONTENT_DIR, 'tmp' ),
					get_temp_dir(),
				] )
			) ),
			function ( $path ) {
				return Services::WpFs()->isAccessibleDir( $path ) && wp_is_writable( $path );
			}
		);
	}
}