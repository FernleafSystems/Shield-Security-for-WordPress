<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Rules;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\RequestRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\Core\IsRateLimitExceeded as RateLimitRuleBuilder,
	Processors\ProcessConditions,
	Processors\ResponseProcessor,
	RulesController
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

/**
 * Integration coverage for traffic rate-limit rule behavior:
 * - threshold boundary behavior
 * - logged-in bypass semantics
 * - event/audit payload contract
 */
class RateLimitRuleBehaviorTest extends ShieldIntegrationTestCase {

	private array $optionSnapshot = [];

	private function setupRateLimitOptions( int $limit, int $span, string $logger = 'Y', string $limiter = 'Y' ) :void {
		$con = $this->requireController();
		$con->opts->optSet( 'enable_logger', $logger );
		$con->opts->optSet( 'enable_limiter', $limiter );
		$con->opts->optSet( 'limit_requests', $limit );
		$con->opts->optSet( 'limit_time_span', $span );
	}

	private function seedRequestLogs( string $ip, int $count ) :void {
		$ipRecord = TestDataFactory::createIpRecord( $ip );
		$records = new RequestRecords();

		for ( $i = 0; $i < $count; $i++ ) {
			$records->addReq( 'req_'.uniqid( (string)$i, true ), $ipRecord->id );
		}
	}

	private function processRateLimitRuleConditions() :bool {
		$con = $this->requireController();
		$rule = ( new RateLimitRuleBuilder() )->build();
		return ( new ProcessConditions( $rule->conditions ) )
			->setThisRequest( $con->this_req )
			->process();
	}

	private function resetRateLimitRequestState( string $ip = '203.0.113.55', bool $bypassesRestrictions = false ) :void {
		$this->resetIpCaches();

		$con = $this->requireController();
		$con->rules = new RulesController();
		$con->rules->setThisRequest( $con->this_req )->execute();
		$con->this_req->request_bypasses_all_restrictions = $bypassesRestrictions;
		$con->this_req->request_subject_to_shield_restrictions = true;
		$con->this_req->ip = $ip;
	}

	public function set_up() {
		parent::set_up();

		$this->enablePremiumCapabilities( [ 'traffic_rate_limiting' ] );
		$this->requireDb( 'ips' );
		$this->requireDb( 'req_logs' );
		$this->optionSnapshot = $this->snapshotSelectedOptions( [
			'enable_logger',
			'enable_limiter',
			'limit_requests',
			'limit_time_span',
		] );

		\wp_set_current_user( 0 );
		$this->resetRateLimitRequestState();
	}

	public function tear_down() {
		$this->restoreSelectedOptions( $this->optionSnapshot );
		\wp_set_current_user( 0 );

		parent::tear_down();
	}

	public function test_conditions_match_only_when_request_count_exceeds_limit() {
		$ip = '203.0.113.55';
		$this->setupRateLimitOptions( 2, 300 );
		$this->resetRateLimitRequestState( $ip );
		$this->seedRequestLogs( $ip, 3 );

		$this->assertTrue( $this->processRateLimitRuleConditions() );

		$meta = $this->requireController()->rules->getConditionMeta()->getRawData();
		$this->assertSame( 2, (int)( $meta[ 'count' ] ?? -1 ) );
		$this->assertSame( 300, (int)( $meta[ 'span' ] ?? -1 ) );
		$this->assertGreaterThanOrEqual( 3, (int)( $meta[ 'requests' ] ?? 0 ) );
	}

	public function test_conditions_do_not_match_at_exact_limit_boundary() {
		$ip = '203.0.113.56';
		$this->setupRateLimitOptions( 2, 300 );
		$this->resetRateLimitRequestState( $ip );
		$this->seedRequestLogs( $ip, 2 );

		$this->assertFalse( $this->processRateLimitRuleConditions() );
	}

	public function test_logged_in_users_do_not_match_rate_limit_rule() {
		$ip = '203.0.113.57';
		$this->setupRateLimitOptions( 2, 300 );
		$this->resetRateLimitRequestState( $ip );
		$this->seedRequestLogs( $ip, 5 );

		$userId = self::factory()->user->create( [
			'role' => 'administrator',
		] );
		\wp_set_current_user( $userId );

		$this->assertFalse( $this->processRateLimitRuleConditions() );
	}

	public function test_request_bypass_does_not_match_rate_limit_rule() {
		$ip = '203.0.113.58';
		$this->setupRateLimitOptions( 2, 300 );
		$this->resetRateLimitRequestState( $ip, true );
		$this->seedRequestLogs( $ip, 5 );

		$this->assertFalse( $this->processRateLimitRuleConditions() );
	}

	/**
	 * @dataProvider disabledTrafficLimiterProvider
	 */
	public function test_disabled_traffic_limiter_configuration_does_not_match_rate_limit_rule(
		string $logger,
		string $limiter,
		int $limit,
		int $span,
		string $ip
	) :void {
		$this->setupRateLimitOptions( $limit, $span, $logger, $limiter );
		$this->resetRateLimitRequestState( $ip );
		$this->seedRequestLogs( $ip, 5 );

		$this->assertFalse( $this->processRateLimitRuleConditions() );
	}

	public function disabledTrafficLimiterProvider() :array {
		return [
			'logger disabled'  => [ 'N', 'Y', 2, 300, '203.0.113.59' ],
			'limiter disabled' => [ 'Y', 'N', 2, 300, '203.0.113.60' ],
			'zero count'       => [ 'Y', 'Y', 0, 300, '203.0.113.61' ],
			'zero span'        => [ 'Y', 'Y', 2, 0, '203.0.113.62' ],
		];
	}

	public function test_rate_limit_event_contains_required_audit_params() {
		$ip = '203.0.113.63';
		$this->setupRateLimitOptions( 1, 300 );
		$this->resetRateLimitRequestState( $ip );
		$this->seedRequestLogs( $ip, 3 );

		$rule = ( new RateLimitRuleBuilder() )->build();
		$this->assertTrue( ( new ProcessConditions( $rule->conditions ) )
			->setThisRequest( $this->requireController()->this_req )
			->process() );

		$this->captureShieldEvents();
		( new ResponseProcessor( $rule ) )
			->setThisRequest( $this->requireController()->this_req )
			->run();

		$events = $this->getCapturedEventsByKey( 'request_limit_exceeded' );
		$this->assertNotEmpty( $events, 'Rate-limit response should fire request_limit_exceeded event' );

		$auditParams = $events[ 0 ][ 'meta' ][ 'audit_params' ] ?? [];
		foreach ( [ 'requests', 'count', 'span' ] as $required ) {
			$this->assertArrayHasKey( $required, $auditParams, "Missing rate-limit audit param: {$required}" );
		}
		$this->assertGreaterThanOrEqual( 3, (int)$auditParams[ 'requests' ] );
		$this->assertSame( 1, (int)$auditParams[ 'count' ] );
		$this->assertSame( 300, (int)$auditParams[ 'span' ] );
	}
}
