<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\AssessDirWrite;

class CacheDirHandler {

	use PluginControllerConsumer;

	private const ACTIVE_HASH_DIR_MARKER = 'ptguard-active.txt';

	private $cacheDir;

	private string $lastKnownBaseDir;

	private string $preferredDir;

	public function __construct( string $lastKnownBaseDir = '', string $preferredDir = '' ) {
		$this->lastKnownBaseDir = $lastKnownBaseDir;
		$this->preferredDir = $preferredDir;
	}

	public function dir( bool $retest = false ) :string {
		if ( !isset( $this->cacheDir ) || $retest ) {
			$this->cacheDir = '';

			$dir = $this->resolveConfiguredDir();
			if ( $dir === null ) {
				$candidates = $this->buildCandidates( $this->getDiscoveryBaseDirCandidates() );
				$dir = $this->assessCandidates( $this->getExistingSnapshotRootCandidates( $candidates ) );
				if ( empty( $dir ) ) {
					$dir = $this->assessCandidates( $candidates );
				}
				if ( empty( $dir ) && empty( \ini_get( 'open_basedir' ) ) ) {
					$dir = $this->assessCandidates( $this->buildCandidates( [ get_temp_dir() ] ) );
					if ( empty( $dir ) ) {
						$dir = $this->assessCandidates( $this->buildCandidates( [ '/tmp' ] ) );
					}
				}
			}

			if ( !empty( $dir ) ) {
				$this->cacheDir = $dir;
			}
		}
		return $this->cacheDir;
	}

	private function resolveConfiguredDir() :?string {
		$configuredCandidates = [];
		if ( !empty( $this->preferredDir ) ) {
			$configuredCandidates = $this->buildConfiguredCandidates( [ $this->preferredDir ] );
		}
		elseif ( !empty( $this->lastKnownBaseDir ) ) {
			$configuredCandidates = $this->buildConfiguredCandidates( [ $this->lastKnownBaseDir ] );
		}
		return empty( $configuredCandidates ) ? null : ( $this->assessCandidates( $configuredCandidates ) ?? '' );
	}

