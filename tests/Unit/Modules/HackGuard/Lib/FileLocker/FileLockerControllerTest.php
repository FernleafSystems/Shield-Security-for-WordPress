<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Lib\FileLocker;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Exceptions\{
	FileContentsEncodingFailure,
	FileContentsEncryptionFailure,
	LockDbInsertFailure,
	NoCipherAvailableException,
	NoFileLockPathsExistException,
	PublicKeyRetrievalFailure,
	UnsupportedFileLockType
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\FileLockerController;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestRequest
};
use FernleafSystems\Wordpress\Services\Core\General;

class FileLockerControllerTest extends BaseUnitTest {

	private const NOW = 1700000000;

	private array $actions = [];
	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();

		Functions\when( 'add_action' )->alias( function (
			string $hook,
			callable $callback,
			int $priority = 10,
			int $acceptedArgs = 1
		) :bool {
			unset( $priority, $acceptedArgs );
			$this->actions[ $hook ][] = $callback;
			return true;
		} );
		Functions\when( 'wp_next_scheduled' )->alias(
			static fn( string $hook ) => $hook === 'shield-create_file_locks' ? self::NOW + 60 : false
		);
		Functions\when( 'wp_normalize_path' )->alias(
			static fn( string $path ) :string => \str_replace( '\\', '/', $path )
		);
		Functions\when( 'trailingslashit' )->alias(
			static fn( string $path ) :string => \rtrim( \str_replace( '\\', '/', $path ), '/' ).'/'
		);
		Functions\when( 'path_join' )->alias(
			static fn( string $base, string $path ) :string => \rtrim( \str_replace( '\\', '/', $base ), '/' ).'/'.\ltrim( $path, '/' )
		);

		ServicesState::installItems( [
			'service_request'   => new UnitTestRequest( [], '127.0.0.1', self::NOW ),
			'service_wpgeneral' => new FileLockerGeneralStub(),
		] );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	/**
	 * @dataProvider permanentExceptionProvider
	 * @param class-string<\Exception> $exceptionClass
	 */
	public function test_permanent_failure_removes_only_current_type_and_continues(
		string $fileType,
		string $exceptionClass
	) :void {
		$priorFailure = self::NOW - 120;
		[ $subject, $opts ] = $this->runCreation( [
			'files'    => [ $fileType, 'root_index' ],
			'state'    => [ 'last_locks_created_failed_at' => $priorFailure ],
			'failures' => [ $fileType => new $exceptionClass( 'permanent failure' ) ],
		] );

		$this->assertSame( [ $fileType, 'root_index' ], $subject->attemptedTypes );
		$this->assertSame( [ 'root_index' ], \array_values( $opts->optGet( 'file_locker' ) ) );
		$this->assertSame( $priorFailure, $opts->optGet( 'filelocker_state' )[ 'last_locks_created_failed_at' ] );
		$this->assertSame( self::NOW, $opts->optGet( 'filelocker_state' )[ 'last_locks_created_at' ] );
	}

	public static function permanentExceptionProvider() :array {
		return [
			'missing paths'    => [ 'wpconfig', NoFileLockPathsExistException::class ],
			'unsupported type' => [ 'wpconfig', UnsupportedFileLockType::class ],
		];
	}

	/**
	 * @dataProvider retryableExceptionProvider
	 * @param class-string<\Exception> $exceptionClass
	 */
	public function test_retryable_failure_retains_selection_records_state_and_stops( string $exceptionClass ) :void {
		$message = 'retryable '.$exceptionClass;
		[ $subject, $opts ] = $this->runCreation( [
			'files'    => [ 'wpconfig', 'root_index' ],
			'failures' => [ 'wpconfig' => new $exceptionClass( $message ) ],
		] );

		$this->assertSame( [ 'wpconfig' ], $subject->attemptedTypes );
		$this->assertSame( [ 'wpconfig', 'root_index' ], $opts->optGet( 'file_locker' ) );
		$this->assertSame( $message, $opts->optGet( 'filelocker_state' )[ 'last_error' ] );
		$this->assertSame( self::NOW, $opts->optGet( 'filelocker_state' )[ 'last_locks_created_failed_at' ] );
		$this->assertSame( 0, $opts->optGet( 'filelocker_state' )[ 'last_locks_created_at' ] );
		$this->assertSame( \trailingslashit( \wp_normalize_path( ABSPATH ) ), $opts->optGet( 'filelocker_state' )[ 'abspath' ] );
	}

	public static function retryableExceptionProvider() :array {
		return [
			'DB insert'      => [ LockDbInsertFailure::class ],
			'encoding'       => [ FileContentsEncodingFailure::class ],
			'encryption'     => [ FileContentsEncryptionFailure::class ],
			'cipher'         => [ NoCipherAvailableException::class ],
			'public key'     => [ PublicKeyRetrievalFailure::class ],
		];
	}

