<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Controller\Config\Opts;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts\PreStore;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\CacheStore\{
	CacheStoreTestController,
	CacheStoreTestFs,
	CacheStoreTestOptions,
	CacheStoreTestRequest,
	CacheStoreWordPressFunctions
};

class PreStoreCacheDirPreferenceTest extends BaseUnitTest {

	use CacheStoreWordPressFunctions;

	private array $servicesSnapshot = [];

	private array $tempDirs = [];

	private CacheStoreTestFs $fs;

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		$this->fs = new CacheStoreTestFs();
		$this->registerCacheStoreWordPressFunctions( $this->fs, $this->makeTempDir( 'tmp' ) );
		ServicesState::installItems( [
			'service_request' => new CacheStoreTestRequest(),
			'service_wpfs'    => $this->fs,
		] );
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		foreach ( \array_reverse( $this->tempDirs ) as $dir ) {
			$this->removeDir( $dir );
		}
		parent::tearDown();
	}

	public function test_preferred_temp_dir_is_not_cleared_when_path_missing() :void {
		$preferred = $this->normaliseCacheStorePath( WP_CONTENT_DIR.'/uploads/shield-missing' );
		$this->mkdir( \dirname( $preferred ) );
		$options = $this->installController( $preferred );

		$this->runPluginKeepers();

		$this->assertSame( $preferred, $options->values[ 'preferred_temp_dir' ] );
	}

	public function test_preferred_temp_dir_is_not_cleared_when_temporarily_unwritable() :void {
		$preferred = $this->normaliseCacheStorePath( WP_CONTENT_DIR.'/uploads/shield' );
		$this->mkdir( $preferred );
		$this->fs->failDir( $preferred );
		$options = $this->installController( $preferred );

		$this->runPluginKeepers();

		$this->assertSame( $preferred, $options->values[ 'preferred_temp_dir' ] );
	}

	private function installController( string $preferred ) :CacheStoreTestOptions {
		$options = new CacheStoreTestOptions( [
			'preferred_temp_dir'              => $preferred,
			'ipdetect_at'                     => 1,
			'visitor_address_source'          => 'AUTO_DETECT_IP',
			'instant_alert_filelocker'        => 'disabled',
			'instant_alert_vulnerabilities'   => 'disabled',
			'tracking_permission_set_at'      => 1,
		] );
		$controller = CacheStoreTestController::install( $options );
		$controller->comps = (object)[
			'file_locker' => new class {
				public function isEnabled() :bool {
					return false;
				}
			},
			'scans'       => new class {
				public function WPV() :object {
					return new class {
						public function isEnabled() :bool {
							return false;
						}
					};
				}
			},
			'opts_lookup' => new class {
				public function enabledTelemetry() :bool {
					return false;
				}
			},
		];
		return $options;
	}

	private function runPluginKeepers() :void {
		$method = new \ReflectionMethod( PreStore::class, 'pluginKeepers' );
		$method->setAccessible( true );
		$method->invoke( new PreStore() );
	}

	private function makeTempDir( string $suffix ) :string {
		$dir = $this->normaliseCacheStorePath( \sys_get_temp_dir().'/shield-prestore-cache-'.$suffix.'-'.\uniqid() );
		$this->mkdir( $dir );
		$this->tempDirs[] = $dir;
		return $dir;
	}

	private function mkdir( string $dir ) :void {
		if ( !\is_dir( $dir ) ) {
			@\mkdir( $dir, 0777, true );
		}
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
