<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\VirtualPatching;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestRequest
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\VirtualPatching\Ruleset\PatchFixtures;
use FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\{
	BlockLog,
	RequestGuard,
	VirtualPatchingCon
};
use FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\Ruleset\RulesetFile;
use FernleafSystems\Wordpress\Services\Core\{
	Fs,
	General,
	Plugins
};

class VirtualPatchingConTest extends BaseUnitTest {

	use PatchFixtures;
	use TempDirLifecycleTrait;

	private const SHIELD = 'wp-simple-firewall/icwp-wpsf.php';

	private string $dir;

	private array $actions = [];

	private array $events = [];

	private array $site = [];

	private object $state;

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		if ( !\defined( 'HOUR_IN_SECONDS' ) ) {
			\define( 'HOUR_IN_SECONDS', 3600 );
		}
		$this->dir = $this->createTrackedTempDir();
		$this->state = (object)[
			'enabled'    => true,
			'capability' => true,
			'ipSource'   => 'REMOTE_ADDR',
			'detected'   => 'HTTP_CF_CONNECTING_IP',
			'isCron'     => false,
		];
		$this->site = [
			'admin'       => false,
			'ajax'        => false,
			'manager'     => false,
			'multisite'   => false,
			'alloptions'  => [
				'active_plugins' => \serialize( [ 'example/example.php', self::SHIELD ] ),
				'stylesheet'     => 'child',
				'template'       => 'parent',
			],
			'plugins'     => [ 'example/example.php' => [ 'Version' => '1.4.0' ], self::SHIELD => [ 'Version' => '22.3.0' ] ],
		];

		Functions\when( 'add_action' )->alias( function ( string $hook, $callback ) :bool {
			$this->actions[ $hook ][] = $callback;
			return true;
		} );
		Functions\when( 'is_admin' )->alias( fn() :bool => $this->site[ 'admin' ] );
		Functions\when( 'wp_doing_ajax' )->alias( fn() :bool => $this->site[ 'ajax' ] );
		Functions\when( 'current_user_can' )->alias( fn() :bool => $this->site[ 'manager' ] );
		Functions\when( 'is_multisite' )->alias( fn() :bool => $this->site[ 'multisite' ] );
		Functions\when( 'wp_load_alloptions' )->alias( fn() :array => $this->site[ 'alloptions' ] );
		Functions\when( 'maybe_unserialize' )->alias( fn( $value ) => \is_string( $value ) && \str_starts_with( $value, 'a:' ) ? \unserialize( $value ) : $value );
		Functions\when( 'wp_get_themes' )->alias( fn() :array => [
			'child'  => new class { public function get( string $key ) :string { return '1.0'; } },
			'parent' => new class { public function get( string $key ) :string { return '2.0'; } },
		] );

		$this->servicesSnapshot = ServicesState::snapshot();
		$state = $this->state;
		$plugins = $this->site[ 'plugins' ];
		ServicesState::mergeItems( [
			'service_request'   => new UnitTestRequest( [], '127.0.0.1', 1700000000 ),
			'service_wpfs'      => new class extends Fs {
				public function fs() {
					return null;
				}

				public function mkdir( $path ) {
					return \is_dir( $path ) || \mkdir( $path, 0755, true );
				}
			},
			'service_wpplugins' => new class( $plugins ) extends Plugins {
				private array $installed;

				public function __construct( array $installed ) {
					$this->installed = $installed;
				}

				public function getPlugins() :array {
					return $this->installed;
				}
			},
			'service_wpgeneral' => new class( $state ) extends General {
				private object $state;

				public function __construct( object $state ) {
					$this->state = $state;
				}

				public function isCron() :bool {
					return $this->state->isCron;
				}
			},
		] );

		$this->installController();
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testApplicablePatchIsExported() :void {
		$this->writePatches( [ self::patch() ] );

		$this->con()->export();

		$this->assertSame( $this->dir.'/virtual-patching/ruleset.php', get_option( RequestGuard::OPTION_RULESET_PATH ) );
		$ruleset = RulesetFile::read( $this->dir.'/virtual-patching/ruleset.php' );
		$this->assertSame( [ 'candidate-1' ], \array_column( $ruleset[ 'rules' ], 'id' ) );
		$this->assertSame( [ 'example/example.php', self::SHIELD ], $ruleset[ 'active_plugins' ] );
		$this->assertSame( [ 'child', 'parent' ], $ruleset[ 'active_themes' ] );
		$this->assertSame( [ 'ip_source' => 'REMOTE_ADDR', 'ips' => [ '198.51.100.0/24' ] ], $ruleset[ 'bypass' ] );
		$this->assertSame( $this->dir.'/virtual-patching/events.php', $ruleset[ 'events' ] );
		$this->assertSame( 1, get_option( VirtualPatchingCon::OPTION_STATUS )[ 'rules' ] );
	}