	public function test_unexpected_runtime_exception_is_retryable() :void {
		[ $subject, $opts ] = $this->runCreation( [
			'files'    => [ 'wpconfig', 'root_index' ],
			'failures' => [ 'wpconfig' => new \RuntimeException( 'unexpected failure' ) ],
		] );

		$this->assertSame( [ 'wpconfig' ], $subject->attemptedTypes );
		$this->assertSame( [ 'wpconfig', 'root_index' ], $opts->optGet( 'file_locker' ) );
		$this->assertSame( 'unexpected failure', $opts->optGet( 'filelocker_state' )[ 'last_error' ] );
		$this->assertSame( self::NOW, $opts->optGet( 'filelocker_state' )[ 'last_locks_created_failed_at' ] );
	}

	public function test_success_records_time_and_clears_prior_error() :void {
		[ $subject, $opts ] = $this->runCreation( [
			'files' => [ 'wpconfig' ],
			'state' => [ 'last_error' => 'prior error' ],
		] );

		$this->assertSame( [ 'wpconfig' ], $subject->attemptedTypes );
		$this->assertSame( self::NOW, $opts->optGet( 'filelocker_state' )[ 'last_locks_created_at' ] );
		$this->assertSame( '', $opts->optGet( 'filelocker_state' )[ 'last_error' ] );
	}

	/**
	 * @dataProvider cooldownProvider
	 */
	public function test_creation_waits_full_cooldown( string $stateField ) :void {
		[ $subject ] = $this->runCreation( [
			'files' => [ 'wpconfig' ],
			'state' => [ $stateField => self::NOW - FileLockerController::CRON_DELAY + 1 ],
		] );

		$this->assertSame( [], $subject->attemptedTypes );
	}

	public static function cooldownProvider() :array {
		return [
			'last success' => [ 'last_locks_created_at' ],
			'last failure' => [ 'last_locks_created_failed_at' ],
		];
	}

	public function test_creation_is_allowed_at_exact_cooldown_boundary() :void {
		[ $subject ] = $this->runCreation( [
			'files' => [ 'wpconfig' ],
			'state' => [
				'last_locks_created_at'        => self::NOW - FileLockerController::CRON_DELAY,
				'last_locks_created_failed_at' => self::NOW - FileLockerController::CRON_DELAY,
			],
		] );

		$this->assertSame( [ 'wpconfig' ], $subject->attemptedTypes );
	}

	public function test_malformed_stored_selections_are_discarded_without_blocking_valid_siblings() :void {
		$resource = \fopen( 'php://memory', 'r' );
		try {
			[ $subject ] = $this->runCreation( [
				'files' => [
					'root_index',
					1,
					1.5,
					true,
					[ 'root_index' ],
					new \stdClass(),
					new FileLockerSelectionStringable(),
					$resource,
					null,
					'',
					'unknown_file',
					'wpconfig',
					'root_index',
				],
			] );

			$this->assertSame( [ 'root_index', 'wpconfig' ], $subject->getFilesToLock() );
			$this->assertSame( [ 'root_index', 'wpconfig' ], $subject->attemptedTypes );
		}
		finally {
			\fclose( $resource );
		}
	}

	public function test_all_configured_keys_and_associative_storage_become_first_seen_list() :void {
		[ $subject ] = $this->runCreation( [
			'files' => [
				'first'     => 'root_webconfig',
				'second'    => 'wpconfig',
				'duplicate' => 'root_webconfig',
				'third'     => 'theme_functions',
				'fourth'    => 'root_htaccess',
				'fifth'     => 'root_index',
			],
			'execute' => false,
		] );

		$expected = [ 'root_webconfig', 'wpconfig', 'theme_functions', 'root_htaccess', 'root_index' ];
		$this->assertSame( $expected, $subject->getFilesToLock() );
	}

	public function test_only_malformed_stored_selections_do_not_schedule_or_attempt_locks() :void {
		[ $subject ] = $this->runCreation( [
			'files' => [ 1, true, [ 'wpconfig' ], new \stdClass(), null, '', 'unknown_file' ],
		] );

		$this->assertSame( [], $subject->getFilesToLock() );
		$this->assertSame( [], $subject->attemptedTypes );
		$this->assertSame( [], $this->actions );
	}

	/**
	 * @dataProvider malformedOuterSelectionProvider
	 */
	public function test_malformed_outer_stored_selection_disables_runtime( $files ) :void {
		[ $subject ] = $this->runCreation( [ 'files' => $files ] );

		$this->assertSame( [], $subject->getFilesToLock() );
		$this->assertSame( [], $subject->attemptedTypes );
		$this->assertSame( [], $this->actions );
	}

