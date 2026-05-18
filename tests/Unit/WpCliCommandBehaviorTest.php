<?php declare( strict_types=1 );

namespace {
	if ( !\class_exists( 'WP_CLI' ) ) {
		class WP_CLI {
			public static array $commands = [];
			public static array $events = [];

			public static function reset() :void {
				self::$commands = [];
				self::$events = [];
			}

			public static function add_command( $name, $callable, $args = [] ) :void {
				self::$commands[ (string)$name ] = [
					'callable' => $callable,
					'args'     => $args,
				];
			}

			public static function log( $message ) :void {
				self::$events[] = [
					'type'    => 'log',
					'message' => (string)$message,
				];
			}

			public static function success( $message ) :void {
				self::$events[] = [
					'type'    => 'success',
					'message' => (string)$message,
				];
			}

			public static function warning( $message ) :void {
				self::$events[] = [
					'type'    => 'warning',
					'message' => (string)$message,
				];
			}

			public static function error( $message ) :void {
				self::$events[] = [
					'type'    => 'error',
					'message' => (string)$message,
				];
				throw new \WP_CLI\ExitException( (string)$message, 1 );
			}

			public static function confirm( $message ) :void {
				self::$events[] = [
					'type'    => 'confirm',
					'message' => (string)$message,
				];
			}

			public static function halt( $code = 0 ) :void {
				throw new \WP_CLI\ExitException( 'halt', (int)$code );
			}
		}
	}
}

namespace WP_CLI {
	if ( !\class_exists( __NAMESPACE__.'\\ExitException' ) ) {
		class ExitException extends \RuntimeException {
		}
	}
}

namespace WP_CLI\Utils {
	class Recorder {
		public static array $formattedItems = [];

		public static function reset() :void {
			self::$formattedItems = [];
		}
	}

	function get_flag_value( array $assocArgs, string $key, $default = false ) {
		return \array_key_exists( $key, $assocArgs ) ? $assocArgs[ $key ] : $default;
	}

