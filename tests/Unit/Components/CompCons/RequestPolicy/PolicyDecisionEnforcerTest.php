<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons\RequestPolicy;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\RequestPolicy\{
	PolicyDecision,
	PolicyDecisionEnforcer,
	PolicyEvidence,
	PolicyEvidenceRecorder,
	RequestPolicyEvaluator,
	RequestProfile
};
use FernleafSystems\Wordpress\Plugin\Shield\Rules\RuleVO;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	UnitTestControllerFactory
};

class PolicyDecisionEnforcerTest extends BaseUnitTest {

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_log_only_records_firewall_log_evidence_without_legacy_dispatch() :void {
		$events = new PolicyDecisionEnforcerEventsStub();
		$recorder = new PolicyDecisionEnforcerRecorderStub();
		$this->installController( $events );

		( new PolicyDecisionEnforcer( $recorder ) )->enforce(
			$this->decision( PolicyDecision::DECISION_LOG_ONLY, 'firewall_trusted_mutation_log_only' ),
			new PolicyEvidence( [
				'detector'       => PolicyEvidence::DETECTOR_FIREWALL,
				'type'           => PolicyEvidence::TYPE_FIREWALL_ABUSE,
				'severity'       => PolicyEvidence::SEVERITY_NOISY,
				'rule_slug'      => 'shield/firewall',
				'condition_meta' => [
					'match_category' => 'sql_queries',
				],
			] ),
			$this->rule(),
			[]
		);

		$this->assertSame( [ PolicyEvidence::TYPE_FIREWALL_ABUSE ], $recorder->recordedTypes );
		$this->assertSame( [ 'request_policy_decision' ], $events->events );
	}

	public function test_crowdsec_policy_block_records_evidence_without_global_listener() :void {
		$recorder = new PolicyDecisionEnforcerRecorderStub();
		$this->installController( new PolicyDecisionEnforcerEventsStub() );

		( new PolicyDecisionEnforcer( $recorder ) )->enforce(
			$this->decision( PolicyDecision::DECISION_BLOCK_REQUEST, 'crowdsec_sensitive_request', PolicyEvidence::DETECTOR_CROWDSEC ),
			new PolicyEvidence( [
				'detector'  => PolicyEvidence::DETECTOR_CROWDSEC,
				'type'      => PolicyEvidence::TYPE_CROWDSEC,
				'severity'  => PolicyEvidence::SEVERITY_SIGNAL,
				'rule_slug' => 'shield/is_ip_blocked_crowdsec',
			] ),
			$this->rule(),
			[]
		);

		$this->assertSame( [ PolicyEvidence::TYPE_IP_ENFORCEMENT ], $recorder->recordedTypes );
	}

	public function test_shadow_crowdsec_policy_block_records_evidence_without_global_listener() :void {
		$recorder = new PolicyDecisionEnforcerRecorderStub();
		$this->installController( new PolicyDecisionEnforcerEventsStub() );

		( new PolicyDecisionEnforcer( $recorder ) )->enforce(
			$this->decision( PolicyDecision::DECISION_BLOCK_REQUEST, 'crowdsec_sensitive_request', PolicyEvidence::DETECTOR_CROWDSEC, RequestPolicyEvaluator::MODE_SHADOW ),
			new PolicyEvidence( [
				'detector'  => PolicyEvidence::DETECTOR_CROWDSEC,
				'type'      => PolicyEvidence::TYPE_CROWDSEC,
				'severity'  => PolicyEvidence::SEVERITY_SIGNAL,
				'rule_slug' => 'shield/is_ip_blocked_crowdsec',
			] ),
			$this->rule(),
			[]
		);

		$this->assertSame( [ PolicyEvidence::TYPE_IP_ENFORCEMENT ], $recorder->recordedTypes );
	}

	public function test_firewall_policy_block_does_not_direct_record_firewall_evidence() :void {
		$recorder = new PolicyDecisionEnforcerRecorderStub();
		$this->installController( new PolicyDecisionEnforcerEventsStub() );

		( new PolicyDecisionEnforcer( $recorder ) )->enforce(
			$this->decision( PolicyDecision::DECISION_BLOCK_REQUEST, 'firewall_untrusted_actor' ),
			new PolicyEvidence( [
				'detector'  => PolicyEvidence::DETECTOR_FIREWALL,
				'type'      => PolicyEvidence::TYPE_FIREWALL_ABUSE,
				'severity'  => PolicyEvidence::SEVERITY_NOISY,
				'rule_slug' => 'shield/firewall',
			] ),
			$this->rule(),
			[]
		);

		$this->assertSame( [], $recorder->recordedTypes );
	}