	public static function malformedOuterSelectionProvider() :array {
		return [
			'null'    => [ null ],
			'string'  => [ 'wpconfig' ],
			'integer' => [ 1 ],
			'float'   => [ 1.5 ],
			'boolean' => [ true ],
			'object'  => [ new \stdClass() ],
		];
	}

	public function test_resource_outer_stored_selection_disables_runtime() :void {
		$resource = \fopen( 'php://memory', 'r' );
		try {
			[ $subject ] = $this->runCreation( [ 'files' => $resource ] );

			$this->assertSame( [], $subject->getFilesToLock() );
			$this->assertSame( [], $subject->attemptedTypes );
			$this->assertSame( [], $this->actions );
		}
		finally {
			\fclose( $resource );
		}
	}

	/**
	 * @param array{
	 *   files:mixed,
	 *   state?:array<string,mixed>,
	 *   failures?:array<string,\Exception>,
	 *   execute?:bool
	 * } $config
	 * @return array{FileLockerControllerTestSubject,FileLockerOptionsStub}
	 */
	private function runCreation( array $config ) :array {
		$opts = new FileLockerOptionsStub( [
			'file_locker'     => $config[ 'files' ],
			'filelocker_state' => $config[ 'state' ] ?? [],
		] );
		$subject = new FileLockerControllerTestSubject( $config[ 'failures' ] ?? [] );
		$dbHandler = new FileLockerDbHandlerStub();

		$controller = new class( $opts, $subject, $dbHandler ) extends Controller {
			public object $opts;
			public object $comps;
			public object $db_con;
			public object $this_req;

			public function __construct(
				FileLockerOptionsStub $opts,
				FileLockerControllerTestSubject $fileLocker,
				FileLockerDbHandlerStub $dbHandler
			) {
				$this->opts = $opts;
				$this->this_req = (object)[ 'wp_is_cron' => true ];
				$this->db_con = (object)[ 'file_locker' => $dbHandler ];
				$this->comps = (object)[
					'file_locker' => $fileLocker,
					'shieldnet'   => new FileLockerShieldNetStub(),
				];
			}

			public function prefix( string $suffix = '', string $glue = '-' ) :string {
				return 'shield'.( $suffix === '' ? '' : $glue.$suffix );
			}
		};
		PluginControllerInstaller::install( $controller );

		if ( !( $config[ 'execute' ] ?? true ) ) {
			return [ $subject, $opts ];
		}

		$subject->execute();
		if ( !$subject->isEnabled() ) {
			return [ $subject, $opts ];
		}
		$this->assertCount( 1, $this->actions[ 'wp_loaded' ] ?? [] );
		$this->actions[ 'wp_loaded' ][ 0 ]();
		$this->assertCount( 1, $this->actions[ 'shield-create_file_locks' ] ?? [] );
		$this->actions[ 'shield-create_file_locks' ][ 0 ]();

		return [ $subject, $opts ];
	}
}

class FileLockerControllerTestSubject extends FileLockerController {

	public array $attemptedTypes = [];

	/**
	 * @var array<string,\Exception>
	 */
	private array $failures;

	/**
	 * @param array<string,\Exception> $failures
	 */
	public function __construct( array $failures ) {
		$this->failures = $failures;
	}

	protected function createLocksForType( string $type ) :void {
		$this->attemptedTypes[] = $type;
		if ( isset( $this->failures[ $type ] ) ) {
			throw $this->failures[ $type ];
		}
	}
}

class FileLockerOptionsStub {

	private array $values;

	public function __construct( array $values ) {
		$this->values = $values;
	}

	public function optGet( string $key ) {
		return $this->values[ $key ] ?? [];
	}

	public function optSet( string $key, $value ) :self {
		$this->values[ $key ] = $value;
		return $this;
	}

	public function optDef( string $key ) :array {
		return $key === 'file_locker' ? [
			'value_options' => \array_map(
				static fn( string $value ) :array => [ 'value_key' => $value ],
				[ 'wpconfig', 'theme_functions', 'root_htaccess', 'root_index', 'root_webconfig' ]
			),
		] : [];
	}

	public function store() :self {
		return $this;
	}
}

class FileLockerDbHandlerStub {

	public function isReady() :bool {
		return true;
	}

	public function getQuerySelector() :FileLockerDbSelectorStub {
		return new FileLockerDbSelectorStub();
	}
}

class FileLockerDbSelectorStub {

	public function setNoOrderBy() :self {
		return $this;
	}

	public function queryWithResult() :array {
		return [];
	}
}

class FileLockerShieldNetStub {

	public function canHandshake() :bool {
		return true;
	}
}

class FileLockerSelectionStringable {

	public function __toString() :string {
		return 'wpconfig';
	}
}

class FileLockerGeneralStub extends General {

	public function isCron() :bool {
		return false;
	}
}
