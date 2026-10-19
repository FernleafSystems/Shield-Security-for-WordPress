<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rest\Worpdrive\Route;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\BackupsCon;
use FernleafSystems\Wordpress\Plugin\Shield\Rest\Worpdrive\v1\Route\{
	BaseWorpdrive,
	Checks,
	Clean,
	Signal
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestControllerFactory,
	UnitTestRequest
};
use FernleafSystems\Wordpress\Services\Core\{
	General,
	Users
};
use FernleafSystems\Wordpress\Services\Utilities\{
	HttpRequest,
	IpUtils,
	ServiceProviders
};

class BackupsAccessAndActivityTest extends BaseUnitTest {

	private const AUTH_KEY = 'worpdrive-test-key';
	private const WORPDRIVE_IP = '203.0.113.10';
	private const OTHER_IP = '198.51.100.20';
	private const ROUTE = '/shield/v1/worpdrive/checks';

	private array $servicesSnapshot = [];

	private BackupsTestHttp $http;

	private BackupsTestGeneral $general;

	private BackupsTestOptions $opts;

	private BackupsTestEvents $events;

	private object $comps;

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->returnArg();
		Functions\when( 'apply_filters' )->alias( static fn( string $hook, $value ) => $value );
		Functions\when( 'is_user_logged_in' )->justReturn( false );
		Functions\when( 'is_wp_error' )->alias( static fn( $thing ) :bool => $thing instanceof \WP_Error );
		Functions\when( 'rest_authorization_required_code' )->justReturn( 401 );

		$this->servicesSnapshot = ServicesState::snapshot();
		$this->http = new BackupsTestHttp( '{"backups": {"paused": false}}' );
		$this->general = new BackupsTestGeneral();
		$this->installServices( 1000 );

		$this->opts = new BackupsTestOptions();
		$this->events = new BackupsTestEvents();
		$this->installController( self::WORPDRIVE_IP );
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_verified_request_passes_with_defaults() :void {
		$this->assertTrue( $this->verify( $this->request( self::AUTH_KEY ) ) );
		$this->assertSame( [], $this->events->fired );
	}

	public function test_local_switch_off_refuses_verified_request() :void {
		$this->comps->backups->setEnabled( false );

		$this->assertFalse( $this->verify( $this->request( self::AUTH_KEY ) ) );
		$this->assertSame( 'N', $this->opts->stored[ 'shieldbackups_enabled' ] ?? null );
		$this->assertSame( 0, $this->http->calls, 'The local switch is checked before any remote status lookup.' );
		$this->assertSame( [
			[
				'event'  => 'backup_refused_disabled',
				'params' => [ 'ip' => self::WORPDRIVE_IP, 'route' => self::ROUTE ],
			],
		], $this->events->fired );

		$this->comps->backups->setEnabled( true );
		$this->assertTrue( $this->verify( $this->request( self::AUTH_KEY ) ) );
	}

	public function test_switches_file_saying_paused_refuses_verified_request() :void {
		$this->http->body = '{"backups": {"paused": true}}';

		$this->assertFalse( $this->verify( $this->request( self::AUTH_KEY ) ) );
		$this->assertSame( [
			[
				'event'  => 'backup_refused_paused',
				'params' => [ 'ip' => self::WORPDRIVE_IP, 'route' => self::ROUTE ],
			],
		], $this->events->fired );
	}

	/**
	 * @dataProvider providerUnusableSwitchesFile
	 */
	public function test_unusable_switches_file_lets_requests_through_and_is_cached( string $body ) :void {
		$this->http->body = $body;

		$this->assertTrue( $this->verify( $this->request( self::AUTH_KEY ) ) );
		$this->assertTrue( $this->verify( $this->request( self::AUTH_KEY ) ) );
		$this->assertSame( 1, $this->http->calls );
		$this->assertSame( 600, $this->general->lastTransientLifetime );
		$this->assertSame( [], $this->events->fired );
	}

	public function providerUnusableSwitchesFile() :array {
		return [
			'unreachable'           => [ '' ],
			'not json'              => [ '<html>502 Bad Gateway</html>' ],
			'paused is not boolean' => [ '{"backups": {"paused": "true"}}' ],
			'no backups section'    => [ '{"other": {"paused": true}}' ],
			'not an object'         => [ '[true]' ],
		];
	}

	public function test_correct_key_from_non_worpdrive_ip_is_refused_and_logged() :void {
		$this->installController( self::OTHER_IP );

		$this->assertFalse( $this->verify( $this->request( self::AUTH_KEY ) ) );
		$this->assertSame( [
			[
				'event'  => 'backup_refused_ip',
				'params' => [ 'ip' => self::OTHER_IP, 'route' => self::ROUTE ],
			],
		], $this->events->fired );
	}

	public function test_invalid_key_requests_are_refused_without_logging() :void {
		$this->installController( self::OTHER_IP );
		$this->assertFalse( $this->verify( $this->request( 'wrong-key' ) ) );

		$this->installController( self::WORPDRIVE_IP );
		$this->assertFalse( $this->verify( $this->request( 'wrong-key' ) ) );

		$this->assertSame( [], $this->events->fired );
		$this->assertSame( 0, $this->http->calls );
	}