	public function test_rule_allow_fires_decision_event_without_recording_evidence() :void {
		$events = new PolicyDecisionEnforcerEventsStub();
		$recorder = new PolicyDecisionEnforcerRecorderStub();
		$this->installController( $events );

		( new PolicyDecisionEnforcer( $recorder ) )->enforce(
			$this->decision( PolicyDecision::DECISION_ALLOW, 'test_allow', PolicyEvidence::DETECTOR_EVENT ),
			new PolicyEvidence( [
				'detector'  => PolicyEvidence::DETECTOR_EVENT,
				'type'      => PolicyEvidence::TYPE_NONE,
				'severity'  => PolicyEvidence::SEVERITY_INFO,
				'rule_slug' => 'test/rule',
			] ),
			$this->rule(),
			[]
		);

		$this->assertSame( [], $recorder->recordedTypes );
		$this->assertSame( [ 'request_policy_decision' ], $events->events );
	}

	public function test_rule_no_decision_fires_decision_event_without_recording_evidence() :void {
		$events = new PolicyDecisionEnforcerEventsStub();
		$recorder = new PolicyDecisionEnforcerRecorderStub();
		$this->installController( $events );

		( new PolicyDecisionEnforcer( $recorder ) )->enforce(
			$this->decision( PolicyDecision::DECISION_NO_DECISION, 'insufficient_information', PolicyEvidence::DETECTOR_EVENT ),
			new PolicyEvidence( [
				'detector'  => PolicyEvidence::DETECTOR_EVENT,
				'type'      => PolicyEvidence::TYPE_NONE,
				'severity'  => PolicyEvidence::SEVERITY_INFO,
				'rule_slug' => 'test/rule',
			] ),
			$this->rule(),
			[]
		);

		$this->assertSame( [], $recorder->recordedTypes );
		$this->assertSame( [ 'request_policy_decision' ], $events->events );
	}

	public function test_action_router_allow_fires_decision_event_without_recording_evidence_and_allows_request() :void {
		$events = new PolicyDecisionEnforcerEventsStub();
		$recorder = new PolicyDecisionEnforcerRecorderStub();
		$this->installController( $events );

		$allowed = ( new PolicyDecisionEnforcer( $recorder ) )->allowsActionRouterRequest(
			$this->decision( PolicyDecision::DECISION_ALLOW, 'shield_auto_safe_read', PolicyEvidence::DETECTOR_SHIELD_IP ),
			new PolicyEvidence( [
				'detector'  => PolicyEvidence::DETECTOR_SHIELD_IP,
				'type'      => PolicyEvidence::TYPE_SHIELD_AUTO_BLOCK,
				'severity'  => PolicyEvidence::SEVERITY_SIGNAL,
				'rule_slug' => 'action_router_ip_guard',
			] )
		);

		$this->assertTrue( $allowed );
		$this->assertSame( [], $recorder->recordedTypes );
		$this->assertSame( [ 'request_policy_decision' ], $events->events );
	}

	public function test_action_router_no_decision_fires_decision_event_without_recording_evidence_and_denies_request() :void {
		$events = new PolicyDecisionEnforcerEventsStub();
		$recorder = new PolicyDecisionEnforcerRecorderStub();
		$this->installController( $events );

		$allowed = ( new PolicyDecisionEnforcer( $recorder ) )->allowsActionRouterRequest(
			$this->decision( PolicyDecision::DECISION_NO_DECISION, 'insufficient_information', PolicyEvidence::DETECTOR_EVENT ),
			new PolicyEvidence( [
				'detector'  => PolicyEvidence::DETECTOR_EVENT,
				'type'      => PolicyEvidence::TYPE_NONE,
				'severity'  => PolicyEvidence::SEVERITY_INFO,
				'rule_slug' => 'action_router_ip_guard',
			] )
		);

		$this->assertFalse( $allowed );
		$this->assertSame( [], $recorder->recordedTypes );
		$this->assertSame( [ 'request_policy_decision' ], $events->events );
	}

