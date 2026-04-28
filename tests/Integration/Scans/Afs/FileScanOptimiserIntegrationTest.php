<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Scans\Afs;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\{
	Processing\FileScanOptimiser,
	Processing\TrustedFileContext,
	Scan,
	ScanActionVO,
	ScanFromFileMap
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class FileScanOptimiserIntegrationTest extends ShieldIntegrationTestCase {

	private const MALWARE_MARKER = 'SHIELD_INTEGRATION_AFS_CACHE_MARKER';

	private array $optionSnapshot = [];

	private array $createdDirs = [];

	private $originalCacheDirHandler;

	public function set_up() {
		parent::set_up();
		$this->optionSnapshot = $this->snapshotSelectedOptions( [
			'optimise_scan_speed',
			'enable_core_file_integrity_scan',
			'file_scan_areas',
		] );
		$this->originalCacheDirHandler = $this->requireController()->cache_dir_handler;
		$this->enablePremiumCapabilities( [
			'scan_files_everywhere',
			'scan_malware_local',
		] );
		$this->requireDb( 'malware' );
		$this->requireController()->opts
			->optSet( 'enable_core_file_integrity_scan', 'Y' )
			->optSet( 'file_scan_areas', [ 'wpcontent', 'malware_php' ] );
	}

	public function tear_down() {
		$this->requireController()->cache_dir_handler = $this->originalCacheDirHandler;
		$this->restoreSelectedOptions( $this->optionSnapshot );
		foreach ( \array_reverse( $this->createdDirs ) as $dir ) {
			$this->removeDir( $dir );
		}
		parent::tear_down();
	}

	public function test_cache_off_and_cache_on_return_same_result_flags_for_file_map_scan() :void {
		$fixtureDir = $this->makeDir( WP_CONTENT_DIR.'/shield-afs-cache-integration-'.\uniqid() );
		$clean = $this->writeFile( $fixtureDir.'/clean.php', '<?php clean_cache_fixture();' );
		$malware = $this->writeFile( $fixtureDir.'/malware.php', '<?php '.self::MALWARE_MARKER.'();' );
		$unidentified = $this->writeFile( $fixtureDir.'/unidentified.php', '<?php unidentified_cache_fixture();' );
		$malwareFiles = [ $clean, $malware ];

		$cacheOffResults = $this->runFileMap( $malwareFiles, false, [ 'malware_php' ] );
		$cacheOnFirstRunResults = $this->runFileMap( $malwareFiles, true, [ 'malware_php' ] );
		$cacheOnWarmRunResults = $this->runFileMap( $malwareFiles, true, [ 'malware_php' ] );

		$this->assertSame( $cacheOffResults, $cacheOnFirstRunResults );
		$this->assertSame( $cacheOffResults, $cacheOnWarmRunResults );
		$this->assertContains( true, \array_column( $cacheOffResults, 'is_mal' ) );

		$unidentifiedCacheOff = $this->runFileMap( [ $unidentified ], false, [ 'wpcontent', 'malware_php' ] );
		$unidentifiedCacheOn = $this->runFileMap( [ $unidentified ], true, [ 'wpcontent', 'malware_php' ] );
		$this->assertSame( $unidentifiedCacheOff, $unidentifiedCacheOn );
		$this->assertContains( true, \array_column( $unidentifiedCacheOff, 'is_unidentified' ) );
	}

	public function test_unavailable_cache_dir_keeps_scan_results_unchanged() :void {
		$fixtureDir = $this->makeDir( WP_CONTENT_DIR.'/shield-afs-cache-unavailable-'.\uniqid() );
		$malware = $this->writeFile( $fixtureDir.'/malware.php', '<?php '.self::MALWARE_MARKER.'();' );
		$cacheOffResults = $this->runFileMap( [ $malware ], false );

		$this->requireController()->cache_dir_handler = new class {
			public function exists() :bool {
				return false;
			}

			public function buildSubDir( string $subDir ) :string {
				unset( $subDir );
				return '';
			}
		};

		$this->assertSame( $cacheOffResults, $this->runFileMap( [ $malware ], true ) );
	}

	public function test_known_valid_cache_is_exact_to_core_context() :void {
		$corePath = \wp_normalize_path( \path_join( ABSPATH, 'wp-includes/version.php' ) );
		if ( !Services::WpFs()->isAccessibleFile( $corePath ) || !Services::CoreFileHashes()->isCoreFile( $corePath ) ) {
			$this->markTestSkipped( 'Core hash service is not ready for this integration fixture.' );
		}
		$tempDir = $this->makeDir( \sys_get_temp_dir().'/shield-afs-known-valid-'.\uniqid() );
		$copyPath = $this->writeFile( $tempDir.'/version.php', (string)\file_get_contents( $corePath ) );
		$action = $this->newAction( [ $corePath, $copyPath ] );
		$this->requireController()->opts->optSet( 'optimise_scan_speed', 'Y' );

		( new FileScanOptimiser() )->recordKnownValidFile( $corePath, new TrustedFileContext(
			'core',
			'core',
			Services::WpGeneral()->getVersion(),
			\str_replace( \wp_normalize_path( ABSPATH ), '', \wp_normalize_path( $corePath ) )
		) );

		$optimiser = new FileScanOptimiser();
		$this->assertTrue( $optimiser->canSkipKnownValidFile( $corePath, $action ) );
		$this->assertFalse( $optimiser->canSkipKnownValidFile( $copyPath, $action ) );
	}

	public function test_full_scan_prescan_filters_only_exact_known_valid_context() :void {
		$corePath = \wp_normalize_path( \path_join( ABSPATH, 'wp-includes/version.php' ) );
		if ( !Services::WpFs()->isAccessibleFile( $corePath ) || !Services::CoreFileHashes()->isCoreFile( $corePath ) ) {
			$this->markTestSkipped( 'Core hash service is not ready for this integration fixture.' );
		}
		$fixtureDir = $this->makeDir( WP_CONTENT_DIR.'/shield-afs-prescan-cache-'.\uniqid() );
		$coreCopy = $this->writeFile( $fixtureDir.'/version-copy.php', (string)\file_get_contents( $corePath ) );
		$malware = $this->writeFile( $fixtureDir.'/malware.php', '<?php '.self::MALWARE_MARKER.'();' );
		$files = [ $corePath, $coreCopy, $malware ];

		$cacheOffAction = $this->runFullScan( $files, false, [ 'wpcontent', 'malware_php' ] );

		( new FileScanOptimiser() )->recordKnownValidFile( $corePath, new TrustedFileContext(
			'core',
			'core',
			Services::WpGeneral()->getVersion(),
			\str_replace( \wp_normalize_path( ABSPATH ), '', \wp_normalize_path( $corePath ) )
		) );
		$cacheOnAction = $this->runFullScan( $files, true, [ 'wpcontent', 'malware_php' ] );

		$decodedItems = \array_map( '\base64_decode', $cacheOnAction->items );
		$this->assertFalse( \in_array( $corePath, $decodedItems, true ) );
		$this->assertTrue( \in_array( $coreCopy, $decodedItems, true ) );
		$this->assertTrue( \in_array( $malware, $decodedItems, true ) );
		$this->assertSame( $this->normaliseRawResults( $cacheOffAction->results ), $this->normaliseRawResults( $cacheOnAction->results ) );
		$this->assertContains( true, \array_column( $this->normaliseRawResults( $cacheOnAction->results ), 'is_unidentified' ) );
		$this->assertContains( true, \array_column( $this->normaliseRawResults( $cacheOnAction->results ), 'is_mal' ) );
	}

	private function runFileMap( array $files, bool $optimise, array $scanAreas = [ 'malware_php' ] ) :array {
		$this->requireController()->opts->optSet( 'optimise_scan_speed', $optimise ? 'Y' : 'N' );
		$this->requireController()->opts->optSet( 'file_scan_areas', $scanAreas );
		$results = ( new ScanFromFileMap() )
			->setScanActionVO( $this->newAction( $files ) )
			->run()
			->getAllItems();

		$normalised = \array_map(
			fn( $item ) :array => [
				'path'            => (string)$item->path_fragment,
				'is_mal'          => $item->is_mal,
				'is_unrecognised' => $item->is_unrecognised,
				'is_unidentified' => $item->is_unidentified,
				'is_checksumfail' => $item->is_checksumfail,
				'is_missing'      => $item->is_missing,
			],
			$results
		);
		\usort( $normalised, static fn( array $a, array $b ) :int => $a[ 'path' ] <=> $b[ 'path' ] );
		return $normalised;
	}

	private function runFullScan( array $files, bool $optimise, array $scanAreas ) :ScanActionVO {
		$this->seedMalwarePatternCache();
		$this->requireController()->opts->optSet( 'optimise_scan_speed', $optimise ? 'Y' : 'N' );
		$this->requireController()->opts->optSet( 'file_scan_areas', $scanAreas );
		$action = $this->newAction( $files );

		( new Scan() )
			->setScanActionVO( $action )
			->run();

		return $action;
	}

	private function normaliseRawResults( array $results ) :array {
		$normalised = \array_map(
			static fn( array $item ) :array => [
				'path'            => (string)$item[ 'path_fragment' ],
				'is_mal'          => (bool)( $item[ 'is_mal' ] ?? false ),
				'is_unrecognised' => (bool)( $item[ 'is_unrecognised' ] ?? false ),
				'is_unidentified' => (bool)( $item[ 'is_unidentified' ] ?? false ),
				'is_checksumfail' => (bool)( $item[ 'is_checksumfail' ] ?? false ),
				'is_missing'      => (bool)( $item[ 'is_missing' ] ?? false ),
			],
			$results
		);
		\usort( $normalised, static fn( array $a, array $b ) :int => $a[ 'path' ] <=> $b[ 'path' ] );
		return $normalised;
	}

	private function seedMalwarePatternCache() :void {
		$dir = $this->requireController()->cache_dir_handler->buildSubDir( 'scans' );
		if ( empty( $dir ) ) {
			throw new \RuntimeException( 'Could not create malware pattern cache directory.' );
		}
		Services::WpFs()->putFileContent(
			\path_join( $dir, 'malcache_patterns_v2.txt' ),
			\json_encode( [
				'raw'       => [ self::MALWARE_MARKER ],
				're'        => [ self::MALWARE_MARKER ],
				'iraw'      => [],
				'functions' => [],
				'keywords'  => [],
			] ),
			true
		);
	}

	private function newAction( array $files ) :ScanActionVO {
		$action = new ScanActionVO();
		$action->scan = 'afs';
		$action->file_exts = [ 'php' ];
		$action->items = \array_map( '\base64_encode', $files );
		$action->max_file_size = 16*1024*1024;
		$action->patterns_raw = [ self::MALWARE_MARKER ];
		$action->patterns_iraw = [];
		$action->patterns_regex = [];
		$action->patterns_functions = [];
		$action->patterns_keywords = [];
		return $action;
	}

	private function makeDir( string $dir ) :string {
		$dir = \wp_normalize_path( $dir );
		if ( !\is_dir( $dir ) && !@\mkdir( $dir, 0777, true ) && !\is_dir( $dir ) ) {
			throw new \RuntimeException( 'Could not create fixture directory.' );
		}
		$this->createdDirs[] = $dir;
		return $dir;
	}

	private function writeFile( string $path, string $content ) :string {
		if ( !\is_dir( \dirname( $path ) ) ) {
			$this->makeDir( \dirname( $path ) );
		}
		\file_put_contents( $path, $content );
		return \wp_normalize_path( $path );
	}

	private function removeDir( string $dir ) :void {
		if ( !\is_dir( $dir ) ) {
			return;
		}
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iterator as $item ) {
			$item->isDir() ? @\rmdir( $item->getPathname() ) : @\unlink( $item->getPathname() );
		}
		@\rmdir( $dir );
	}
}
