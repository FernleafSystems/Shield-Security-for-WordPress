<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\VirtualPatching;

use Brain\Monkey\Functions;
use FernleafSystems\Orle\VirtualPatchingRules\EvaluationPoint;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\{
	BlockLog,
	RequestGuard
};
use FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\Ruleset\RulesetFile;

class RequestGuardTest extends BaseUnitTest {

	use TempDirLifecycleTrait;

	private const AJAX_EXPORT = [
		[ 'kind' => 'path', 'negate' => false, 'operator' => 'equals', 'value' => '/wp-admin/admin-ajax.php' ],
		[ 'kind' => 'parameter_value', 'negate' => false, 'source' => 'form', 'name' => 'action', 'operator' => 'equals', 'value' => "it's_export" ],
	];

	private array $blocks = [];

	private array $actions = [];

	private array $filters = [];

	private array $done = [];

	private bool $admin = false;

	private array $capabilities = [];

	private int $userId = 0;

	private array $serverBackup;

	protected function setUp() :void {
		parent::setUp();
		$this->serverBackup = $_SERVER;
		$_GET = [];
		$_POST = [];
		$_SERVER = [
			'REQUEST_METHOD' => 'POST',
			'REQUEST_URI'    => '/wp-admin/admin-ajax.php',
			'SCRIPT_NAME'    => '/wp-admin/admin-ajax.php',
			'REMOTE_ADDR'    => '203.0.113.9',
		];

		Functions\when( 'add_action' )->alias( function ( string $hook, $callback, int $priority = 10 ) :bool {
			$this->actions[ $hook ] = [ $callback, $priority ];
			return true;
		} );
		Functions\when( 'add_filter' )->alias( function ( string $hook, $callback, int $priority = 10 ) :bool {
			$this->filters[ $hook ] = [ $callback, $priority ];
			return true;
		} );
		Functions\when( 'did_action' )->alias( fn( string $hook ) :int => $this->done[ $hook ] ?? 0 );
		Functions\when( 'is_admin' )->alias( fn() :bool => $this->admin );
		Functions\when( 'is_network_admin' )->justReturn( false );
		Functions\when( 'wp_unslash' )->alias( fn( $value ) => \is_array( $value ) ? \array_map( 'stripslashes', $value ) : \stripslashes( $value ) );
		Functions\when( 'current_user_can' )->alias( fn( string $capability ) :bool => \in_array( $capability, $this->capabilities, true ) );
		Functions\when( 'get_current_user_id' )->alias( fn() :int => $this->userId );
	}

