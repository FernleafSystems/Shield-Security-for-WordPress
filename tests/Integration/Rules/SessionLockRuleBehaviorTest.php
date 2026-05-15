<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Rules;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\Core\LockSessionFail as LockSessionFailRuleBuilder,
	Conditions,
	Enum,
	Processors\ProcessConditions,
	Processors\ResponseProcessor,
	Responses,
	RuleVO,
	RulesController
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

class SessionLockRuleBehaviorTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;

	private array $optionSnapshot = [];

	private array $requestSnapshot = [];

	public function set_up() {
		parent::set_up();

		$this->optionSnapshot = $this->snapshotSelectedOptions( [ 'session_lock' ] );
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
		$this->resetRuleRequestState();
	}

	public function tear_down() {
		$this->restoreCurrentRequestState( $this->requestSnapshot );
		$this->restoreSelectedOptions( $this->optionSnapshot );
		\wp_set_current_user( 0 );

		parent::tear_down();
	}

	public function test_ip_session_lock_matches_when_request_ip_changes() :void {
		$rule = $this->primeRuleWithSession( '203.0.113.45', '203.0.113.46', [ 'ip' ] );

		$this->assertSame( LockSessionFailRuleBuilder::SLUG, $rule->slug );
		$this->assertTrue( $this->conditionMatches( Conditions\ShieldConfigurationOption::class, [
			'name'        => 'session_lock',
			'match_type'  => Enum\EnumMatchTypes::MATCH_TYPE_CONTAINS,
			'match_value' => 'ip',
		] ) );
		$this->assertTrue( $this->conditionMatches( Conditions\IsLoggedInNormal::class ) );
		$this->assertTrue( $this->conditionMatches( Conditions\ShieldHasValidCurrentSession::class ) );
		$this->assertFalse( $this->conditionMatches( Conditions\ShieldSessionParameterValueMatches::class, [
			'param_name'    => 'ip',
			'match_type'    => Enum\EnumMatchTypes::MATCH_TYPE_EQUALS,
			'match_pattern' => '{{request.ip}}',
		] ) );
		$this->assertTrue( $this->runRuleConditions( $rule ) );

		$meta = $this->requireController()->rules->getConditionMeta()->getRawData();
		$this->assertSame( 'ip', $meta[ 'match_request_param' ] ?? '' );
		$this->assertSame( '203.0.113.45', $meta[ 'match_request_value' ] ?? '' );
		$this->assertSame( '203.0.113.46', $meta[ 'match_pattern' ] ?? '' );

		$eventResponse = $this->responseDefinition( $rule->responses, Responses\EventFire::class );
		$this->assertSame( 'session_lock', $eventResponse[ 'params' ][ 'event' ] ?? '' );
		$this->responseDefinition( $rule->responses, Responses\UserSessionLogoutCurrent::class );

		$redirectResponse = $this->responseDefinition( $rule->responses, Responses\HttpRedirect::class );
		$this->assertSame( 302, $redirectResponse[ 'params' ][ 'status_code' ] ?? 0 );
		$this->assertSame(
			'/wp-login.php',
			(string)\wp_parse_url( (string)( $redirectResponse[ 'params' ][ 'redirect_url' ] ?? '' ), \PHP_URL_PATH )
		);

		$this->captureShieldEvents();
		( new ResponseProcessor( $this->eventOnlyRule( $eventResponse, $meta ) ) )
			->setThisRequest( $this->requireController()->this_req )
			->run();

		$events = $this->getCapturedEventsByKey( 'session_lock' );
		$this->assertCount( 1, $events );
		$this->assertSame(
			(string)\wp_get_current_user()->user_login,
			$events[ 0 ][ 'meta' ][ 'audit_params' ][ 'user_login' ] ?? ''
		);
	}

	public function test_ip_session_lock_does_not_match_when_ip_is_unchanged() :void {
		$rule = $this->primeRuleWithSession( '203.0.113.45', '203.0.113.45', [ 'ip' ] );

		$this->assertFalse( $this->runRuleConditions( $rule ) );
	}

	public function test_ip_session_lock_does_not_match_when_disabled() :void {
		$rule = $this->primeRuleWithSession( '203.0.113.45', '203.0.113.46', [] );

		$this->assertFalse( $this->runRuleConditions( $rule ) );
	}

	public function test_ip_session_lock_respects_request_bypass() :void {
		$rule = $this->primeRuleWithSession( '203.0.113.45', '203.0.113.46', [ 'ip' ] );
		$this->requireController()->this_req->request_bypasses_all_restrictions = true;

		$this->assertFalse( $this->runRuleConditions( $rule ) );
	}

	public function test_ip_session_lock_ignores_loopback_requests() :void {
		$rule = $this->primeRuleWithSession( '203.0.113.45', '127.0.0.1', [ 'ip' ] );

		$this->assertFalse( $this->runRuleConditions( $rule ) );
	}

	private function primeRuleWithSession( string $sessionIp, string $requestIp, array $sessionLock ) :RuleVO {
		$this->enablePremiumCapabilities();
		RuntimeTestState::restoreOptions( [
			'session_lock' => $sessionLock,
		] );

		$userID = $this->createAdministratorUser();
		\wp_set_current_user( $userID );

		$con = $this->requireController();
		$this->setRequestIdentity( $sessionIp );
		$token = \WP_Session_Tokens::get_instance( $userID )->create( \time() + \DAY_IN_SECONDS );
		$session = $con->comps->session->buildSession( $userID, $token );

		$this->resetRuleRequestState();
		$this->setRequestIdentity( $requestIp );
		$con->this_req->session = $session;

		return ( new LockSessionFailRuleBuilder() )->build();
	}

	private function setRequestIdentity( string $ip ) :void {
		$this->requireController()->this_req->ip = $ip;
		$this->requireController()->this_req->ip_id = $ip === '127.0.0.1' ? IpID::LOOPBACK : IpID::UNKNOWN;
		$this->requireController()->this_req->is_server_loopback = $ip === '127.0.0.1';
		$this->requireController()->this_req->host = 'example.org';
		$this->requireController()->this_req->useragent = 'Shield Session Lock Test';
	}

	private function resetRuleRequestState() :void {
		$this->resetIpCaches();

		$con = $this->requireController();
		$con->rules = new RulesController();
		$con->rules->setThisRequest( $con->this_req )->execute();
		$con->this_req->request_bypasses_all_restrictions = false;
		$con->this_req->request_subject_to_shield_restrictions = true;
		$con->this_req->path = '/wp-admin/profile.php';
		$con->this_req->wp_is_ajax = true;
	}

	private function runRuleConditions( RuleVO $rule ) :bool {
		$this->resetIpCaches();

		return ( new ProcessConditions( $rule->conditions ) )
			->setThisRequest( $this->requireController()->this_req )
			->process();
	}

	private function responseDefinition( array $responses, string $responseClass ) :array {
		$response = \current( \array_filter(
			$responses,
			static fn( array $resp ) :bool => ( $resp[ 'response' ] ?? '' ) === $responseClass
		) );

		$this->assertNotEmpty( $response, 'Rule must define response: '.$responseClass );
		return $response;
	}

	private function conditionMatches( string $conditionClass, array $params = [] ) :bool {
		$condition = new $conditionClass();
		return $condition
			->setThisRequest( $this->requireController()->this_req )
			->setParams( $params )
			->run();
	}

	private function eventOnlyRule( array $eventResponse, array $conditionMeta ) :RuleVO {
		return ( new RuleVO() )->applyFromArray( [
			'slug'                    => 'test_session_lock_event_contract',
			'name'                    => 'Test Session Lock Event Contract',
			'conditions'              => fn() => true,
			'responses'               => [ $eventResponse ],
			'immediate_exec_response' => true,
			'condition_meta'          => $conditionMeta,
		] );
	}
}
