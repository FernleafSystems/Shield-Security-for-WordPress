<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModCon;
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
	CacheStoreTestWpGeneral,
	CacheStoreWordPressFunctions
};
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\CacheDirHandler;

class ModConCacheDirHandlerTest extends BaseUnitTest {

	use CacheStoreWordPressFunctions;

	private array $servicesSnapshot = [];

	private array $tempDirs = [];

	private CacheStoreTestFs $fs;

	private CacheStoreTestWpGeneral $wpGeneral;

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		$this->fs = new CacheStoreTestFs();
		$this->wpGeneral = new CacheStoreTestWpGeneral( 'https://first.example/' );
		$tmpDir = $this->makeTempDir( 'tmp' );
		$this->registerCacheStoreWordPressFunctions( $this->fs, $tmpDir );
		ServicesState::installItems( [
			'service_request'   => new CacheStoreTestRequest(),
			'service_wpfs'      => $this->fs,
			'service_wpgeneral' => $this->wpGeneral,
		] );
		$this->prepareWpContentDirs();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		foreach ( \array_reverse( $this->tempDirs ) as $dir ) {
			$this->removeDir( $dir );
		}
		parent::tearDown();
	}

	public function test_default_key_wins_over_current_url_conflict() :void {
		$options = new CacheStoreTestOptions( [
			'preferred_temp_dir'       => '',
			'last_known_cache_basedirs' => [
				'_default'               => $this->baseUploads(),
				'https://first.example/' => $this->baseCache(),
			],
		] );

		$handler = $this->buildHandler( $options );

		$this->assertSame( $this->baseUploads().'/shield', $handler->dir() );
		$this->assertSame( [
			'_default'               => $this->baseUploads(),
			'https://first.example/' => $this->baseCache(),
		], $options->values[ 'last_known_cache_basedirs' ] );
	}

	public function test_legacy_url_value_seeds_handler_without_migrating_on_build() :void {
		$options = new CacheStoreTestOptions( [
			'preferred_temp_dir'       => '',
			'last_known_cache_basedirs' => [
				'https://first.example/' => $this->baseUploads(),
			],
		] );

		$this->assertSame( $this->baseUploads().'/shield', $this->buildHandler( $options )->dir() );
		$this->assertSame( [
			'https://first.example/' => $this->baseUploads(),
		], $options->values[ 'last_known_cache_basedirs' ] );
	}

	public function test_invalid_default_key_falls_back_to_legacy_string_without_migrating_on_build() :void {
		$options = new CacheStoreTestOptions( [
			'preferred_temp_dir'       => '',
			'last_known_cache_basedirs' => [
				'_default'               => [ 'invalid' ],
				'https://first.example/' => $this->baseUploads(),
			],
		] );

		$this->assertSame( $this->baseUploads().'/shield', $this->buildHandler( $options )->dir() );
		$this->assertSame( [
			'_default'               => [ 'invalid' ],
			'https://first.example/' => $this->baseUploads(),
		], $options->values[ 'last_known_cache_basedirs' ] );
	}

	public function test_second_request_with_different_url_does_not_create_url_key() :void {
		$options = new CacheStoreTestOptions( [
			'preferred_temp_dir'       => '',
			'last_known_cache_basedirs' => [
				'https://first.example/' => $this->baseUploads(),
			],
		] );

		$this->assertSame( $this->baseUploads().'/shield', $this->buildHandler( $options )->dir() );
		$this->wpGeneral->url = 'https://second.example/';

		$this->assertSame( $this->baseUploads().'/shield', $this->buildHandler( $options )->dir() );
		$this->assertSame( [
			'https://first.example/' => $this->baseUploads(),
		], $options->values[ 'last_known_cache_basedirs' ] );
	}

	public function test_existing_snapshot_root_is_selected_without_persisting_on_build() :void {
		$activeRoot = $this->baseUploads().'/shield';
		$this->mkdir( $activeRoot.'/ptguard-eeeeeeeeeeeeeeee' );
		\file_put_contents( $activeRoot.'/.ptguard-active.txt', 'ptguard-eeeeeeeeeeeeeeee' );
		$options = new CacheStoreTestOptions( [
			'preferred_temp_dir'       => '',
			'last_known_cache_basedirs' => [],
		] );

		$this->assertSame( $activeRoot, $this->buildHandler( $options )->dir() );
		$this->assertSame( [], $options->values[ 'last_known_cache_basedirs' ] );
	}

	public function test_handler_construction_does_not_mutate_options() :void {
		$options = $this->trackedOptions( [
			'preferred_temp_dir'       => '',
			'last_known_cache_basedirs' => [
				'https://first.example/' => $this->baseUploads(),
			],
		] );

		$this->buildHandler( $options );

		$this->assertSame( [], $options->setCalls );
	}

	private function buildHandler( CacheStoreTestOptions $options ) :CacheDirHandler {
		$plugin = new class extends ModCon {
			public function buildForTest() :CacheDirHandler {
				return $this->buildCacheDirHandler();
			}
		};
		CacheStoreTestController::install( $options, null, null, $plugin );
		return $plugin->buildForTest();
	}

	private function trackedOptions( array $values ) :CacheStoreTestOptions {
		return new class( $values ) extends CacheStoreTestOptions {
			public array $setCalls = [];

			public function optSet( string $key, $value ) :CacheStoreTestOptions {
				$this->setCalls[] = [
					'key'   => $key,
					'value' => $value,
				];
				return parent::optSet( $key, $value );
			}
		};
	}

	private function baseUploads() :string {
		return $this->normaliseCacheStorePath( WP_CONTENT_DIR.'/uploads' );
	}

	private function baseCache() :string {
		return $this->normaliseCacheStorePath( WP_CONTENT_DIR.'/cache' );
	}

	private function prepareWpContentDirs() :void {
		foreach ( [
			WP_CONTENT_DIR,
			$this->baseUploads(),
			$this->baseCache(),
		] as $dir ) {
			$this->mkdir( $dir );
		}
		foreach ( [
			WP_CONTENT_DIR.'/shield',
			$this->baseUploads().'/shield',
			$this->baseCache().'/shield',
		] as $dir ) {
			$this->removeDir( $this->normaliseCacheStorePath( $dir ) );
		}
	}

	private function makeTempDir( string $suffix ) :string {
		$dir = $this->normaliseCacheStorePath( \sys_get_temp_dir().'/shield-modcon-cache-'.$suffix.'-'.\uniqid() );
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
