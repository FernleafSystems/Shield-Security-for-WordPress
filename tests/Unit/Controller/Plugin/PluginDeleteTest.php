<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Controller\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginDelete;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestControllerFactory
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\CacheStore\{
	CacheStoreTestDb,
	CacheStoreTestFs,
	CacheStoreTestRequest,
	CacheStoreWordPressFunctions
};
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\CacheDirHandler;
use FernleafSystems\Wordpress\Services\Core\General;

class PluginDeleteTest extends BaseUnitTest {

	use CacheStoreWordPressFunctions;

	private CacheStoreTestDb $db;

	private CacheStoreTestFs $fs;

	private array $servicesSnapshot = [];

	private array $tempDirs = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		$this->db = new CacheStoreTestDb( 'wp_install_a_' );
		$this->fs = new CacheStoreTestFs();
		$this->registerCacheStoreWordPressFunctions( $this->fs, $this->makeTempDir( 'fallback' ) );

		ServicesState::installItems( [
			'service_request'   => new CacheStoreTestRequest(),
			'service_wpdb'      => $this->db,
			'service_wpfs'      => $this->fs,
			'service_wpgeneral' => new class extends General {
				public function canUseTransients() :bool {
					return false;
				}
			},
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

	public function test_run_deletes_only_the_current_external_namespace() :void {
		$sharedParent = $this->makeTempDir( 'shared-parent' );
		$controller = UnitTestControllerFactory::install( null, null, (object)[
			'cfg'               => (object)[
				'paths'      => [
					'cache' => 'shield',
				],
				'properties' => [
					'slug_parent' => 'icwp',
					'slug_plugin' => 'wpsf',
				],
			],
			'labels'            => (object)[
				'Name' => 'Shield',
			],
			'opts'              => new class {
				public function delete() :void {
				}
			},
			'db_con'            => $this->newDbCon(),
			'cache_dir_handler' => new CacheDirHandler( '', $sharedParent ),
		] );

		$rootA = $controller->cache_dir_handler->dir();
		$this->assertMatchesRegularExpression( '#/shield-v2-[a-f0-9]{32}$#', $rootA );
		$this->assertSame( $sharedParent, $this->normaliseCacheStorePath( \dirname( $rootA ) ) );

		$this->db->setBasePrefix( 'wp_install_b_' );
		$rootB = ( new CacheDirHandler( '', $sharedParent ) )->dir();
		$this->assertMatchesRegularExpression( '#/shield-v2-[a-f0-9]{32}$#', $rootB );
		$this->assertNotSame( $rootA, $rootB );

		$unsuffixedRoot = $sharedParent.'/shield';
		$legacyRoot = $sharedParent.'/shield-first-example-com';
		foreach ( [ $unsuffixedRoot, $legacyRoot ] as $root ) {
			$this->mkdir( $root );
		}

		$preservedSentinels = [
			$sharedParent.'/parent-sentinel.txt',
			$rootB.'/sibling-sentinel.txt',
			$unsuffixedRoot.'/unsuffixed-sentinel.txt',
			$legacyRoot.'/legacy-sentinel.txt',
		];
		foreach ( $preservedSentinels as $sentinel ) {
			\file_put_contents( $sentinel, 'preserve' );
		}
		\file_put_contents( $rootA.'/current-sentinel.txt', 'delete' );

		$this->db->setBasePrefix( 'wp_install_a_' );
		$controller->cache_dir_handler = new CacheDirHandler( '', $sharedParent );

		( new PluginDelete() )->run();

		$this->assertNotEmpty( $this->db->droppedTables );
		$this->assertDirectoryDoesNotExist( $rootA );
		foreach ( [ $sharedParent, $rootB, $unsuffixedRoot, $legacyRoot ] as $preservedRoot ) {
			$this->assertDirectoryExists( $preservedRoot );
		}
		foreach ( $preservedSentinels as $sentinel ) {
			$this->assertFileExists( $sentinel );
		}
	}

	private function newDbCon() :object {
		$dbCon = new class {
			public function reset() :void {
			}
		};

		foreach ( [
			'activity_logs_meta',
			'activity_logs',
			'activity_snapshots',
			'scan_results',
			'scan_result_item_meta',
			'scan_result_items',
			'scan_items',
			'scans',
			'file_locker',
			'malware',
			'crowdsec_signals',
			'bot_signals',
			'ip_rules',
			'mfa',
			'req_logs',
			'user_meta',
			'ip_meta',
			'ips',
			'events',
			'reports',
			'rules',
		] as $table ) {
			$dbCon->{$table} = new PluginDeleteTestDbHandler( $table );
		}

		return $dbCon;
	}

	private function makeTempDir( string $suffix ) :string {
		$dir = $this->normaliseCacheStorePath(
			\sys_get_temp_dir().'/cache-plugin-delete-'.$suffix.'-'.\uniqid()
		);
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

class PluginDeleteTestDbHandler {

	private string $table;

	public function __construct( string $table ) {
		$this->table = $table;
	}

	public static function GetTableReadyCache() :object {
		return new class {
			public function setReady( object $schema, bool $ready ) :void {
				unset( $schema, $ready );
			}
		};
	}

	public function getTableSchema() :object {
		return (object)[
			'table' => $this->table,
		];
	}
}