	protected function tearDown() :void {
		$_SERVER = $this->serverBackup;
		$_GET = [];
		$_POST = [];
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testMatchingRequestIsBlockedWhenShieldLoadsAndLogged() :void {
		$_POST = [ 'action' => "it's_export" ];
		$events = $this->createTrackedTempDir().'/events.php';

		$this->guard( $this->ruleset( [ $this->rule( 'export', self::AJAX_EXPORT ) ], [ 'events' => $events ] ) )->start();

		$this->assertSame( [ [ 'export', EvaluationPoint::Request->value ] ], $this->blocks );
		$entry = BlockLog::read( $events )[ 0 ];
		$this->assertSame( [ 'export', [ 'CVE-2026-0001' ], '203.0.113.9', 'POST', '/wp-admin/admin-ajax.php', EvaluationPoint::Request->value, 'match' ], [
			$entry[ 'rule' ], $entry[ 'advisories' ], $entry[ 'ip' ], $entry[ 'method' ], $entry[ 'path' ], $entry[ 'pass' ], $entry[ 'verdict' ],
		] );
	}

	public function testOtherRequestsPass() :void {
		$_POST = [ 'action' => 'heartbeat' ];
		$this->guard( $this->ruleset( [ $this->rule( 'export', self::AJAX_EXPORT ) ] ) )->start();
		$this->assertSame( [], $this->blocks );
	}

	public function testSlashedValuesAreUnslashedAfterWordPressAddsSlashes() :void {
		$this->done[ 'sanitize_comment_cookies' ] = 1;
		$_POST = [ 'action' => "it\\'s_export" ];

		$this->guard( $this->ruleset( [ $this->rule( 'export', self::AJAX_EXPORT ) ] ) )->start();

		$this->assertCount( 1, $this->blocks );
	}

	public function testPatternFailureBlocks() :void {
		$rule = $this->rule( 'broken', [ [ 'kind' => 'path', 'negate' => false, 'operator' => 'regex', 'value' => '#(#' ] ] );
		$this->guard( $this->ruleset( [ $rule ] ) )->start();
		$this->assertSame( [ [ 'broken', EvaluationPoint::Request->value ] ], $this->blocks );
	}

	public function testBypassedIpSkipsEveryRule() :void {
		$_POST = [ 'action' => "it's_export" ];
		$ruleset = $this->ruleset( [ $this->rule( 'export', self::AJAX_EXPORT ) ], [
			'bypass' => [ 'ip_source' => 'REMOTE_ADDR', 'ips' => [ '203.0.113.0/24' ] ],
		] );

		$this->guard( $ruleset )->start();

		$this->assertSame( [], $this->blocks );
		$this->assertSame( [], $this->actions );
	}

	public function testRulesTheEarlyPassCheckedAreSkippedOnlyForTheSameRuleset() :void {
		if ( !\defined( 'SHIELD_EARLY_RAN' ) ) {
			\define( 'SHIELD_EARLY_RAN', 'hash-from-early-pass' );
		}
		$_POST = [ 'action' => "it's_export" ];

		$this->guard( $this->ruleset( [ $this->rule( 'export', self::AJAX_EXPORT ) ], [ 'hash' => 'hash-from-early-pass' ] ) )->start();
		$this->assertSame( [], $this->blocks );

		$this->guard( $this->ruleset( [ $this->rule( 'export', self::AJAX_EXPORT ) ], [ 'hash' => 'another-ruleset' ] ) )->start();
		$this->assertCount( 1, $this->blocks );
	}

	public function testAdminAreaConditionIsCheckedWhenShieldLoads() :void {
		$this->admin = true;
		$rule = $this->rule( 'admin', [ [ 'kind' => 'wp_is_admin', 'negate' => false ] ] );
		$this->guard( $this->ruleset( [ $rule ] ) )->start();
		$this->assertSame( [ [ 'admin', EvaluationPoint::Request->value ] ], $this->blocks );
	}

	public function testCapabilityRulesWaitForWordPressToSetUpTheUserAtInit() :void {
		$rule = $this->rule( 'settings', [
			[ 'kind' => 'path', 'negate' => false, 'operator' => 'equals', 'value' => '/wp-admin/admin-ajax.php' ],
			[ 'kind' => 'user_capability', 'negate' => true, 'capability' => 'manage_options' ],
		] );
		$guard = $this->guard( $this->ruleset( [ $rule ] ) );
		$guard->start();

		$this->assertSame( [], $this->blocks );
		$this->assertSame( [ [ $guard, 'runUserRules' ], \PHP_INT_MIN ], $this->actions[ 'init' ] );
		$this->assertArrayNotHasKey( 'plugins_loaded', $this->actions );

		$this->capabilities = [ 'manage_options' ];
		$guard->runUserRules();
		$this->assertSame( [], $this->blocks );

		$this->capabilities = [ 'read' ];
		$guard->runUserRules();
		$this->assertSame( [ [ 'settings', EvaluationPoint::User->value ] ], $this->blocks );
	}

	public function testSignedInAndOwnUserRulesWaitForWordPressToSetUpTheUserAtInit() :void {
		// Blocks a signed-in user acting on another user's ID.
		$rule = $this->rule( 'other-user', [
			[ 'kind' => 'parameter_exists', 'negate' => false, 'source' => 'form', 'name' => 'user_id' ],
			[ 'kind' => 'parameter_current_user_id', 'negate' => true, 'source' => 'form', 'name' => 'user_id' ],
			[ 'kind' => 'user_authenticated', 'negate' => false ],
		] );
		$guard = $this->guard( $this->ruleset( [ $rule ] ) );
		$guard->start();

		$this->assertSame( [ [ $guard, 'runUserRules' ], \PHP_INT_MIN ], $this->actions[ 'init' ] );

		$_POST = [ 'user_id' => '05' ];
		$guard->runUserRules();
		$this->userId = 5;
		$guard->runUserRules();
		$this->assertSame( [], $this->blocks );

		$this->userId = 2;
		$guard->runUserRules();
		$this->assertSame( [ [ 'other-user', EvaluationPoint::User->value ] ], $this->blocks );
	}

	public function testRestRulesReadTheUserAtDispatch() :void {
		$rule = $this->rule( 'signed-out', [
			[ 'kind' => 'rest_route', 'negate' => false, 'namespace' => 'example/v1', 'operator' => 'equals_i', 'value' => '/profile' ],
			[ 'kind' => 'user_authenticated', 'negate' => true ],
		] );
		$guard = $this->guard( $this->ruleset( [ $rule ] ) );
		$guard->start();

		$this->assertArrayNotHasKey( 'init', $this->actions );
		$this->userId = 3;
		$this->assertNull( $guard->checkRestDispatch( null, null, new \WP_REST_Request( 'GET', '/example/v1/profile', [] ) ) );
		$this->userId = 0;
		$this->assertSame( 'blocked', $guard->checkRestDispatch( null, null, new \WP_REST_Request( 'GET', '/example/v1/profile', [] ) ) );
	}

	public function testHeaderRulesAreCheckedWhenShieldLoads() :void {
		$rule = $this->rule( 'header', [
			[ 'kind' => 'header_value', 'negate' => false, 'name' => 'X-Forwarded-For', 'operator' => 'contains', 'value' => '<' ],
		] );
		$_SERVER[ 'HTTP_X_FORWARDED_FOR' ] = '198.51.100.23<script>';

		$this->guard( $this->ruleset( [ $rule ] ) )->start();

		$this->assertSame( [ [ 'header', EvaluationPoint::Request->value ] ], $this->blocks );
	}

	public function testRestRulesAreCheckedOnEveryDispatch() :void {
		$rule = $this->rule( 'rest', [
			[ 'kind' => 'rest_route', 'negate' => false, 'namespace' => 'example/v1', 'operator' => 'equals_i', 'value' => '/settings' ],
			[ 'kind' => 'rest_parameter_exists', 'negate' => false, 'name' => 'callback' ],
		] );
		$guard = $this->guard( $this->ruleset( [ $rule ] ) );
		$guard->start();

		$this->assertSame( [ [ $guard, 'checkRestDispatch' ], \PHP_INT_MIN ], $this->filters[ 'rest_pre_dispatch' ] );

		$this->assertNull( $guard->checkRestDispatch( null, null, new \WP_REST_Request( 'POST', '/example/v1/settings', [ 'name' => 'x' ] ) ) );
		$this->assertSame( 'blocked', $guard->checkRestDispatch( null, null, new \WP_REST_Request( 'POST', '/example/v1/settings', [ 'callback' => 'x' ] ) ) );
		$this->assertSame( 'earlier result', $guard->checkRestDispatch( 'earlier result', null, new \WP_REST_Request( 'GET', '/other/v1/x', [] ) ) );
		$this->assertCount( 1, $this->blocks );
	}

	public function testRestRulesUseTheMethodWordPressDispatches() :void {
		// The HTTP request is a GET; ?_method=POST makes WordPress dispatch the POST handler.
		$_SERVER[ 'REQUEST_METHOD' ] = 'GET';
		$rule = $this->rule( 'rest-post', [
			[ 'kind' => 'method', 'negate' => false, 'value' => 'POST' ],
			[ 'kind' => 'rest_route', 'negate' => false, 'namespace' => 'example/v1', 'operator' => 'equals_i', 'value' => '/upload' ],
		] );
		$guard = $this->guard( $this->ruleset( [ $rule ] ) );
		$guard->start();

		$this->assertSame( 'blocked', $guard->checkRestDispatch( null, null, new \WP_REST_Request( 'POST', '/example/v1/upload', [] ) ) );
		$this->assertNull( $guard->checkRestDispatch( null, null, new \WP_REST_Request( 'GET', '/example/v1/upload', [] ) ) );
	}

	public function testGuardIsBuiltOnlyForAReadableRulesetWithRules() :void {
		$dir = $this->createTrackedTempDir();
		$valid = $this->ruleset( [ $this->rule( 'export', self::AJAX_EXPORT ) ] );
		unset( $valid[ 'hash' ] );
		\file_put_contents( $dir.'/valid.php', RulesetFile::render( $valid ) );
		\file_put_contents( $dir.'/empty.php', RulesetFile::render( \array_merge( $valid, [ 'rules' => [] ] ) ) );
		\file_put_contents( $dir.'/corrupt.php', '<?php return [' );

		$this->assertNull( RequestGuard::fromSite(), 'no ruleset option' );
		foreach ( [ 'empty.php', 'corrupt.php', 'missing.php' ] as $file ) {
			update_option( RequestGuard::OPTION_RULESET_PATH, $dir.'/'.$file );
			$this->assertNull( RequestGuard::fromSite(), $file );
		}

		update_option( RequestGuard::OPTION_RULESET_PATH, $dir.'/valid.php' );
		$this->assertInstanceOf( RequestGuard::class, RequestGuard::fromSite() );

		Functions\when( 'is_multisite' )->justReturn( true );
		$this->assertNull( RequestGuard::fromSite(), 'multisite' );
	}

	public function testBootLoadsTheGuardAndRulePackageWithoutComposer() :void {
		$this->assertSame( '[true,true]', $this->boot( \dirname( __DIR__, 3 ).'/src/VirtualPatching/boot.php' ) );
	}

	public function testBootLeavesTheRequestAloneWhenFilesAreMissingPartWayThroughAnUpdate() :void {
		$dir = $this->createTrackedTempDir();
		\copy( \dirname( __DIR__, 3 ).'/src/VirtualPatching/boot.php', $dir.'/boot.php' );
		$this->assertSame( '[false,false]', $this->boot( $dir.'/boot.php' ) );
	}

	/**
	 * Includes boot.php in a fresh PHP process with no autoloader and errors displayed, as when Shield's plugin
	 * file is included on a host that shows errors.
	 * @return string everything the process printed, which reports whether the guard and package classes loaded
	 */
	private function boot( string $boot ) :string {
		$script = \sprintf(
			'define( "ABSPATH", "/" ); function get_option( $name ) { return ""; } function add_action() {} require %s; echo json_encode( [ class_exists( %s, false ), class_exists( %s, false ) ] );',
			\var_export( $boot, true ),
			\var_export( RequestGuard::class, true ),
			\var_export( EvaluationPoint::class, true )
		);
		$process = \proc_open( [ \PHP_BINARY, '-n', '-d', 'display_errors=1', '-d', 'error_reporting=-1', '-r', $script ], [ 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes );
		$output = \stream_get_contents( $pipes[ 1 ] ).\stream_get_contents( $pipes[ 2 ] );
		\proc_close( $process );
		return $output;
	}

	public function testRestBlockIsAForbiddenResponse() :void {
		Functions\when( 'rest_convert_error_to_response' )->alias( function ( \WP_Error $error ) :\WP_REST_Response {
			return new \WP_REST_Response( [ 'code' => $error->get_error_code() ], $error->get_error_data()[ 'status' ] );
		} );
		$response = ( new RequestGuard( [] ) )->sendForbidden( [], EvaluationPoint::Rest->value );
		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( [ 'code' => 'shield_virtual_patch' ], $response->get_data() );
	}

	private function guard( array $ruleset ) :RequestGuard {
		return new RequestGuard( $ruleset, function ( array $rule, string $point ) {
			$this->blocks[] = [ $rule[ 'id' ], $point ];
			return 'blocked';
		} );
	}

	private function ruleset( array $rules, array $extra = [] ) :array {
		return \array_merge( [
			'format'         => 1,
			'hash'           => 'hash',
			'active_plugins' => [],
			'active_themes'  => [],
			'rules'          => $rules,
		], $extra );
	}

	private function rule( string $id, array $conditions ) :array {
		return [ 'id' => $id, 'advisories' => [ 'CVE-2026-0001' ], 'conditions' => $conditions ];
	}
}