	private function assessCandidates( array $candidates ) :?string {
		$chosenDir = null;
		foreach ( $candidates as $maybeDir ) {
			if ( $this->testDir( $maybeDir ) ) {
				$chosenDir = $maybeDir;
				if ( !\str_starts_with( $maybeDir, '/tmp' ) ) {
					$this->addProtections( $maybeDir );
				}
				break;
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

				$assess = ( new AssessDirWrite( $this->pathForWordPressAbsoluteCheck( $dir ) ) )->test();
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
		$rootDir = $this->dir();
		if ( !empty( $rootDir ) && !Services::WpFs()->isDir( $rootDir ) ) {
			$rootDir = $this->dir( true );
		}
		if ( !empty( $rootDir ) && Services::WpFs()->isDir( $rootDir ) ) {
			$finalDir = path_join( $rootDir, $subDir );
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
			sprintf( "This is a temporary caching folder used by the %s plugin. You can safely delete it, but it'll be recreated if required.\n", self::con()->labels->Name ) );

		return true;
	}

	private function buildCandidates( array $baseDirCandidates ) :array {
		$candidates = [];
		$cacheBasename = $this->cacheBasename();
		if ( !empty( $cacheBasename ) ) {
			$candidates = \array_filter(
				\array_map(
					fn( string $baseDir ) => untrailingslashit( wp_normalize_path( path_join( $baseDir, $cacheBasename ) ) ),
					$baseDirCandidates
				),
				fn( string $dir ) => !empty( $dir ) && \str_ends_with( $dir, $cacheBasename )
			);
		}
		return $candidates;
	}

	private function buildConfiguredCandidates( array $configuredPaths ) :array {
		$candidates = [];
		$cacheBasename = $this->cacheBasename();
		if ( !empty( $cacheBasename ) ) {
			$candidates = \array_filter(
				\array_unique( \array_map(
					function ( string $path ) use ( $cacheBasename ) :string {
						$path = untrailingslashit( wp_normalize_path( $path ) );
						return \basename( $path ) === $cacheBasename ? $path : untrailingslashit( wp_normalize_path( path_join( $path, $cacheBasename ) ) );
					},
					\array_filter( $configuredPaths )
				) ),
				fn( string $dir ) => !empty( $dir ) && \str_ends_with( $dir, $cacheBasename )
			);
		}
		return $candidates;
	}

	private function getExistingSnapshotRootCandidates( array $candidates ) :array {
		$ranked = [];
		foreach ( \array_unique( $candidates ) as $candidate ) {
			$markerMTime = $this->getValidActiveMarkerMTime( $candidate );
			if ( $markerMTime > 0 ) {
				$ranked[] = [
					'dir'      => $candidate,
					'priority' => 2,
					'mtime'    => $markerMTime,
				];
				continue;
			}

			$newestHashDirMTime = $this->getNewestHashDirMTime( $candidate );
			if ( $newestHashDirMTime > 0 ) {
				$ranked[] = [
					'dir'      => $candidate,
					'priority' => 1,
					'mtime'    => $newestHashDirMTime,
				];
			}
		}

		\usort( $ranked, static function ( array $a, array $b ) :int {
			return $b[ 'priority' ] <=> $a[ 'priority' ]
				   ?: $b[ 'mtime' ] <=> $a[ 'mtime' ]
					  ?: \strcmp( $a[ 'dir' ], $b[ 'dir' ] );
		} );

		return \array_column( $ranked, 'dir' );
	}

	private function getValidActiveMarkerMTime( string $candidate ) :int {
		$FS = Services::WpFs();
		$mtime = 0;
		$marker = path_join( $candidate, self::ACTIVE_HASH_DIR_MARKER );
		if ( $FS->isAccessibleFile( $marker ) ) {
			$activeDirBasename = \trim( (string)$FS->getFileContent( $marker ) );
			$activeDir = path_join( $candidate, $activeDirBasename );
			if ( $this->isHashDirBasename( $activeDirBasename ) && $FS->isDir( $activeDir ) ) {
				$mtime = $FS->getModifiedTime( $marker );
			}
		}
		return $mtime;
	}

	private function getNewestHashDirMTime( string $candidate ) :int {
		$FS = Services::WpFs();
		$mtime = 0;
		foreach ( $FS->getAllFilesInDir( $candidate ) as $fileItem ) {
			if ( $FS->isDir( $fileItem ) && $this->isHashDirBasename( \basename( $fileItem ) ) ) {
				$mtime = \max( $mtime, $FS->getModifiedTime( $fileItem ) );
			}
		}
		return $mtime;
	}

	private function isHashDirBasename( string $basename ) :bool {
		return \preg_match( '#^ptguard-[a-z0-9]{16}$#i', $basename ) === 1;
	}

	private function cacheBasename() :string {
		$cacheBasename = (string)( self::con()->cfg->paths[ 'cache' ] ?? '' );
		return \preg_match( '#^[a-z]+$#i', $cacheBasename ) ? $cacheBasename : '';
	}

	private function pathForWordPressAbsoluteCheck( string $dir ) :string {
		return \DIRECTORY_SEPARATOR === '\\' && \preg_match( '#^[a-z]:/#i', $dir ) === 1
			? \str_replace( '/', '\\', $dir )
			: $dir;
	}

	private function getDiscoveryBaseDirCandidates() :array {
		return \array_filter(
			\array_unique( \array_map(
				fn( $path ) => untrailingslashit( wp_normalize_path( $path ) ),
				\array_filter( [
					WP_CONTENT_DIR,
					path_join( ABSPATH, 'wp-content' ),
					path_join( WP_CONTENT_DIR, 'uploads' ),
					path_join( WP_CONTENT_DIR, 'cache' ),
					path_join( WP_CONTENT_DIR, 'tmp' ),
					get_temp_dir(),
				] )
			) ),
			fn( $path ) => Services::WpFs()->isAccessibleDir( $path ) && wp_is_writable( $path )
		);
	}
}
