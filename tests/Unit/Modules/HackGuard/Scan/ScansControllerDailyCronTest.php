<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan;

use Brain\Monkey\Functions;
use Carbon\Carbon;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\ScansController;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\{
	Processing\FileScanOptimiser,
	ScanActionVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState
};
use FernleafSystems\Wordpress\Services\Core\{
	Fs,
	Request
};

class ScansControllerDailyCronTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	private array $tempDirs = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		Functions\when( 'path_join' )->alias( fn( string $a, string $b ) :string => $this->normalisePath( \rtrim( $a, '/\\' ).'/'.\ltrim( $b, '/\\' ) ) );
		Functions\when( 'wp_json_encode' )->alias( static fn( $data ) :string => \json_encode( $data ) );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		foreach ( \array_reverse( $this->tempDirs ) as $dir ) {
			$this->removeDir( $dir );
		}
		parent::tearDown();
	}

	public function test_daily_cron_cleans_stale_optimiser_cache_on_non_sunday() :void {
		$cacheDir = $this->makeTempDir( 'cache' );
		$request = new ScansDailyCronRequest( Carbon::create( 2024, 4, 7, 12, 0, 0, 'UTC' )->timestamp );
		$this->installEnvironment( $cacheDir, $request );
		$old = $this->writeFile( ABSPATH.'wp-content/uploads/cron-old.php', '<?php old_clean();' );
		$fresh = $this->writeFile( ABSPATH.'wp-content/uploads/cron-fresh.php', '<?php fresh_clean();' );
		$action = $this->newAction();
		$optimiser = new FileScanOptimiser();

		$optimiser->recordCleanMalwareVerdict( $old, $action );
		$request->now = Carbon::create( 2024, 4, 9, 12, 0, 0, 'UTC' )->timestamp;
		$optimiser->recordCleanMalwareVerdict( $fresh, $action );
		$this->assertTrue( $optimiser->hasCleanMalwareVerdict( $old, $action ) );
		$this->assertTrue( $optimiser->hasCleanMalwareVerdict( $fresh, $action ) );

		$request->now = Carbon::create( 2024, 4, 15, 12, 0, 0, 'UTC' )->timestamp;
		( new ScansController() )->runDailyCron();

		$this->assertFalse( $optimiser->hasCleanMalwareVerdict( $old, $action ) );
		$this->assertTrue( $optimiser->hasCleanMalwareVerdict( $fresh, $action ) );
	}

	private function installEnvironment( string $cacheDir, ScansDailyCronRequest $request ) :void {
		ServicesState::installItems( [
			'service_request' => $request,
			'service_wpfs'    => new ScansDailyCronFs(),
		] );

		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->cache_dir_handler = new ScansDailyCronCacheDir( $cacheDir );
		PluginControllerInstaller::install( $controller );
	}

	private function newAction() :ScanActionVO {
		$action = new ScanActionVO();
		$action->scan = 'afs';
		$action->file_exts = [ 'php' ];
		$action->patterns_raw = [ 'bad_token' ];
		$action->patterns_iraw = [];
		$action->patterns_regex = [];
		$action->patterns_functions = [];
		$action->patterns_keywords = [];
		return $action;
	}

	private function writeFile( string $path, string $content ) :string {
		$path = $this->normalisePath( $path );
		if ( !\is_dir( \dirname( $path ) ) ) {
			@\mkdir( \dirname( $path ), 0777, true );
		}
		\file_put_contents( $path, $content );
		return $path;
	}

	private function makeTempDir( string $suffix ) :string {
		$dir = $this->normalisePath( \sys_get_temp_dir().'/shield-scans-daily-cron-'.$suffix.'-'.\uniqid() );
		@\mkdir( $dir, 0777, true );
		$this->tempDirs[] = $dir;
		return $dir;
	}

	private function normalisePath( string $path ) :string {
		return \str_replace( '\\', '/', $path );
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

class ScansDailyCronCacheDir {
	private string $dir;

	public function __construct( string $dir ) {
		$this->dir = $dir;
	}

	public function exists() :bool {
		return \is_dir( $this->dir ) && \is_writable( $this->dir );
	}

	public function buildSubDir( string $subDir ) :string {
		$path = $this->dir.'/'.$subDir;
		return ( \is_dir( $path ) || @\mkdir( $path, 0777, true ) ) ? $path : '';
	}

	public function dir() :string {
		return $this->dir;
	}
}

class ScansDailyCronRequest extends Request {
	public int $now;

	public function __construct( int $now ) {
		$this->now = $now;
	}

	public function carbon( $setTimezone = false, bool $userLocale = true ) :Carbon {
		unset( $setTimezone, $userLocale );
		return Carbon::createFromTimestampUTC( $this->now );
	}

	public function ts( bool $update = true ) :int {
		unset( $update );
		return $this->now;
	}
}

class ScansDailyCronFs extends Fs {
	public function isAccessibleFile( string $file ) :bool {
		return \is_file( $file ) && \is_readable( $file );
	}

	public function getAllFilesInDir( $dir, $includeDirs = true ) {
		unset( $includeDirs );
		return \is_dir( $dir ) ? \array_map(
			static fn( \SplFileInfo $item ) :string => $item->getPathname(),
			\iterator_to_array( new \FilesystemIterator( $dir, \FilesystemIterator::SKIP_DOTS ) )
		) : [];
	}

	public function isDir( string $path ) :bool {
		return \is_dir( $path );
	}

	public function deleteDir( $dir ) {
		return @\rmdir( $dir );
	}
}
