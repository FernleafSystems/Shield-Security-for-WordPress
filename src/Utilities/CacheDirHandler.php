<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\HashesStorageDir;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\AssessDirWrite;

class CacheDirHandler {

	use PluginControllerConsumer;

	private const EXTERNAL_CACHE_NAMESPACE_SEED_VERSION = 'shield-external-cache-namespace-v2';
	private const EXTERNAL_CACHE_NAMESPACE_HASH_LENGTH = 32;
	private const LEGACY_EXTERNAL_CACHE_ROOT_SUFFIX_MAX_LENGTH = 48;

	private ?string $cacheDir = null;

	private ?string $externalCacheBasename = null;

	private string $lastKnownBaseDir;

	private string $preferredDir;

	public function __construct( string $lastKnownBaseDir = '', string $preferredDir = '' ) {
		$this->lastKnownBaseDir = $lastKnownBaseDir;
		$this->preferredDir = $preferredDir;
	}

	public function dir( bool $retest = false ) :string {
		if ( $this->cacheDir === null || $retest ) {
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

	public function locateExistingDir() :string {
		$FS = Services::WpFs();
		if ( !empty( $this->cacheDir ) && $FS->isDir( $this->cacheDir ) ) {
			return $this->cacheDir;
		}

		$configuredPath = $this->configuredPath();
		if ( $configuredPath !== null ) {
			$configuredCandidate = $this->canonicaliseConfiguredPath( $configuredPath );
			return $configuredCandidate === '' ? '' : $this->locateExistingCandidate( [ $configuredCandidate ] );
		}

		$candidates = $this->buildCandidates( $this->getDiscoveryBaseDirCandidates( false ) );
		$rankedCandidates = $this->getExistingSnapshotRootCandidates( $candidates );

		return $this->locateExistingCandidate(
			\array_merge( $rankedCandidates, $candidates, $this->getTempFallbackCandidates() )
		);
	}

	private function resolveConfiguredDir() :?string {
		$configuredPath = $this->configuredPath();
		if ( $configuredPath === null ) {
			return null;
		}

		$configuredCandidate = $this->canonicaliseConfiguredPath( $configuredPath );
		return $configuredCandidate === '' ? '' : ( $this->assessCandidates( [ $configuredCandidate ] ) ?? '' );
	}

	private function configuredPath() :?string {
		if ( $this->preferredDir !== '' ) {
			$configuredPath = $this->preferredDir;
		}
		elseif ( $this->lastKnownBaseDir !== '' ) {
			$configuredPath = $this->lastKnownBaseDir;
		}
		else {
			$configuredPath = null;
		}
		return $configuredPath;
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

		$readme = path_join( $cacheDir, 'README.txt' );
		$readmeContent = sprintf( "This is a temporary caching folder used by the %s plugin. You can safely delete it, but it'll be recreated if required.\n", self::con()->labels->Name );
		if ( !$FS->exists( $readme ) || !\hash_equals( \hash( 'sha256', $readmeContent ), \hash_file( 'sha256', $readme ) ) ) {
			$FS->putFileContent( $readme, $readmeContent );
		}

		return true;
	}

	private function buildCandidates( array $baseDirCandidates ) :array {
		$candidates = [];
		$cacheBasename = $this->cacheBasename();
		if ( !empty( $cacheBasename ) ) {
			$candidates = \array_filter( \array_map(
				fn( string $baseDir ) :string => $this->canonicaliseKnownBase( $baseDir, $cacheBasename ),
				$baseDirCandidates
			) );
		}
		return $candidates;
	}

	private function canonicaliseConfiguredPath( string $configuredPath ) :string {
		$cacheBasename = $this->cacheBasename();
		$path = $this->normalisePathSegments( $configuredPath );
		if ( $cacheBasename === '' || $path === '' ) {
			return '';
		}

		$basename = \basename( $path );
		if ( $this->isPathWithinAbsPath( $path ) ) {
			$candidate = $this->basenamesMatch( $basename, $cacheBasename )
				? $path
				: $this->normalisePathSegments( path_join( $path, $cacheBasename ) );
		}
		else {
			$externalCacheBasename = $this->externalCacheBasename( $cacheBasename );
			if ( $externalCacheBasename === '' ) {
				return '';
			}

			if ( $this->isV2CacheRootBasename( $basename, $cacheBasename ) ) {
				$candidate = $this->basenamesMatch( $basename, $externalCacheBasename )
					? $this->normalisePathSegments( path_join( \dirname( $path ), $externalCacheBasename ) )
					: '';
			}
			elseif ( $this->basenamesMatch( $basename, $cacheBasename )
				   || $this->isLegacyCacheRootBasename( $basename, $cacheBasename ) ) {
				$candidate = $this->normalisePathSegments( path_join( \dirname( $path ), $externalCacheBasename ) );
			}
			else {
				$candidate = $this->normalisePathSegments( path_join( $path, $externalCacheBasename ) );
			}
		}

		return $this->isOwnedCacheRootPath( $candidate, $cacheBasename ) ? $candidate : '';
	}

	private function canonicaliseKnownBase( string $baseDir, string $cacheBasename ) :string {
		$baseDir = $this->normalisePathSegments( $baseDir );
		if ( $baseDir === '' ) {
			return '';
		}

		$unsuffixedCandidate = $this->normalisePathSegments( path_join( $baseDir, $cacheBasename ) );
		if ( $this->isPathWithinAbsPath( $unsuffixedCandidate ) ) {
			$candidate = $unsuffixedCandidate;
		}
		else {
			$externalCacheBasename = $this->externalCacheBasename( $cacheBasename );
			$candidate = $externalCacheBasename === ''
				? ''
				: $this->normalisePathSegments( path_join( $baseDir, $externalCacheBasename ) );
		}

		return $this->isOwnedCacheRootPath( $candidate, $cacheBasename ) ? $candidate : '';
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
		$marker = path_join( $candidate, HashesStorageDir::ACTIVE_MARKER );
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

	private function locateExistingCandidate( array $candidates ) :string {
		$dir = '';
		$FS = Services::WpFs();
		foreach ( \array_unique( $candidates ) as $candidate ) {
			$candidate = untrailingslashit( wp_normalize_path( $candidate ) );
			if ( !empty( $candidate ) && $FS->isDir( $candidate ) ) {
				$dir = $candidate;
				break;
			}
		}
		return $dir;
	}

	private function getTempFallbackCandidates() :array {
		$candidates = [];
		if ( empty( \ini_get( 'open_basedir' ) ) ) {
			$candidates = $this->buildCandidates( \array_filter(
				\array_unique( \array_map(
					fn( $path ) => untrailingslashit( wp_normalize_path( $path ) ),
					\array_filter( [
						get_temp_dir(),
						'/tmp',
					] )
				) ),
				fn( $path ) => Services::WpFs()->isAccessibleDir( $path )
			) );
		}
		return $candidates;
	}

	private function isHashDirBasename( string $basename ) :bool {
		return \preg_match( '#^ptguard-[a-z0-9]{16}$#i', $basename ) === 1;
	}

	private function cacheBasename() :string {
		$cacheBasename = (string)( self::con()->cfg->paths[ 'cache' ] ?? '' );
		return \preg_match( '#^[a-z]+$#i', $cacheBasename ) ? $cacheBasename : '';
	}

	private function isPathWithinAbsPath( string $path ) :bool {
		$absPath = $this->pathForLexicalComparison( ABSPATH );
		$path = $this->pathForLexicalComparison( $path );
		return $absPath !== '' && ( $path === $absPath || \str_starts_with( $path.'/', $absPath.'/' ) );
	}

	private function externalCacheBasename( string $cacheBasename ) :string {
		if ( $this->externalCacheBasename === null ) {
			$this->externalCacheBasename = '';
			try {
				$absPath = $this->pathForLexicalComparison( ABSPATH );
				$dbHost = \defined( 'DB_HOST' ) ? (string)\constant( 'DB_HOST' ) : '';
				$dbName = \defined( 'DB_NAME' ) ? (string)\constant( 'DB_NAME' ) : '';
				$dbPrefix = Services::WpDb()->getPrefix();
				$blogID = \function_exists( 'get_current_blog_id' ) ? (int)\get_current_blog_id() : 0;
				if ( $absPath !== '' && \trim( $dbHost ) !== '' && \trim( $dbName ) !== ''
					 && \trim( $dbPrefix ) !== '' && $blogID > 0 ) {
					$seed = \implode( "\0", [
						self::EXTERNAL_CACHE_NAMESPACE_SEED_VERSION,
						$absPath,
						$dbHost,
						$dbName,
						$dbPrefix,
						(string)$blogID,
					] );
					$this->externalCacheBasename = $cacheBasename.'-v2-'
						.\substr( \hash( 'sha256', $seed ), 0, self::EXTERNAL_CACHE_NAMESPACE_HASH_LENGTH );
				}
			}
			catch ( \Throwable $e ) {
				$this->externalCacheBasename = '';
			}
		}
		return $this->externalCacheBasename;
	}

	private function pathForLexicalComparison( string $path ) :string {
		$path = $this->normalisePathSegments( $path );
		return \DIRECTORY_SEPARATOR === '\\' ? \strtolower( $path ) : $path;
	}

	private function normalisePathSegments( string $path ) :string {
		$path = untrailingslashit( wp_normalize_path( $path ) );
		if ( $path === '' ) {
			return '';
		}

		$prefix = '';
		$rest = $path;
		if ( \preg_match( '#^([a-z]:)(?:/(.*))?$#i', $path, $matches ) === 1 ) {
			$prefix = $matches[ 1 ];
			$rest = $matches[ 2 ] ?? '';
		}
		elseif ( \str_starts_with( $path, '//' ) ) {
			$prefix = '//';
			$rest = \substr( $path, 2 );
		}
		elseif ( \str_starts_with( $path, '/' ) ) {
			$prefix = '/';
			$rest = \substr( $path, 1 );
		}

		$segments = [];
		foreach ( \explode( '/', $rest ) as $segment ) {
			if ( $segment === '' || $segment === '.' ) {
				continue;
			}
			if ( $segment === '..' ) {
				if ( !empty( $segments ) && \end( $segments ) !== '..' ) {
					\array_pop( $segments );
				}
				elseif ( $prefix === '' ) {
					$segments[] = $segment;
				}
				continue;
			}
			$segments[] = $segment;
		}

		$collapsed = \implode( '/', $segments );
		return $prefix === ''
			? $collapsed
			: ( $prefix === '/' || $prefix === '//' ? $prefix.$collapsed : $prefix.( $collapsed === '' ? '' : '/'.$collapsed ) );
	}

	private function isOwnedCacheRootPath( string $path, string $cacheBasename ) :bool {
		$path = $this->normalisePathSegments( $path );
		if ( $path === '' ) {
			return false;
		}

		if ( $this->isPathWithinAbsPath( $path ) ) {
			return $this->basenamesMatch( \basename( $path ), $cacheBasename );
		}

		$externalCacheBasename = $this->externalCacheBasename( $cacheBasename );
		return $externalCacheBasename !== ''
			   && $this->basenamesMatch( \basename( $path ), $externalCacheBasename )
			   && !$this->hasV2CacheRootAncestor( $path, $cacheBasename );
	}

	private function isV2CacheRootBasename( string $basename, string $cacheBasename ) :bool {
		return \preg_match(
			'#^'.\preg_quote( $cacheBasename, '#' ).'-v2-[a-f0-9]{'.self::EXTERNAL_CACHE_NAMESPACE_HASH_LENGTH.'}$#'
			.( \DIRECTORY_SEPARATOR === '\\' ? 'i' : '' ),
			$basename
		) === 1;
	}

	private function isLegacyCacheRootBasename( string $basename, string $cacheBasename ) :bool {
		$prefix = $cacheBasename.'-';
		if ( \strlen( $basename ) <= \strlen( $prefix )
			 || !$this->basenamesMatch( \substr( $basename, 0, \strlen( $prefix ) ), $prefix ) ) {
			return false;
		}

		$suffix = \substr( $basename, \strlen( $prefix ) );
		return \strlen( $suffix ) <= self::LEGACY_EXTERNAL_CACHE_ROOT_SUFFIX_MAX_LENGTH
			   && \preg_match( '#^[a-z0-9]+(?:-[a-z0-9]+)*$#'.( \DIRECTORY_SEPARATOR === '\\' ? 'i' : '' ), $suffix ) === 1;
	}

	private function hasV2CacheRootAncestor( string $path, string $cacheBasename ) :bool {
		$components = \explode( '/', \trim( $this->normalisePathSegments( $path ), '/' ) );
		\array_pop( $components );
		foreach ( $components as $component ) {
			if ( $this->isV2CacheRootBasename( $component, $cacheBasename ) ) {
				return true;
			}
		}
		return false;
	}

	private function basenamesMatch( string $first, string $second ) :bool {
		return \DIRECTORY_SEPARATOR === '\\'
			? \strtolower( $first ) === \strtolower( $second )
			: $first === $second;
	}

	private function pathForWordPressAbsoluteCheck( string $dir ) :string {
		return \DIRECTORY_SEPARATOR === '\\' && \preg_match( '#^[a-z]:/#i', $dir ) === 1
			? \str_replace( '/', '\\', $dir )
			: $dir;
	}

	private function getDiscoveryBaseDirCandidates( bool $requireWritable = true ) :array {
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
			fn( $path ) => Services::WpFs()->isAccessibleDir( $path ) && ( !$requireWritable || wp_is_writable( $path ) )
		);
	}
}