	public function testActivePluginsAreReadWithoutRequestFilters() :void {
		Functions\when( 'get_option' )->justReturn( [ 'unrelated/unrelated.php' ] );
		$this->writePatches( [ self::patch() ] );

		$this->con()->export();

		$this->assertFileExists( $this->dir.'/virtual-patching/ruleset.php' );
		$this->assertSame(
			[ 'example/example.php', self::SHIELD ],
			RulesetFile::read( $this->dir.'/virtual-patching/ruleset.php' )[ 'active_plugins' ]
		);
	}

	public function testRulesetIsRemovedWhenVirtualPatchingIsUnavailable() :void {
		$cases = [
			'features disabled'    => fn() => $this->state->enabled = false,
			'capability missing'   => fn() => $this->state->capability = false,
			'multisite'            => fn() => $this->site[ 'multisite' ] = true,
			'no patches'           => fn() => $this->writePatches( [] ),
			'nothing applies'      => fn() => $this->writePatches( [ self::patch( [], [ 'targetExtension' => [ 'kind' => 'plugin', 'slug' => 'other', 'affectedVersionRanges' => [ [ 'firstAffectedVersion' => '1', 'lastAffectedVersion' => '2' ] ] ] ] ) ] ),
		];
		foreach ( $cases as $label => $change ) {
			$this->state->enabled = true;
			$this->state->capability = true;
			$this->site[ 'multisite' ] = false;
			$this->writePatches( [ self::patch() ] );
			$this->con()->export();
			$this->assertFileExists( $this->dir.'/virtual-patching/ruleset.php', $label );

			$change();
			$this->con()->export();

			$this->assertFileDoesNotExist( $this->dir.'/virtual-patching/ruleset.php', $label );
			$this->assertFalse( get_option( RequestGuard::OPTION_RULESET_PATH ), $label );
		}
	}

	public function testDeactivatingShieldLeavesNoRuleset() :void {
		$this->writePatches( [ self::patch() ] );
		$con = $this->con();
		$con->export();
		$con->execute();

		// WordPress fires deactivate_{plugin}, which Shield turns into pre_deactivate_plugin, then deactivated_plugin.
		$this->fire( 'icwp-wpsf-pre_deactivate_plugin' );
		$this->fire( 'deactivated_plugin', self::SHIELD );
		$this->fire( 'shutdown' );

		$this->assertFileDoesNotExist( $this->dir.'/virtual-patching/ruleset.php' );
		$this->assertFalse( get_option( RequestGuard::OPTION_RULESET_PATH ) );
	}

	public function testDeactivatingAnotherPluginRebuildsAtShutdown() :void {
		$this->writePatches( [ self::patch() ] );
		$con = $this->con();
		$con->execute();

		$this->fire( 'deactivated_plugin', 'other/other.php' );
		$this->assertFileDoesNotExist( $this->dir.'/virtual-patching/ruleset.php' );
		$this->fire( 'shutdown' );

		$this->assertFileExists( $this->dir.'/virtual-patching/ruleset.php' );
	}

	public function testSettingsSaveRebuildsOnlyWhenSettingsChanged() :void {
		$this->writePatches( [ self::patch() ] );
		$this->con()->execute();

		$this->fire( 'icwp-wpsf-after_pre_options_store', false );
		$this->fire( 'shutdown' );
		$this->assertFileDoesNotExist( $this->dir.'/virtual-patching/ruleset.php' );

		$this->fire( 'icwp-wpsf-after_pre_options_store', true );
		$this->fire( 'shutdown' );
		$this->assertFileExists( $this->dir.'/virtual-patching/ruleset.php' );
	}

	public function testMissingRulesetIsBuiltOnlyOnAnAdministratorsAdminPageAtMostHourly() :void {
		$this->writePatches( [ self::patch() ] );
		$this->site[ 'admin' ] = true;

		$attempts = [
			'visitor'          => [ 'manager' => false, 'ajax' => false ],
			'administrator AJAX' => [ 'manager' => true, 'ajax' => true ],
		];
		foreach ( $attempts as $label => $site ) {
			$this->site = \array_merge( $this->site, $site );
			$this->installController();
			$this->con()->execute();
			$this->fire( 'shutdown' );
			$this->assertFileDoesNotExist( $this->dir.'/virtual-patching/ruleset.php', $label );
		}

		update_option( VirtualPatchingCon::OPTION_STATUS, [ 'ts' => 1700000000 - 600 ] );
		$this->site = \array_merge( $this->site, [ 'manager' => true, 'ajax' => false ] );
		$this->installController();
		$this->con()->execute();
		$this->fire( 'shutdown' );
		$this->assertFileDoesNotExist( $this->dir.'/virtual-patching/ruleset.php', 'checked recently' );

		update_option( VirtualPatchingCon::OPTION_STATUS, [ 'ts' => 1700000000 - 7200 ] );
		$this->installController();
		$this->con()->execute();
		$this->fire( 'shutdown' );
		$this->assertFileExists( $this->dir.'/virtual-patching/ruleset.php' );
	}

	public function testRemovalAlsoClearsTheCurrentPathWithoutTheOption() :void {
		$this->writePatches( [ self::patch() ] );
		$this->con()->export();
		delete_option( RequestGuard::OPTION_RULESET_PATH );

		$this->con()->removeRuleset();

		$this->assertFileDoesNotExist( $this->dir.'/virtual-patching/ruleset.php' );
	}