	function format_items( string $format, array $items, array $fields ) :void {
		Recorder::$formattedItems[] = [
			'format' => $format,
			'items'  => $items,
			'fields' => $fields,
		];
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules {
	if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
		function shield_security_get_plugin() {
			return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
		}
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit {

	use Brain\Monkey\Functions;
	use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\StartScansResult;
	use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModCon;
	use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
		PluginControllerInstaller,
		ServicesState,
		UnitTestControllerFactory
	};
	use FernleafSystems\Wordpress\Plugin\Shield\WpCli\Cmds\{
		ConfigOptGet,
		ConfigOptSet,
		ConfigOptsList,
		PluginReset,
		ScansRun
	};

	class WpCliCommandBehaviorTest extends BaseUnitTest {

		private array $servicesSnapshot = [];

		protected function setUp() :void {
			parent::setUp();
			\WP_CLI::reset();
			\WP_CLI\Utils\Recorder::reset();
			Functions\when( '__' )->returnArg();
			$this->servicesSnapshot = ServicesState::snapshot();
		}

		protected function tearDown() :void {
			ServicesState::restore( $this->servicesSnapshot );
			PluginControllerInstaller::reset();
			parent::tearDown();
		}

		public function test_core_wp_cli_commands_register_expected_contracts() :void {
			$this->installController();

			foreach ( [
				new ConfigOptsList(),
				new ConfigOptGet(),
				new ConfigOptSet(),
				new PluginReset(),
				new ScansRun(),
			] as $command ) {
				$command->execute();
			}

			$this->assertArrayHasKey( 'shield opt-list', \WP_CLI::$commands );
			$this->assertArrayHasKey( 'shield opt-get', \WP_CLI::$commands );
			$this->assertArrayHasKey( 'shield opt-set', \WP_CLI::$commands );
			$this->assertArrayHasKey( 'shield reset', \WP_CLI::$commands );
			$this->assertArrayHasKey( 'shield scans run', \WP_CLI::$commands );
			$this->assertSame( 'before_wp_load', \WP_CLI::$commands[ 'shield opt-get' ][ 'args' ][ 'when' ] );
			$this->assertSame(
				[ 'key' ],
				\array_column( \WP_CLI::$commands[ 'shield opt-get' ][ 'args' ][ 'synopsis' ], 'name' )
			);
			$this->assertSame(
				[ 'key', 'value' ],
				\array_column( \WP_CLI::$commands[ 'shield opt-set' ][ 'args' ][ 'synopsis' ], 'name' )
			);
			$this->assertContains(
				'force',
				\array_column( \WP_CLI::$commands[ 'shield reset' ][ 'args' ][ 'synopsis' ], 'name' )
			);
			$this->assertContains(
				'all',
				\array_column( \WP_CLI::$commands[ 'shield scans run' ][ 'args' ][ 'synopsis' ], 'name' )
			);
		}

		public function test_config_option_commands_read_list_and_write_option_state() :void {
			$state = $this->installController();

			( new ConfigOptGet() )->execCmd( [], [ 'key' => 'wp_cli_test_option' ] );
			( new ConfigOptSet() )->execCmd( [], [
				'key'   => 'wp_cli_test_option',
				'value' => 'N',
			] );
			( new ConfigOptsList() )->execCmd( [], [
				'format' => 'json',
			] );

			$this->assertSame( 'N', $state->opts->values[ 'wp_cli_test_option' ] );
			$this->assertSame( [
				[
					'key'   => 'wp_cli_test_option',
					'value' => 'N',
				],
			], $state->opts->sets );
			$this->assertSame( 'log', \WP_CLI::$events[ 0 ][ 'type' ] ?? null );
			$this->assertContains( 'success', \array_column( \WP_CLI::$events, 'type' ) );
			$this->assertSame( 'json', \WP_CLI\Utils\Recorder::$formattedItems[ 0 ][ 'format' ] ?? null );
			$this->assertSame(
				[ 'key', 'name', 'current' ],
				\WP_CLI\Utils\Recorder::$formattedItems[ 0 ][ 'fields' ] ?? []
			);
			$this->assertSame(
				'wp_cli_test_option',
				\WP_CLI\Utils\Recorder::$formattedItems[ 0 ][ 'items' ][ 0 ][ 'key' ] ?? null
			);
		}

		public function test_reset_force_bypasses_confirmation_and_runs_reset_contract() :void {
			$state = $this->installController();
			$this->installResetServices();

			( new PluginReset() )->execCmd( [], [ 'force' => true ] );

			$this->assertTrue( $state->controller->plugin_reset );
			$this->assertSame( 1, $state->opts->resetCalls );
			$this->assertSame( 1, $state->opts->deleteCalls );
			$this->assertNotContains( 'confirm', \array_column( \WP_CLI::$events, 'type' ) );
			$this->assertContains( 'success', \array_column( \WP_CLI::$events, 'type' ) );
		}

		public function test_scans_run_reports_no_selection_as_wp_cli_error() :void {
			$state = $this->installController();

			try {
				( new ScansRun() )->execCmd( [], [] );
				$this->fail( 'Expected WP-CLI error for missing scan selection.' );
			}
			catch ( \WP_CLI\ExitException $e ) {
				$this->assertSame( 1, $e->getCode() );
			}

			$this->assertSame( [], $state->scans->startedScans );
			$this->assertSame( [ 'error' ], \array_column( \WP_CLI::$events, 'type' ) );
		}

		public function test_scans_run_all_starts_available_scans_and_warns_on_partial_failure() :void {
			$state = $this->installController(
				StartScansResult::fromRequested( [ 'afs', 'wpv' ] )
					->addStarted( 'afs', 44 )
					->addFailure( 'wpv', StartScansResult::REASON_SCAN_UNAVAILABLE )
			);

			( new ScansRun() )->execCmd( [], [ 'all' => true ] );

			$this->assertSame( [ [ 'afs', 'wpv' ] ], $state->scans->startedScans );
			$this->assertSame( [ 'warning' ], \array_column( \WP_CLI::$events, 'type' ) );
			$this->assertSame( 'Shield scan start failures: wpv:scan_unavailable', \WP_CLI::$events[ 0 ][ 'message' ] ?? '' );
		}

		public function test_scans_run_selected_flags_delegate_only_selected_scans() :void {
			$state = $this->installController(
				StartScansResult::fromRequested( [ 'afs' ] )->addStarted( 'afs', 44 )
			);

			( new ScansRun() )->execCmd( [], [ 'afs' => true ] );

			$this->assertSame( [ [ 'afs' ] ], $state->scans->startedScans );
			$this->assertSame( [], \WP_CLI::$events );
		}

		public function test_scans_run_no_start_result_exits_with_error_after_central_start_attempt() :void {
			$state = $this->installController(
				StartScansResult::fromRequested( [ 'afs' ] )
					->addFailure( 'afs', StartScansResult::REASON_ALREADY_EXISTS )
			);

			try {
				( new ScansRun() )->execCmd( [], [ 'afs' => true ] );
				$this->fail( 'Expected WP-CLI error when central start starts no scans.' );
			}
			catch ( \WP_CLI\ExitException $e ) {
				$this->assertSame( 1, $e->getCode() );
			}

			$this->assertSame( [ [ 'afs' ] ], $state->scans->startedScans );
			$this->assertSame( [ 'error' ], \array_column( \WP_CLI::$events, 'type' ) );
		}

		private function installController( ?StartScansResult $scanResult = null ) :object {
			$opts = new WpCliTestOptions();
			$scans = new WpCliTestScans( $scanResult );
			$configuration = new class {
				public array $options = [
					'wp_cli_test_option' => [],
				];

				public function optsForModule( string $module ) :array {
					unset( $module );
					return $this->options;
				}
			};
			$controller = UnitTestControllerFactory::install( null, null, (object)[
				'caps'       => new class {
					public function canWpcliLevel2() :bool {
						return true;
					}
				},
				'cfg'        => (object)[
					'configuration' => $configuration,
					'properties'    => [
						'slug_parent' => 'icwp',
						'slug_plugin' => 'wpsf',
					],
				],
				'labels'     => new class {
					public string $Name = 'Shield';

					public function getBrandName( string $brand ) :string {
						return $brand;
					}
				},
				'opts'       => $opts,
				'comps'      => (object)[
					'opts_lookup' => new class {
					},
					'scans'       => $scans,
				],
				'db_con'     => $this->newResetDbCon(),
				'plugin'     => new class extends ModCon {
					public function canSiteLoopback() :bool {
						return true;
					}
				},
				'plugin_reset' => false,
				'cache_dir_handler' => new class {
					public function dir() :string {
						return '';
					}
				},
			] );

			return (object)[
				'controller' => $controller,
				'opts'       => $opts,
				'scans'      => $scans,
			];
		}

		private function installResetServices() :void {
			ServicesState::mergeItems( [
				'service_wpgeneral' => new class extends \FernleafSystems\Wordpress\Services\Core\General {
					public array $deletedOptions = [];

					public function deleteOption( $key, $bIgnoreWPMS = false ) {
						unset( $bIgnoreWPMS );
						$this->deletedOptions[] = $key;
						return true;
					}

					public function canUseTransients() :bool {
						return false;
					}
				},
				'service_wpdb'      => new class extends \FernleafSystems\Wordpress\Services\Core\Db {
					public array $droppedTables = [];

					public function doDropTable( string $tables ) :bool {
						$this->droppedTables[] = $tables;
						return true;
					}
				},
				'service_wpfs'      => new class extends \FernleafSystems\Wordpress\Services\Core\Fs {
					public function deleteDir( $path ) {
						unset( $path );
						return true;
					}
				},
			] );
		}

		private function newResetDbCon() :object {
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
				$dbCon->{$table} = new WpCliResetDbHandler( $table );
			}

			return $dbCon;
		}
	}

	class WpCliTestOptions {

		public array $values = [
			'wp_cli_test_option' => 'Y',
		];

		public array $sets = [];

		public int $resetCalls = 0;

		public int $deleteCalls = 0;

		public function optExists( string $key ) :bool {
			return \array_key_exists( $key, $this->values );
		}

		public function optGet( string $key ) {
			return $this->values[ $key ] ?? null;
		}

		public function optSet( string $key, $value ) :self {
			$this->sets[] = [
				'key'   => $key,
				'value' => $value,
			];
			$this->values[ $key ] = $value;
			return $this;
		}

		public function optType( string $key ) :string {
			unset( $key );
			return 'checkbox';
		}

		public function optDefault( string $key ) :string {
			unset( $key );
			return 'Y';
		}

		public function optDef( string $key ) :array {
			unset( $key );
			return [
				'section' => 'general',
				'name'    => 'Global protection',
			];
		}

		public function resetToDefaults() :void {
			$this->resetCalls++;
		}

		public function delete() :void {
			$this->deleteCalls++;
		}
	}

	class WpCliTestScans {

		public array $startedScans = [];

		private StartScansResult $result;

		public function __construct( ?StartScansResult $result = null ) {
			$this->result = $result ?? StartScansResult::fromRequested( [] );
		}

		public function getAllScanCons() :array {
			return [
				new WpCliTestScanCon( 'afs' ),
				new WpCliTestScanCon( 'wpv' ),
			];
		}

		public function getScanSlugs() :array {
			return [ 'afs', 'wpv' ];
		}

		public function getStartBlockedMessage( bool $isCli = false ) :string {
			unset( $isCli );
			return '';
		}

		public function startNewScans( array $scans ) :StartScansResult {
			$this->startedScans[] = $scans;
			return $this->result;
		}
	}

	class WpCliTestScanCon {

		private string $slug;

		public function __construct( string $slug ) {
			$this->slug = $slug;
		}

		public function getSlug() :string {
			return $this->slug;
		}

		public function getScanName() :string {
			return \strtoupper( $this->slug );
		}
	}

	class WpCliResetDbHandler {

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
}