	public function test_shadow_action_router_block_records_evidence_and_denies_request() :void {
		$events = new PolicyDecisionEnforcerEventsStub();
		$recorder = new PolicyDecisionEnforcerRecorderStub();
		$this->installController( $events );

		$allowed = ( new PolicyDecisionEnforcer( $recorder ) )->allowsActionRouterRequest(
			$this->decision(
				PolicyDecision::DECISION_BLOCK_REQUEST,
				'shield_auto_sensitive_request',
				PolicyEvidence::DETECTOR_SHIELD_IP,
				RequestPolicyEvaluator::MODE_SHADOW
			),
			new PolicyEvidence( [
				'detector'  => PolicyEvidence::DETECTOR_SHIELD_IP,
				'type'      => PolicyEvidence::TYPE_SHIELD_AUTO_BLOCK,
				'severity'  => PolicyEvidence::SEVERITY_SIGNAL,
				'rule_slug' => 'action_router_ip_guard',
			] )
		);

		$this->assertFalse( $allowed );
		$this->assertSame( [ PolicyEvidence::TYPE_IP_ENFORCEMENT ], $recorder->recordedTypes );
		$this->assertSame( [ 'request_policy_decision', 'request_policy_block' ], $events->events );
		$this->assertSame( false, $events->auditParamsByEvent[ 'request_policy_block' ][ 0 ][ 'enforced' ] ?? null );
	}

	public function test_manual_shield_block_fires_block_event_without_recording_policy_state() :void {
		$events = new PolicyDecisionEnforcerEventsStub();
		$recorder = new PolicyDecisionEnforcerRecorderStub();
		$this->installController( $events );

		( new PolicyDecisionEnforcer( $recorder ) )->enforce(
			$this->decision(
				PolicyDecision::DECISION_BLOCK_REQUEST,
				'shield_manual_block',
				PolicyEvidence::DETECTOR_SHIELD_IP
			),
			new PolicyEvidence( [
				'detector'  => PolicyEvidence::DETECTOR_SHIELD_IP,
				'type'      => PolicyEvidence::TYPE_SHIELD_MANUAL_BLOCK,
				'severity'  => PolicyEvidence::SEVERITY_CRITICAL,
				'rule_slug' => 'shield/is_ip_blocked_shield',
			] ),
			$this->rule(),
			[]
		);

		$this->assertSame( [], $recorder->recordedTypes );
		$this->assertSame( [ 'request_policy_decision', 'request_policy_block' ], $events->events );
	}

	private function installController( PolicyDecisionEnforcerEventsStub $events ) :void {
		UnitTestControllerFactory::install( null, null, (object)[
			'this_req' => (object)[
				'ip' => '198.51.100.44',
			],
			'comps'    => (object)[
				'events' => $events,
			],
		] );
	}

	private function decision(
		string $decision,
		string $reason,
		string $detector = PolicyEvidence::DETECTOR_FIREWALL,
		string $mode = RequestPolicyEvaluator::MODE_ADAPTIVE
	) :PolicyDecision {
		return new PolicyDecision( [
			'mode'           => $mode,
			'sensitivity'    => 'balanced',
			'detector'       => $detector,
			'surface'        => RequestProfile::SURFACE_CONTENT_MUTATION,
			'risk_band'      => $decision === PolicyDecision::DECISION_BLOCK_REQUEST
				? \FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\RequestPolicy\PolicyState::BAND_SUSPICIOUS
				: \FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\RequestPolicy\PolicyState::BAND_NORMAL,
			'risk_reason'    => $decision === PolicyDecision::DECISION_BLOCK_REQUEST ? 'crowdsec_signal' : 'none',
			'decision'       => $decision,
			'reason'         => $reason,
			'block_category' => $decision === PolicyDecision::DECISION_BLOCK_REQUEST
				? PolicyDecision::BLOCK_CATEGORY_SENSITIVE_REQUEST
				: '',
			'rule_slug'      => 'test/rule',
		] );
	}

	private function rule() :RuleVO {
		return ( new RuleVO() )->applyFromArray( [
			'slug' => 'test/rule',
		] );
	}
}

class PolicyDecisionEnforcerEventsStub {

	public array $events = [];

	public array $auditParamsByEvent = [];

	public function fireEvent( string $event, array $meta = [] ) :void {
		$this->events[] = $event;
		$this->auditParamsByEvent[ $event ][] = $meta[ 'audit_params' ] ?? [];
	}
}

class PolicyDecisionEnforcerRecorderStub extends PolicyEvidenceRecorder {

	public array $recordedTypes = [];

	public function __construct() {
	}

	public function record( string $ip, PolicyEvidence $evidence ) :\FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\RequestPolicy\PolicyState {
		unset( $ip );
		$this->recordedTypes[] = $evidence->type;
		return new \FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\RequestPolicy\PolicyState();
	}
}