	public function testAutoDetectedIpSourceComesOnlyFromAnAdministratorsRequest() :void {
		$this->state->ipSource = 'AUTO_DETECT_IP';
		$this->writePatches( [ self::patch() ] );

		$this->con()->export();
		$this->assertNull( $this->exportedIpSource(), 'visitor request, nothing exported before' );

		$this->site[ 'manager' ] = true;
		$this->con()->export();
		$this->assertSame( 'HTTP_CF_CONNECTING_IP', $this->exportedIpSource() );

		$this->site[ 'manager' ] = false;
		$this->state->detected = 'HTTP_X_FORWARDED_FOR';
		$this->con()->export();
		$this->assertSame( 'HTTP_CF_CONNECTING_IP', $this->exportedIpSource(), 'previous source kept' );

		$this->site[ 'manager' ] = true;
		$this->state->isCron = true;
		$this->con()->export();
		$this->assertSame( 'HTTP_CF_CONNECTING_IP', $this->exportedIpSource(), 'cron keeps the previous source' );
	}

	public function testImportRecordsAtMostFiveHundredBlocksAndCountsTheRest() :void {
		$events = $this->dir.'/virtual-patching/events.php';
		\mkdir( \dirname( $events ), 0755, true );
		for ( $i = 0; $i < 502; $i++ ) {
			BlockLog::append( $events, [ 'rule' => 'r'.$i, 'advisories' => [ 'CVE-2026-0001', 'CVE-2026-0002' ], 'ip' => '203.0.113.9', 'method' => 'POST', 'path' => '/x' ] );
		}

		$this->con()->runHourlyCron();

		$this->assertCount( 500, $this->events );
		$this->assertSame( [ 'virtual_patch_block', [
			'audit_params' => [
				'rule'       => 'r0',
				'advisories' => 'CVE-2026-0001, CVE-2026-0002',
				'ip'         => '203.0.113.9',
				'method'     => 'POST',
				'path'       => '/x',
			],
		] ], $this->events[ 0 ] );
		$this->assertSame( 2, get_option( VirtualPatchingCon::OPTION_STATUS )[ 'import_dropped' ] );
		$this->assertSame( [], BlockLog::read( $events ) );
	}

	private function con() :VirtualPatchingCon {
		return new class extends VirtualPatchingCon {
			protected function bypassIps() :array {
				return [ '198.51.100.0/24' ];
			}
		};
	}

	private function installController() :void {
		$dir = $this->dir;
		$state = $this->state;
		$events = &$this->events;
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->cfg = (object)[ 'properties' => [ 'slug_parent' => 'icwp', 'slug_plugin' => 'wpsf' ] ];
		$controller->base_file = self::SHIELD;
		$controller->cache_dir_handler = new class( $dir ) {
			private string $dir;

			public function __construct( string $dir ) {
				$this->dir = $dir;
			}

			public function cacheItemPath( string $item ) :string {
				return $this->dir.'/'.$item;
			}
		};
		$controller->caps = new class( $state ) {
			private object $state;

			public function __construct( object $state ) {
				$this->state = $state;
			}

			public function canVirtualPatching() :bool {
				return $this->state->capability;
			}
		};
		$controller->comps = (object)[
			'opts_lookup' => new class( $state ) {
				private object $state;

				public function __construct( object $state ) {
					$this->state = $state;
				}

				public function isPluginEnabled() :bool {
					return $this->state->enabled;
				}

				public function ipSource() :string {
					return $this->state->ipSource;
				}
			},
			'events'      => new class( $events ) {
				private array $events;

				public function __construct( array &$events ) {
					$this->events = &$events;
				}

				public function fireEvent( string $event, array $meta = [] ) :void {
					$this->events[] = [ $event, $meta ];
				}
			},
		];
		$controller->this_req = (object)[
			'request' => new class( $state ) {
				private object $state;

				public function __construct( object $state ) {
					$this->state = $state;
				}

				public function getIpDetector() :object {
					$state = $this->state;
					return new class( $state ) {
						private object $state;

						public function __construct( object $state ) {
							$this->state = $state;
						}

						public function getPublicRequestSource() :string {
							return $this->state->detected;
						}
					};
				}
			},
		];
		$this->actions = [];
		PluginControllerInstaller::install( $controller );
	}

	private function fire( string $hook, ...$args ) :void {
		foreach ( $this->actions[ $hook ] ?? [] as $callback ) {
			$callback( ...$args );
		}
	}

	private function writePatches( array $patches ) :void {
		if ( !\is_dir( $this->dir.'/virtual-patching' ) ) {
			\mkdir( $this->dir.'/virtual-patching', 0755, true );
		}
		\file_put_contents( $this->dir.'/virtual-patching/patches.json', \json_encode( [ 'patches' => $patches ] ) );
	}

	private function exportedIpSource() :?string {
		return RulesetFile::read( $this->dir.'/virtual-patching/ruleset.php' )[ 'bypass' ][ 'ip_source' ];
	}
}
