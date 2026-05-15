<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Components\CompCons\RequestPolicy;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\RequestPolicy\{
	PolicyDecision,
	PolicyEvidence,
	PolicyEvidenceRecorder,
	PolicyRiskThresholds,
	PolicyStateRepository,
	RequestPolicyEvaluator,
	RequestProfile
};
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Responses,
	RuleVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;
use FernleafSystems\Wordpress\Services\Services;

class RequestPolicyGateIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;

	private array $optionSnapshot = [];

	private array $requestSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->optionSnapshot = $this->snapshotSelectedOptions( [
			'request_policy_mode',
			'request_policy_sensitivity',
		] );
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
		$this->requireDb( 'ip_policy_state' );
	}

	public function tear_down() {
		\wp_set_current_user( 0 );
		$this->restoreCurrentRequestState( $this->requestSnapshot );
		$this->restoreSelectedOptions( $this->optionSnapshot );
		parent::tear_down();
	}

	public function test_adaptive_firewall_log_only_for_trusted_mutation_does_not_fire_block_event() :void {
		$this->requireController()->opts->optSet( 'request_policy_mode', RequestPolicyEvaluator::MODE_ADAPTIVE );
		$this->loginAsSecurityAdmin();
		$this->applyCurrentRequestState(
			[
				'REMOTE_ADDR'    => '198.51.100.201',
				'REQUEST_METHOD' => 'POST',
				'REQUEST_URI'    => '/wp-admin/post.php',
			],
			[],
			[
				'post_title' => 'trusted mutation',
			],
			[
				'ip' => '198.51.100.201',
			]
		);
		$this->captureShieldEvents();

		$this->execPolicyGate(
			PolicyEvidence::DETECTOR_FIREWALL,
			$this->ruleWithMeta( [
				'match_category'      => 'sql_queries',
				'match_name'          => 'SQL Queries',
				'match_pattern'       => '#union.*select#i',
				'match_request_param' => 'post_title',
				'match_request_value' => 'trusted mutation',
				'match_type'          => 'regex',
			] ),
			$this->legacyFirewallResponses()
		);

		$decision = $this->latestPolicyDecision();
		$this->assertSame( PolicyDecision::DECISION_LOG_ONLY, $decision[ 'decision' ] ?? '' );
		$this->assertSame( 'firewall_trusted_mutation_log_only', $decision[ 'reason' ] ?? '' );
		$this->assertSame( RequestProfile::SURFACE_CONTENT_MUTATION, $decision[ 'surface' ] ?? '' );
		$this->assertSame( [], $this->getCapturedEventsByKey( 'firewall_block' ) );
		$this->assertSame( [], $this->getCapturedEventsByKey( 'ip_offense' ) );
		$this->assertSame( [], $this->getCapturedEventsByKey( 'ip_blocked' ) );
		$this->assertSame( [], $this->getCapturedEventsByKey( 'request_policy_block' ) );
	}

	public function test_shadow_mode_records_policy_decision_and_enforces_legacy_block_event() :void {
		$this->requireController()->opts->optSet( 'request_policy_mode', RequestPolicyEvaluator::MODE_SHADOW );
		\wp_set_current_user( 0 );
		$this->applyCurrentRequestState(
			[
				'REMOTE_ADDR'    => '198.51.100.204',
				'REQUEST_METHOD' => 'GET',
				'REQUEST_URI'    => '/blog/shadow-post/',
			],
			[],
			[],
			[
				'ip' => '198.51.100.204',
			]
		);
		$this->captureShieldEvents();

		$this->execPolicyGate(
			PolicyEvidence::DETECTOR_CROWDSEC,
			$this->ruleWithMeta( [], 'shield/is_ip_blocked_crowdsec' ),
			$this->safeLegacyEventResponses( 'conn_kill_crowdsec' )
		);

		$decision = $this->latestPolicyDecision();
		$this->assertSame( RequestPolicyEvaluator::MODE_SHADOW, $decision[ 'mode' ] ?? '' );
		$this->assertSame( PolicyDecision::DECISION_ALLOW, $decision[ 'decision' ] ?? '' );
		$this->assertSame( 'crowdsec_safe_read', $decision[ 'reason' ] ?? '' );
		$this->assertNotEmpty( $this->getCapturedEventsByKey( 'conn_kill_crowdsec' ) );
		$this->assertSame( [], $this->getCapturedEventsByKey( 'request_policy_block' ) );
	}

	public function test_adaptive_crowdsec_public_read_allows_without_legacy_block_event() :void {
		$this->requireController()->opts->optSet( 'request_policy_mode', RequestPolicyEvaluator::MODE_ADAPTIVE );
		$policyStateRowsBefore = $this->countPolicyStateRows();
		\wp_set_current_user( 0 );
		$this->applyCurrentRequestState(
			[
				'REMOTE_ADDR'    => '198.51.100.202',
				'REQUEST_METHOD' => 'GET',
				'REQUEST_URI'    => '/blog/example-post/',
			],
			[],
			[],
			[
				'ip' => '198.51.100.202',
			]
		);
		$this->captureShieldEvents();

		$this->execPolicyGate(
			PolicyEvidence::DETECTOR_CROWDSEC,
			$this->ruleWithMeta( [], 'shield/is_ip_blocked_crowdsec' ),
			$this->safeLegacyEventResponses( 'conn_kill_crowdsec' )
		);

		$decision = $this->latestPolicyDecision();
		$this->assertSame( PolicyDecision::DECISION_ALLOW, $decision[ 'decision' ] ?? '' );
		$this->assertSame( 'crowdsec_safe_read', $decision[ 'reason' ] ?? '' );
		$this->assertSame( RequestProfile::SURFACE_PUBLIC_READ, $decision[ 'surface' ] ?? '' );
		$this->assertSame( [], $this->getCapturedEventsByKey( 'conn_kill_crowdsec' ) );
		$this->assertSame( [], $this->getCapturedEventsByKey( 'request_policy_block' ) );
		$this->assertSame( $policyStateRowsBefore, $this->countPolicyStateRows() );
	}

	public function test_adaptive_shield_auto_block_public_read_allows_without_legacy_block_event() :void {
		$this->requireController()->opts->optSet( 'request_policy_mode', RequestPolicyEvaluator::MODE_ADAPTIVE );
		\wp_set_current_user( 0 );
		$this->applyCurrentRequestState(
			[
				'REMOTE_ADDR'    => '198.51.100.203',
				'REQUEST_METHOD' => 'GET',
				'REQUEST_URI'    => '/blog/another-post/',
			],
			[],
			[],
			[
				'ip' => '198.51.100.203',
			]
		);
		$this->captureShieldEvents();

		$this->execPolicyGate(
			PolicyEvidence::DETECTOR_SHIELD_IP,
			$this->ruleWithMeta( [], 'shield/is_ip_blocked_shield' ),
			$this->safeLegacyEventResponses( 'conn_kill' )
		);

		$decision = $this->latestPolicyDecision();
		$this->assertSame( PolicyDecision::DECISION_ALLOW, $decision[ 'decision' ] ?? '' );
		$this->assertSame( 'shield_auto_safe_read', $decision[ 'reason' ] ?? '' );
		$this->assertSame( RequestProfile::SURFACE_PUBLIC_READ, $decision[ 'surface' ] ?? '' );
		$this->assertSame( [], $this->getCapturedEventsByKey( 'conn_kill' ) );
		$this->assertSame( [], $this->getCapturedEventsByKey( 'request_policy_block' ) );
	}

	public function test_adaptive_crowdsec_login_with_recent_auth_failure_blocks() :void {
		$this->requireController()->opts->optSet( 'request_policy_mode', RequestPolicyEvaluator::MODE_ADAPTIVE );
		\wp_set_current_user( 0 );
		$ip = '198.51.100.205';
		$this->seedPolicyStateCounter( $ip, PolicyEvidence::TYPE_AUTH_ABUSE, '15m', 1 );
		$this->applyCurrentRequestState(
			[
				'REMOTE_ADDR'    => $ip,
				'REQUEST_METHOD' => 'POST',
				'REQUEST_URI'    => '/wp-login.php',
			],
			[],
			[
				'log' => 'editor',
				'pwd' => 'bad-password',
			],
			[
				'ip'   => $ip,
				'path' => '/wp-login.php',
			]
		);
		$this->captureShieldEvents();

		$this->execPolicyGate(
			PolicyEvidence::DETECTOR_CROWDSEC,
			$this->ruleWithMeta( [], 'shield/is_ip_blocked_crowdsec' ),
			$this->safeLegacyEventResponses( 'conn_kill_crowdsec' )
		);

		$decision = $this->latestPolicyDecision();
		$this->assertSame( PolicyDecision::DECISION_BLOCK_REQUEST, $decision[ 'decision' ] ?? '' );
		$this->assertSame( 'crowdsec_sensitive_request', $decision[ 'reason' ] ?? '' );
		$this->assertSame( RequestProfile::SURFACE_AUTH_ATTEMPT, $decision[ 'surface' ] ?? '' );
		$this->assertNotEmpty( $this->getCapturedEventsByKey( 'conn_kill_crowdsec' ) );
		$block = $this->latestPolicyBlock();
		$this->assertSame( PolicyDecision::BLOCK_CATEGORY_REPEATED_SUSPICIOUS_ACTIVITY, $block[ 'block_category' ] ?? '' );
		$this->assertSame( true, $block[ 'enforced' ] ?? null );
	}

	public function test_adaptive_shield_manual_block_always_blocks() :void {
		$this->requireDb( 'ips' );
		$this->requireDb( 'ip_rules' );
		$this->requireController()->opts->optSet( 'request_policy_mode', RequestPolicyEvaluator::MODE_ADAPTIVE );
		\wp_set_current_user( 0 );
		TestDataFactory::insertManualBlock( '198.51.100.206' );
		$this->applyCurrentRequestState(
			[
				'REMOTE_ADDR'    => '198.51.100.206',
				'REQUEST_METHOD' => 'GET',
				'REQUEST_URI'    => '/blog/manual-block/',
			],
			[],
			[],
			[
				'ip' => '198.51.100.206',
			]
		);
		$this->captureShieldEvents();

		$this->execPolicyGate(
			PolicyEvidence::DETECTOR_SHIELD_IP,
			$this->ruleWithMeta( [], 'shield/is_ip_blocked_shield' ),
			$this->safeLegacyEventResponses( 'conn_kill' )
		);

		$decision = $this->latestPolicyDecision();
		$this->assertSame( PolicyDecision::DECISION_BLOCK_REQUEST, $decision[ 'decision' ] ?? '' );
		$this->assertSame( 'shield_manual_block', $decision[ 'reason' ] ?? '' );
		$this->assertNotEmpty( $this->getCapturedEventsByKey( 'conn_kill' ) );
		$block = $this->latestPolicyBlock();
		$this->assertSame( PolicyDecision::BLOCK_CATEGORY_MANUAL_IP_BLOCK, $block[ 'block_category' ] ?? '' );
	}

	public function test_aggressive_single_offense_makes_crowdsec_public_read_hostile() :void {
		$this->requireController()->opts->optSet( 'request_policy_mode', RequestPolicyEvaluator::MODE_ADAPTIVE );
		$this->requireController()->opts->optSet( 'request_policy_sensitivity', PolicyRiskThresholds::SENSITIVITY_AGGRESSIVE );
		$ip = '198.51.100.207';
		$this->seedPolicyStateCounter( $ip, PolicyEvidence::TYPE_IP_ENFORCEMENT, '24h', 1 );
		\wp_set_current_user( 0 );
		$this->applyCurrentRequestState(
			[
				'REMOTE_ADDR'    => $ip,
				'REQUEST_METHOD' => 'GET',
				'REQUEST_URI'    => '/blog/aggressive-offense/',
			],
			[],
			[],
			[
				'ip' => $ip,
			]
		);
		$this->captureShieldEvents();

		$this->execPolicyGate(
			PolicyEvidence::DETECTOR_CROWDSEC,
			$this->ruleWithMeta( [], 'shield/is_ip_blocked_crowdsec' ),
			$this->safeLegacyEventResponses( 'conn_kill_crowdsec' )
		);

		$decision = $this->latestPolicyDecision();
		$this->assertSame( PolicyDecision::DECISION_BLOCK_REQUEST, $decision[ 'decision' ] ?? '' );
		$this->assertSame( 'threshold_ip_enforcement', $decision[ 'risk_reason' ] ?? '' );
		$this->assertSame( PolicyDecision::BLOCK_CATEGORY_REPEATED_SUSPICIOUS_ACTIVITY, $this->latestPolicyBlock()[ 'block_category' ] ?? '' );
	}

	public function test_balanced_single_offense_does_not_make_crowdsec_public_read_hostile() :void {
		$this->requireController()->opts->optSet( 'request_policy_mode', RequestPolicyEvaluator::MODE_ADAPTIVE );
		$this->requireController()->opts->optSet( 'request_policy_sensitivity', PolicyRiskThresholds::SENSITIVITY_BALANCED );
		$ip = '198.51.100.208';
		$this->seedPolicyStateCounter( $ip, PolicyEvidence::TYPE_IP_ENFORCEMENT, '24h', 1 );
		\wp_set_current_user( 0 );
		$this->applyCurrentRequestState(
			[
				'REMOTE_ADDR'    => $ip,
				'REQUEST_METHOD' => 'GET',
				'REQUEST_URI'    => '/blog/balanced-offense/',
			],
			[],
			[],
			[
				'ip' => $ip,
			]
		);
		$this->captureShieldEvents();

		$this->execPolicyGate(
			PolicyEvidence::DETECTOR_CROWDSEC,
			$this->ruleWithMeta( [], 'shield/is_ip_blocked_crowdsec' ),
			$this->safeLegacyEventResponses( 'conn_kill_crowdsec' )
		);

		$decision = $this->latestPolicyDecision();
		$this->assertSame( PolicyDecision::DECISION_ALLOW, $decision[ 'decision' ] ?? '' );
		$this->assertSame( 'crowdsec_signal', $decision[ 'risk_reason' ] ?? '' );
		$this->assertSame( [], $this->getCapturedEventsByKey( 'request_policy_block' ) );
	}

	public function test_request_limit_evidence_makes_policy_state_hostile() :void {
		$this->requireController()->opts->optSet( 'request_policy_mode', RequestPolicyEvaluator::MODE_ADAPTIVE );
		$ip = '198.51.100.209';
		$recorder = new PolicyEvidenceRecorder( new PolicyStateRepository() );
		$recorder->record( $ip, new PolicyEvidence( [
			'type'         => PolicyEvidence::TYPE_RATE_ABUSE,
			'severity'     => PolicyEvidence::SEVERITY_CRITICAL,
			'source_event' => 'request_limit_exceeded',
		] ) );
		$recorder->flush();
		\wp_set_current_user( 0 );
		$this->applyCurrentRequestState(
			[
				'REMOTE_ADDR'    => $ip,
				'REQUEST_METHOD' => 'GET',
				'REQUEST_URI'    => '/blog/rate-limit/',
			],
			[],
			[],
			[
				'ip' => $ip,
			]
		);
		$this->captureShieldEvents();

		$this->execPolicyGate(
			PolicyEvidence::DETECTOR_CROWDSEC,
			$this->ruleWithMeta( [], 'shield/is_ip_blocked_crowdsec' ),
			$this->safeLegacyEventResponses( 'conn_kill_crowdsec' )
		);

		$decision = $this->latestPolicyDecision();
		$this->assertSame( PolicyDecision::DECISION_BLOCK_REQUEST, $decision[ 'decision' ] ?? '' );
		$this->assertSame( 'persisted_hostile', $decision[ 'risk_reason' ] ?? '' );
		$this->assertSame( PolicyDecision::BLOCK_CATEGORY_HOSTILE_IP, $this->latestPolicyBlock()[ 'block_category' ] ?? '' );
	}

	public function test_shadow_block_fires_policy_block_without_adaptive_enforcement() :void {
		$this->requireController()->opts->optSet( 'request_policy_mode', RequestPolicyEvaluator::MODE_SHADOW );
		$ip = '198.51.100.210';
		\wp_set_current_user( 0 );
		$this->applyCurrentRequestState(
			[
				'REMOTE_ADDR'    => $ip,
				'REQUEST_METHOD' => 'POST',
				'REQUEST_URI'    => '/wp-admin/admin.php',
			],
			[],
			[],
			[
				'ip' => $ip,
			]
		);
		$this->captureShieldEvents();

		$this->execPolicyGate(
			PolicyEvidence::DETECTOR_CROWDSEC,
			$this->ruleWithMeta( [], 'shield/is_ip_blocked_crowdsec' ),
			$this->safeLegacyEventResponses( 'conn_kill_crowdsec' )
		);

		$block = $this->latestPolicyBlock();
		$this->assertSame( false, $block[ 'enforced' ] ?? null );
		$this->assertNotEmpty( $this->getCapturedEventsByKey( 'conn_kill_crowdsec' ) );
	}

	public function test_legacy_mode_fires_no_policy_observability_events() :void {
		$this->requireController()->opts->optSet( 'request_policy_mode', RequestPolicyEvaluator::MODE_LEGACY );
		\wp_set_current_user( 0 );
		$this->applyCurrentRequestState(
			[
				'REMOTE_ADDR'    => '198.51.100.211',
				'REQUEST_METHOD' => 'GET',
				'REQUEST_URI'    => '/blog/legacy/',
			],
			[],
			[],
			[
				'ip' => '198.51.100.211',
			]
		);
		$this->captureShieldEvents();

		$this->execPolicyGate(
			PolicyEvidence::DETECTOR_CROWDSEC,
			$this->ruleWithMeta( [], 'shield/is_ip_blocked_crowdsec' ),
			$this->safeLegacyEventResponses( 'conn_kill_crowdsec' )
		);

		$this->assertSame( [], $this->getCapturedEventsByKey( 'request_policy_decision' ) );
		$this->assertSame( [], $this->getCapturedEventsByKey( 'request_policy_block' ) );
	}

	private function execPolicyGate( string $detector, RuleVO $rule, array $legacyResponses ) :void {
		( new Responses\RequestPolicyGate() )
			->setThisRequest( $this->requireController()->this_req )
			->setRule( $rule )
			->setParams( [
				'detector'         => $detector,
				'legacy_responses' => $legacyResponses,
			] )
			->execResponse();
	}

	private function safeLegacyEventResponses( string $event ) :array {
		return [
			[
				'response' => Responses\EventFire::class,
				'params'   => [
					'event' => $event,
				],
			],
		];
	}

	private function legacyFirewallResponses() :array {
		return [
			[
				'response' => Responses\EventFire::class,
				'params'   => [
					'event'            => 'firewall_block',
					'offense_count'    => 1,
					'block'            => false,
					'audit_params_map' => [
						'scan' => 'match_category',
					],
				],
			],
			[
				'response' => Responses\FirewallBlock::class,
				'params'   => [],
			],
		];
	}

	private function ruleWithMeta( array $conditionMeta, string $slug = 'shield/firewall' ) :RuleVO {
		return ( new RuleVO() )->applyFromArray( [
			'slug'           => $slug,
			'name'           => 'Request Policy Gate Integration',
			'condition_meta' => $conditionMeta,
		] );
	}

	private function latestPolicyDecision() :array {
		$events = $this->getCapturedEventsByKey( 'request_policy_decision' );
		$this->assertNotEmpty( $events );
		return $events[ \count( $events ) - 1 ][ 'meta' ][ 'audit_params' ] ?? [];
	}

	private function latestPolicyBlock() :array {
		$events = $this->getCapturedEventsByKey( 'request_policy_block' );
		$this->assertNotEmpty( $events );
		return $events[ \count( $events ) - 1 ][ 'meta' ][ 'audit_params' ] ?? [];
	}

	private function seedPolicyStateCounter( string $ip, string $evidenceType, string $window, int $count ) :void {
		$now = Services::Request()->ts();
		$repository = new PolicyStateRepository();
		$state = $repository->forIp( $ip );
		$state->last_evidence_at = $now;
		$state->expires_at = $now + DAY_IN_SECONDS;
		$state->meta = [
			'evidence' => [
				$evidenceType => [
					$window => [
						'started_at' => $now,
						'count'      => $count,
					],
				],
			],
		];
		$state->dirty = true;
		$this->assertTrue( $repository->save( $state ) );
	}

	private function countPolicyStateRows() :int {
		return (int)Services::WpDb()->getVar(
			sprintf( 'SELECT COUNT(*) FROM `%s`;', $this->requireController()->db_con->ip_policy_state->getTableSchema()->table )
		);
	}
}
