<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Controller\Updates;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Updates\HandleUpgrade;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\StartScansResult;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState
};
use FernleafSystems\Wordpress\Services\Utilities\Data;
use FernleafSystems\Wordpress\Services\Utilities\ServiceProviders;

class HandleUpgradeTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::installItems( [
			'service_data' => new Data(),
			'service_serviceproviders' => new class extends ServiceProviders {
				public function clearProviders() :void {
				}
			},
		] );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_execute_runs_upgrade_inline_and_starts_ready_scans() :void {
		$actions = [];
		$startedScans = [];
		Functions\when( 'add_action' )->alias( static function ( string $hook, callable $callback ) use ( &$actions ) :bool {
			$actions[ $hook ][] = $callback;
			return true;
		} );
		Functions\when( 'do_action' )->alias( static function ( string $hook, ...$args ) use ( &$actions ) :void {
			foreach ( $actions[ $hook ] ?? [] as $callback ) {
				$callback( ...$args );
			}
		} );
		Functions\when( 'wp_next_scheduled' )->justReturn( false );

		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->cfg = new class {
			public string $previous_version = '1.0.0';
			public bool $persist_required = false;
			public array $properties = [
				'slug_parent' => 'icwp',
				'slug_plugin' => 'wpsf',
			];

			public function version() :string {
				return '2.0.0';
			}
		};
		$controller->plugin = new class extends ModCon {
			public int $deletedCrons = 0;

			public function deleteAllPluginCrons() :void {
				$this->deletedCrons++;
			}
		};
		$controller->opts = new class {
			private array $values = [
				'enable_admin_login_email_notification' => '',
				'instant_alert_admin_login' => 'disabled',
				'instant_alert_firewall_block' => 'disabled',
				'block_send_email' => 'N',
			];

			public function hasChanges() :bool {
				return false;
			}

			public function optGet( string $key ) {
				return $this->values[ $key ] ?? null;
			}

			public function optIs( string $key, $value ) :bool {
				return $this->optGet( $key ) === $value;
			}

			public function optSet( string $key, $value ) :self {
				$this->values[ $key ] = $value;
				return $this;
			}

			public function store() :void {
			}
		};
		$controller->extensions_controller = new class {
			public function canRunExtensions() :bool {
				return false;
			}

			public function getAvailableExtensions() :array {
				return [];
			}
		};
		$controller->comps = (object)[
			'scans' => new class( $startedScans ) {
				public array $startedScans;

				public function __construct( array &$startedScans ) {
					$this->startedScans = &$startedScans;
				}

				public function getAllScanCons() :array {
					return [
						new class {
							public function isReady() :bool {
								return true;
							}
						},
						new class {
							public function isReady() :bool {
								return false;
							}
						},
					];
				}

				public function startNewScans( array $scans ) :StartScansResult {
					$this->startedScans = $scans;
					return StartScansResult::fromRequested( [ 'afs' ] )->addStarted( 'afs', 1 );
				}
			},
		];
		PluginControllerInstaller::install( $controller );

		( new HandleUpgrade() )->execute();

		$this->assertCount( 1, $startedScans );
		$this->assertTrue( $startedScans[ 0 ]->isReady() );
		$this->assertSame( '2.0.0', $controller->cfg->previous_version );
		$this->assertTrue( $controller->cfg->persist_required );
	}
}
