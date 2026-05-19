<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Controller\Updates {

	function handle_upgrade_test_cache_purge() :void {
		HandleUpgradeCacheSpy::call( __FUNCTION__ );
	}

	use Brain\Monkey\Functions;
	use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SilentCaptcha\SilentCaptchaComplexity;
	use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\OptsHandler;
	use FernleafSystems\Wordpress\Plugin\Shield\Controller\Updates\HandleUpgrade;
	use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\{
		QueueRecovery,
		QueueWatchdog
	};
	use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\ScansController;
	use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\StartScansResult;
	use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModCon;
	use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
	use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan\Queue\Support\ScanQueueLifecycleHarness;
	use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
		PluginControllerInstaller,
		ServicesState,
		UnitTestControllerFactory,
		UnitTestRequest
	};
	use FernleafSystems\Wordpress\Services\Utilities\Data;
	use FernleafSystems\Wordpress\Services\Utilities\ServiceProviders;

	class HandleUpgradeTest extends BaseUnitTest {

		private array $servicesSnapshot = [];

		private object $serviceProviders;

		protected function setUp() :void {
			parent::setUp();
			HandleUpgradeCacheSpy::reset();
			Functions\when( 'FernleafSystems\Wordpress\Services\Utilities\is_email' )->alias(
				static fn( string $email ) :string => \filter_var( $email, \FILTER_VALIDATE_EMAIL ) ? $email : ''
			);
			Functions\when( 'FernleafSystems\Wordpress\Plugin\Shield\Controller\Updates\error_log' )->alias(
				static fn( string $message ) :bool => HandleUpgradeErrorLogSpy::log( $message )
			);
			HandleUpgradeErrorLogSpy::reset();
			$this->serviceProviders = new class extends ServiceProviders {
				public int $clears = 0;

				public function clearProviders() :void {
					$this->clears++;
				}
			};
			$this->servicesSnapshot = ServicesState::snapshot();
			ServicesState::installItems( [
				'service_data'             => new Data(),
				'service_request'          => new UnitTestRequest( [], '127.0.0.1', 1700000200 ),
				'service_serviceproviders' => $this->serviceProviders,
			] );
		}

		protected function tearDown() :void {
			ServicesState::restore( $this->servicesSnapshot );
			PluginControllerInstaller::reset();
			parent::tearDown();
		}

		public function test_execute_schedules_upgrade_cron_without_running_upgrade_work_inline() :void {
			$actions = [];
			$scheduled = [];
			$nextScheduledCalls = [];
			$this->captureUpgradeAction( $actions );
			$this->mockCronScheduling( $scheduled, false, $nextScheduledCalls );
			Functions\expect( 'do_action' )->never();

			$state = $this->installController();

			( new HandleUpgrade() )->execute();

			$this->assertArrayHasKey( 'icwp-wpsf-plugin-upgrade', $actions );
			$this->assertSame( [
				[
					'hook' => 'icwp-wpsf-plugin-upgrade',
					'args' => [ '1.0.0' ],
				],
			], $nextScheduledCalls );
			$this->assertSame( [
				'timestamp' => 1700000201,
				'hook'      => 'icwp-wpsf-plugin-upgrade',
				'args'      => [ '1.0.0' ],
			], $scheduled[ 0 ] ?? null );
			$this->assertSame( [], $state->scans->startedScans );
			$this->assertSame( 0, $state->plugin->deletedCrons );
			$this->assertSame( 0, $state->opts->stores );
			$this->assertSame( 0, $state->extensionHandler->forceChecks );
			$this->assertSame( [], HandleUpgradeCacheSpy::$calls );
			$this->assertSame( '2.0.0', $state->controller->cfg->previous_version );
			$this->assertTrue( $state->controller->cfg->persist_required );
		}

		public function test_execute_does_not_duplicate_already_scheduled_upgrade_cron() :void {
			$actions = [];
			$scheduled = [];
			$nextScheduledCalls = [];
			$this->captureUpgradeAction( $actions );
			$this->mockCronScheduling( $scheduled, 1700000210, $nextScheduledCalls );
			$this->installController();

			( new HandleUpgrade() )->execute();

			$this->assertArrayHasKey( 'icwp-wpsf-plugin-upgrade', $actions );
			$this->assertSame( [
				[
					'hook' => 'icwp-wpsf-plugin-upgrade',
					'args' => [ '1.0.0' ],
				],
			], $nextScheduledCalls );
			$this->assertSame( [], $scheduled );
		}

		public function test_execute_does_not_schedule_when_previous_version_is_current() :void {
			$actions = [];
			$scheduled = [];
			$nextScheduledCalls = [];
			$this->captureUpgradeAction( $actions );
			$this->mockCronScheduling( $scheduled, false, $nextScheduledCalls );
			$state = $this->installController( '2.0.0' );

			( new HandleUpgrade() )->execute();

			$this->assertArrayHasKey( 'icwp-wpsf-plugin-upgrade', $actions );
			$this->assertSame( [], $nextScheduledCalls );
			$this->assertSame( [], $scheduled );
			$this->assertSame( '2.0.0', $state->controller->cfg->previous_version );
			$this->assertTrue( $state->controller->cfg->persist_required );
		}

		public function test_scheduled_upgrade_callback_runs_upgrade_work() :void {
			$actions = [];
			$this->captureUpgradeAction( $actions );
			$state = $this->installController( '2.0.0' );

			( new HandleUpgrade() )->execute();
			$this->runCapturedUpgradeCallback( $actions );

			$this->assertSame( 1, $this->serviceProviders->clears );
			$this->assertSame( 1, $state->plugin->deletedCrons );
			$this->assertSame( 1, $state->opts->stores );
			$this->assertSame( 1, $state->extensionHandler->forceChecks );
			$this->assertCount( 1, $state->scans->startedScans );
			$this->assertTrue( $state->scans->startedScans[ 0 ]->isReady() );
		}

		public function test_cache_purge_failure_does_not_stop_scheduled_upgrade_worker() :void {
			$actions = [];
			$this->captureUpgradeAction( $actions );
			HandleUpgradeCacheSpy::$throws = [ __NAMESPACE__.'\handle_upgrade_test_cache_purge' ];
			$state = $this->installController( '2.0.0' );

			( new HandleUpgradeCachePurgeHarness() )->execute();
			$this->runCapturedUpgradeCallback( $actions );

			$this->assertSame( [ __NAMESPACE__.'\handle_upgrade_test_cache_purge' ], HandleUpgradeCacheSpy::$calls );
			$this->assertSame( 1, $state->extensionHandler->forceChecks );
			$this->assertCount( 1, $state->scans->startedScans );
		}

		public function test_scheduled_upgrade_deletes_plugin_crons_before_delegating_to_scan_start() :void {
			$actions = [];
			$this->captureUpgradeAction( $actions );
			$state = $this->installController( '2.0.0' );

			( new HandleUpgrade() )->execute();
			$this->runCapturedUpgradeCallback( $actions );

			$this->assertSame( [ 'delete_crons', 'start_scans' ], \array_values( \array_intersect(
				$state->operations->calls,
				[ 'delete_crons', 'start_scans' ]
			) ) );
		}

		public function test_scheduled_upgrade_does_not_require_scan_queue_component() :void {
			$actions = [];
			$this->captureUpgradeAction( $actions );
			$state = $this->installController( '2.0.0' );

			( new HandleUpgrade() )->execute();
			$this->runCapturedUpgradeCallback( $actions );

			$this->assertCount( 1, $state->scans->startedScans );
			$this->assertSame( [], HandleUpgradeErrorLogSpy::$messages );
		}

		public function test_scheduled_upgrade_does_not_log_all_already_exists_as_hard_failure() :void {
			$actions = [];
			$this->captureUpgradeAction( $actions );
			$state = $this->installController( '2.0.0' );
			$state->scans->nextResult = StartScansResult::fromRequested( [ 'afs', 'apc', 'wpv' ] )
														->addFailure( 'afs', StartScansResult::REASON_ALREADY_EXISTS )
														->addFailure( 'apc', StartScansResult::REASON_ALREADY_EXISTS )
														->addFailure( 'wpv', StartScansResult::REASON_ALREADY_EXISTS );

			( new HandleUpgrade() )->execute();
			$this->runCapturedUpgradeCallback( $actions );

			$this->assertSame( [], \array_filter(
				HandleUpgradeErrorLogSpy::$messages,
				static fn( string $message ) :bool => \str_contains( $message, 'already_exists' )
			) );
		}

		public function test_scheduled_upgrade_logs_true_hard_scan_start_failures() :void {
			$actions = [];
			$this->captureUpgradeAction( $actions );
			$state = $this->installController( '2.0.0' );
			$state->scans->nextResult = StartScansResult::fromRequested( [ 'afs' ] )
														->addFailure( 'afs', StartScansResult::REASON_CREATE_FAILED );

			( new HandleUpgrade() )->execute();
			$this->runCapturedUpgradeCallback( $actions );

			$this->assertSame( [ 'Shield scan start failures: afs:create_failed' ], HandleUpgradeErrorLogSpy::$messages );
		}

		public function test_scheduled_upgrade_recovers_prior_release_stale_rows_through_central_scan_start() :void {
			$harness = ( new ScanQueueLifecycleHarness() )->install();
			$actions = [];
			$this->captureUpgradeAction( $actions );
			$state = $this->installLifecycleUpgradeController( $harness );
			foreach ( [ 'afs', 'apc', 'wpv' ] as $slug ) {
				$this->seedPriorReleaseExhaustedRunningScan( $harness, $slug );
			}
			$harness->async->resetTransport();

			( new HandleUpgrade() )->execute();
			$this->runCapturedUpgradeCallback( $actions );

			$this->assertSame( [ 'delete_crons', 'start_scans' ], \array_values( \array_intersect(
				$state->operations->calls,
				[ 'delete_crons', 'start_scans' ]
			) ) );
			$this->assertSame( 1, $state->scans->startCalls );
			$this->assertTrue( $harness->async->hasScheduledHook( ( new QueueWatchdog() )->hook() ) );
			foreach ( [ 'afs', 'apc', 'wpv' ] as $slug ) {
				$rows = $this->scanRowsForSlug( $harness, $slug );
				$this->assertSame( 'failed', $rows[ 0 ][ 'status' ] );
				$this->assertSame( 'queued', $rows[ 1 ][ 'status' ] );
			}
		}

		public function test_scheduled_upgrade_retry_after_prior_release_recovery_does_not_repeat_all_already_exists_loop() :void {
			$harness = ( new ScanQueueLifecycleHarness() )->install();
			$actions = [];
			$this->captureUpgradeAction( $actions );
			$this->installLifecycleUpgradeController( $harness );
			foreach ( [ 'afs', 'apc', 'wpv' ] as $slug ) {
				$this->seedPriorReleaseExhaustedRunningScan( $harness, $slug );
			}

			( new HandleUpgrade() )->execute();
			$this->runCapturedUpgradeCallback( $actions );
			$this->runCapturedUpgradeCallback( $actions );

			$this->assertSame( [], \array_filter(
				HandleUpgradeErrorLogSpy::$messages,
				static fn( string $message ) :bool => \str_contains( $message, 'afs:already_exists' )
													  && \str_contains( $message, 'apc:already_exists' )
													  && \str_contains( $message, 'wpv:already_exists' )
			) );
			foreach ( [ 'afs', 'apc', 'wpv' ] as $slug ) {
				$rows = $this->scanRowsForSlug( $harness, $slug );
				$this->assertSame( 'failed', $rows[ 0 ][ 'status' ] );
				$this->assertSame( 'queued', $rows[ 1 ][ 'status' ] );
			}
		}

		public function test_extension_failure_does_not_stop_scheduled_upgrade_worker() :void {
			$actions = [];
			$this->captureUpgradeAction( $actions );
			$state = $this->installController(
				'2.0.0',
				true,
				true
			);

			( new HandleUpgrade() )->execute();
			$this->runCapturedUpgradeCallback( $actions );

			$this->assertSame( 1, $state->extensionHandler->forceChecks );
			$this->assertSame( 1, $state->throwingExtensionHandler->forceChecks );
			$this->assertSame( 0, $state->throwingHandlerLookup->forceChecks );
			$this->assertCount( 1, $state->scans->startedScans );
		}

		private function captureUpgradeAction( array &$actions ) :void {
			Functions\when( 'add_action' )->alias( static function (
				string $hook,
				callable $callback,
				int $priority = 10,
				int $acceptedArgs = 1
			) use ( &$actions ) :bool {
				$actions[ $hook ][] = [
					'callback'      => $callback,
					'priority'      => $priority,
					'accepted_args' => $acceptedArgs,
				];
				return true;
			} );
		}

		private function mockCronScheduling(
			array &$scheduled,
			$nextScheduled,
			array &$nextScheduledCalls
		) :void {
			Functions\when( 'wp_next_scheduled' )->alias(
				static function ( string $hook, array $args = [] ) use ( $nextScheduled, &$nextScheduledCalls ) {
					$nextScheduledCalls[] = [
						'hook' => $hook,
						'args' => $args,
					];
					return $nextScheduled;
				}
			);
			Functions\when( 'wp_schedule_single_event' )->alias(
				static function ( int $timestamp, string $hook, array $args = [] ) use ( &$scheduled ) :bool {
					$scheduled[] = [
						'timestamp' => $timestamp,
						'hook'      => $hook,
						'args'      => $args,
					];
					return true;
				}
			);
		}

		private function runCapturedUpgradeCallback( array $actions ) :void {
			$actions[ 'icwp-wpsf-plugin-upgrade' ][ 0 ][ 'callback' ]();
		}

		private function installController(
			string $previousVersion = '1.0.0',
			bool $includeThrowingExtension = false,
			bool $includeThrowingHandlerLookup = false
		) :object {
			$operations = new HandleUpgradeTestOperations();
			$cfg = new class( $previousVersion ) {
				public string $previous_version;
				public bool $persist_required = false;
				public array $properties = [
					'slug_parent' => 'icwp',
					'slug_plugin' => 'wpsf',
				];

				public function __construct( string $previousVersion ) {
					$this->previous_version = $previousVersion;
				}

				public function version() :string {
					return '2.0.0';
				}
			};

			$plugin = new HandleUpgradeTestPlugin( $operations );
			$opts = new HandleUpgradeTestOptions();
			$extensionHandler = new HandleUpgradeTestExtensionHandler();
			$extensions = [ new HandleUpgradeTestExtension( $extensionHandler ) ];
			$throwingExtensionHandler = null;
			if ( $includeThrowingExtension ) {
				$throwingExtensionHandler = new HandleUpgradeTestExtensionHandler( true );
				\array_unshift( $extensions, new HandleUpgradeTestExtension( $throwingExtensionHandler ) );
			}
			$throwingHandlerLookup = null;
			if ( $includeThrowingHandlerLookup ) {
				$throwingHandlerLookup = new HandleUpgradeTestExtensionHandler();
				\array_unshift( $extensions, new HandleUpgradeTestExtension( $throwingHandlerLookup, true ) );
			}
			$scans = new HandleUpgradeTestScans( $operations );

			$controller = UnitTestControllerFactory::install( null, null, (object)[
				'cfg'                   => $cfg,
				'plugin'                => $plugin,
				'opts'                  => $opts,
				'extensions_controller' => new HandleUpgradeTestExtensionsController( $extensions ),
				'comps'                 => (object)[
					'scans' => $scans,
				],
			] );

			return (object)[
				'controller'               => $controller,
				'operations'               => $operations,
				'plugin'                   => $plugin,
				'opts'                     => $opts,
				'extensionHandler'         => $extensionHandler,
				'throwingExtensionHandler' => $throwingExtensionHandler,
				'throwingHandlerLookup'    => $throwingHandlerLookup,
				'scans'                    => $scans,
			];
		}

		private function installLifecycleUpgradeController( ScanQueueLifecycleHarness $harness ) :object {
			ServicesState::mergeItems( [
				'service_data'             => new Data(),
				'service_serviceproviders' => $this->serviceProviders,
			] );
			$operations = new HandleUpgradeTestOperations();
			$cfg = new class {
				public string $previous_version = '2.0.0';
				public bool $persist_required = false;
				public array $properties = [
					'slug_parent' => 'icwp',
					'slug_plugin' => 'wpsf',
				];

				public function version() :string {
					return '2.0.0';
				}
			};

			$plugin = new HandleUpgradeTestPlugin( $operations );
			$opts = new HandleUpgradeTestOptions();
			$extensionHandler = new HandleUpgradeTestExtensionHandler();
			$controller = $harness->controller;
			$scanFacade = new HandleUpgradeLifecycleScans( $controller->comps->scans, $operations );
			$controller->cfg = $cfg;
			$controller->plugin = $plugin;
			$controller->opts = $opts;
			$controller->extensions_controller = new HandleUpgradeTestExtensionsController( [
				new HandleUpgradeTestExtension( $extensionHandler ),
			] );
			$controller->comps->scans = $scanFacade;
			PluginControllerInstaller::install( $controller );

			return (object)[
				'controller'       => $controller,
				'operations'       => $operations,
				'plugin'           => $plugin,
				'opts'             => $opts,
				'extensionHandler' => $extensionHandler,
				'scans'            => $scanFacade,
			];
		}

		private function seedPriorReleaseExhaustedRunningScan( ScanQueueLifecycleHarness $harness, string $slug ) :void {
			$scanID = $harness->insertScan( [
				'scan'            => $slug,
				'status'          => 'running',
				'ready_at'        => 1699999000,
				'last_process_at' => 1699999000,
				'started_at'      => 1699999000,
			] );
			$harness->insertScanItem( $scanID, [ $slug.'-a' ], 1699999000, 0, QueueRecovery::MAX_ITEM_ATTEMPTS );
		}

		private function scanRowsForSlug( ScanQueueLifecycleHarness $harness, string $slug ) :array {
			return \array_values( \array_filter(
				$harness->scanRows(),
				static fn( array $row ) :bool => $row[ 'scan' ] === $slug
			) );
		}
	}

	class HandleUpgradeErrorLogSpy {

		public static array $messages = [];

		public static function reset() :void {
			self::$messages = [];
		}

		public static function log( string $message ) :bool {
			self::$messages[] = $message;
			return true;
		}
	}

	class HandleUpgradeCacheSpy {

		public static array $calls = [];

		public static array $throws = [];

		public static function reset() :void {
			self::$calls = [];
			self::$throws = [];
		}

		public static function call( string $function ) :void {
			self::$calls[] = $function;
			if ( \in_array( $function, self::$throws, true ) ) {
				throw new \RuntimeException( sprintf( '%s failed', $function ) );
			}
		}
	}

	class HandleUpgradeCachePurgeHarness extends HandleUpgrade {

		protected const CACHE_PURGE_FUNCTIONS = [
			__NAMESPACE__.'\handle_upgrade_test_cache_purge',
		];
	}

	class HandleUpgradeTestOperations {

		public array $calls = [];
	}

	class HandleUpgradeTestPlugin extends ModCon {

		public int $deletedCrons = 0;

		private HandleUpgradeTestOperations $operations;

		public function __construct( HandleUpgradeTestOperations $operations ) {
			$this->operations = $operations;
		}

		public function deleteAllPluginCrons() {
			$this->deletedCrons++;
			$this->operations->calls[] = 'delete_crons';
		}
	}

	class HandleUpgradeTestOptions {

		public int $stores = 0;

		public array $mod_opts_all;

		private bool $changed = true;

		private array $values = [
			'enable_admin_login_email_notification' => '',
			'instant_alert_admin_login'             => 'disabled',
			'instant_alert_firewall_block'          => 'disabled',
			'block_send_email'                      => 'N',
			'allow_backupcodes'                     => 'N',
			'display_plugin_badge'                  => 'disabled',
			'silentcaptcha_complexity'              => SilentCaptchaComplexity::MEDIUM,
		];

		public function __construct() {
			$this->mod_opts_all = [
				'values' => [
					OptsHandler::TYPE_FREE => $this->values,
					OptsHandler::TYPE_PRO  => [],
				],
			];
		}

		public function hasChanges() :bool {
			return $this->changed;
		}

		public function optGet( string $key ) {
			return $this->values[ $key ] ?? null;
		}

		public function optIs( string $key, $value ) :bool {
			return $this->optGet( $key ) === $value;
		}

		public function optSet( string $key, $value ) :self {
			if ( ( $this->values[ $key ] ?? null ) !== $value ) {
				$this->changed = true;
				$this->values[ $key ] = $value;
				$this->mod_opts_all[ 'values' ][ OptsHandler::TYPE_FREE ][ $key ] = $value;
			}
			return $this;
		}

		public function store() :void {
			$this->stores++;
			$this->changed = false;
		}
	}

	class HandleUpgradeTestExtension {

		private HandleUpgradeTestExtensionHandler $handler;

		private bool $throws;

		public function __construct( HandleUpgradeTestExtensionHandler $handler, bool $throws = false ) {
			$this->handler = $handler;
			$this->throws = $throws;
		}

		public function getUpgradesHandler() :HandleUpgradeTestExtensionHandler {
			if ( $this->throws ) {
				throw new \RuntimeException( 'handler lookup failed' );
			}
			return $this->handler;
		}
	}

	class HandleUpgradeTestExtensionHandler {

		public int $forceChecks = 0;

		private bool $throws;

		public function __construct( bool $throws = false ) {
			$this->throws = $throws;
		}

		public function forceUpdateCheck() :void {
			$this->forceChecks++;
			if ( $this->throws ) {
				throw new \RuntimeException( 'extension failed' );
			}
		}
	}

	class HandleUpgradeTestExtensionsController {

		private array $extensions;

		public function __construct( array $extensions ) {
			$this->extensions = $extensions;
		}

		public function canRunExtensions() :bool {
			return true;
		}

		public function getAvailableExtensions() :array {
			return $this->extensions;
		}
	}

	class HandleUpgradeTestScans {

		public array $startedScans = [];

		public ?StartScansResult $nextResult = null;

		private HandleUpgradeTestOperations $operations;

		public function __construct( HandleUpgradeTestOperations $operations ) {
			$this->operations = $operations;
		}

		public function getAllScanCons() :array {
			return [
				new HandleUpgradeTestScan( true ),
				new HandleUpgradeTestScan( false ),
			];
		}

		public function startNewScans( array $scans ) :StartScansResult {
			$this->operations->calls[] = 'start_scans';
			$this->startedScans = $scans;
			return $this->nextResult ?? StartScansResult::fromRequested( [ 'afs' ] )->addStarted( 'afs', 1 );
		}
	}

	class HandleUpgradeTestScan {

		private bool $ready;

		public function __construct( bool $ready ) {
			$this->ready = $ready;
		}

		public function isReady() :bool {
			return $this->ready;
		}
	}

	class HandleUpgradeLifecycleScans {

		public int $startCalls = 0;

		private object $component;

		private HandleUpgradeTestOperations $operations;

		public function __construct( object $component, HandleUpgradeTestOperations $operations ) {
			$this->component = $component;
			$this->operations = $operations;
		}

		public function getAllScanCons() :array {
			return $this->component->getAllScanCons();
		}

		public function getScanCon( string $slug ) {
			return $this->component->getScanCon( $slug );
		}

		public function startNewScans( array $scans ) :StartScansResult {
			$this->startCalls++;
			$this->operations->calls[] = 'start_scans';
			return ( new HandleUpgradeLifecycleScansController() )->startNewScans( $scans );
		}
	}

	class HandleUpgradeLifecycleScansController extends ScansController {

		public function getScanCon( string $slug ) {
			return self::con()->comps->scans->getScanCon( $slug );
		}

		public function canStartScans( bool $isCli = false ) :bool {
			unset( $isCli );
			return true;
		}
	}
}