	public function test_backup_signals_record_start_and_finish_events() :void {
		$this->comps->backups->processSignal( 'backup', [ 'state' => 'begin' ] );
		$this->assertSame( [
			'started_at'  => 1000,
			'finished_at' => null,
			'result'      => 'running',
			'duration'    => null,
		], $this->comps->backups->lastBackup() );

		$this->installServices( 1600 );
		$this->comps->backups->processSignal( 'backup', [ 'state' => 'end', 'success' => false ] );

		$this->assertSame( [
			[ 'event' => 'backup_started', 'params' => [] ],
			[ 'event' => 'backup_finished', 'params' => [ 'result' => 'failed', 'duration' => 600 ] ],
		], $this->events->fired );
		$this->assertSame( [
			'started_at'  => 1000,
			'finished_at' => 1600,
			'result'      => 'failed',
			'duration'    => 600,
		], $this->comps->backups->lastBackup() );
	}

	public function test_cleanup_and_backup_end_get_through_while_backups_are_refused() :void {
		$this->comps->backups->setEnabled( false );
		$end = $this->request( self::AUTH_KEY, [ 'context' => 'backup', 'signal_data' => [ 'state' => 'end', 'success' => false ] ] );
		$begin = $this->request( self::AUTH_KEY, [ 'context' => 'backup', 'signal_data' => [ 'state' => 'begin' ] ] );

		$this->assertTrue( $this->verify( $this->request( self::AUTH_KEY ), new Clean( [ 'strict_parameters' => false ] ) ) );
		$this->assertTrue( $this->verify( $end, new Signal( [ 'strict_parameters' => false ] ) ) );
		$this->assertFalse( $this->verify( $begin, new Signal( [ 'strict_parameters' => false ] ) ) );
		$this->assertFalse( $this->verify( $this->request( self::AUTH_KEY ) ) );
	}

	public function test_cleanup_still_needs_a_verified_request() :void {
		$this->comps->backups->setEnabled( false );

		$this->assertFalse( $this->verify( $this->request( 'wrong-key' ), new Clean( [ 'strict_parameters' => false ] ) ) );
	}

	private function verify( \WP_REST_Request $request, ?BaseWorpdrive $route = null ) :bool {
		$method = new \ReflectionMethod( BaseWorpdrive::class, 'verifyPermission' );
		$method->setAccessible( true );
		return $method->invoke( $route ?? new Checks( [ 'strict_parameters' => false ] ), $request );
	}

	private function request( string $authKey, array $params = [] ) :\WP_REST_Request {
		return new \WP_REST_Request( 'POST', self::ROUTE, \array_merge( [
			'auth_key' => $authKey,
			'uuid'     => 'gate-test',
		], $params ) );
	}

	private function installServices( int $ts ) :void {
		ServicesState::installItems( [
			'service_request'          => new UnitTestRequest( [], self::WORPDRIVE_IP, $ts ),
			'service_ip'               => new IpUtils(),
			'service_wpusers'          => new Users(),
			'service_wpgeneral'        => $this->general,
			'service_httprequest'      => $this->http,
			'service_serviceproviders' => new class extends ServiceProviders {
				public function getProviders() :array {
					return [
						'services' => [
							ServiceProviders::PROVIDER_WORPDRIVE => [
								'ips' => [ 4 => [ '203.0.113.10' ] ],
							],
						],
					];
				}
			},
		] );
	}

	private function installController( string $requestIp ) :void {
		$this->comps = (object)[
			'events'  => $this->events,
			'license' => new class {
				public function getLicense() :object {
					return (object)[ 'auth_key' => \hash( 'sha512', 'worpdrive-test-key' ) ];
				}
			},
		];
		UnitTestControllerFactory::install( null, null, (object)[
			'opts'     => $this->opts,
			'comps'    => $this->comps,
			'this_req' => (object)[ 'ip' => $requestIp ],
		] );
		$this->comps->backups = new BackupsCon();
	}
}

class BackupsTestHttp extends HttpRequest {

	public string $body;

	public int $calls = 0;

	public function __construct( string $body ) {
		$this->body = $body;
	}

	public function getContent( string $url, $args = [] ) :string {
		$this->calls++;
		return $url === BackupsCon::SWITCHES_URL ? $this->body : '';
	}
}

class BackupsTestGeneral extends General {

	private array $transients = [];

	public int $lastTransientLifetime = 0;

	public function canUseTransients() :bool {
		return true;
	}

	public function getTransient( $key ) {
		return $this->transients[ $key ] ?? false;
	}

	public function setTransient( $key, $value, $expire = 0 ) {
		$this->transients[ $key ] = $value;
		$this->lastTransientLifetime = (int)$expire;
		return true;
	}

	public function isWpCli() :bool {
		return false;
	}
}

class BackupsTestOptions {

	public array $stored = [
		'shieldbackups_enabled' => 'Y',
		'shieldbackups_status'  => [],
	];

	public function optGet( string $key ) {
		return $this->stored[ $key ] ?? null;
	}

	public function optIs( string $key, $value ) :bool {
		return $this->optGet( $key ) == $value;
	}

	public function optSet( string $key, $value ) :self {
		$this->stored[ $key ] = $value;
		return $this;
	}

	public function store() :void {
	}
}

class BackupsTestEvents {

	public array $fired = [];

	public function fireEvent( string $event, array $meta = [] ) :void {
		$this->fired[] = [
			'event'  => $event,
			'params' => $meta[ 'audit_params' ] ?? [],
		];
	}
}
