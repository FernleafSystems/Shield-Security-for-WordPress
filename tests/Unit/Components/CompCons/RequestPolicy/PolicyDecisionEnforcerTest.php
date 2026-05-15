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
				'type'           => PolicyEvidence::TYPE_FIREWALL_NOISY,
				'severity'       => PolicyEvidence::SEVERITY_NOISY,
				'rule_slug'      => 'shield/firewall',
				'condition_meta' => [
					'match_category' => 'sql_queries',
				],
			] ),
			$this->rule(),
			[]
		);

		$this->assertSame( [ PolicyEvidence::TYPE_FIREWALL_LOG ], $recorder->recordedTypes );
		$this->assertSame( [ PolicyDecision::DECISION_LOG_ONLY ], $recorder->decisions );
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

		$this->assertSame( [ PolicyEvidence::TYPE_CROWDSEC ], $recorder->recordedTypes );
		$this->assertSame( [ PolicyDecision::DECISION_BLOCK_REQUEST ], $recorder->decisions );
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

		$this->assertSame( [ PolicyEvidence::TYPE_CROWDSEC ], $recorder->recordedTypes );
		$this->assertSame( [ PolicyDecision::DECISION_BLOCK_REQUEST ], $recorder->decisions );
	}

	public function test_firewall_policy_block_does_not_direct_record_firewall_evidence() :void {
		$recorder = new PolicyDecisionEnforcerRecorderStub();
		$this->installController( new PolicyDecisionEnforcerEventsStub() );

		( new PolicyDecisionEnforcer( $recorder ) )->enforce(
			$this->decision( PolicyDecision::DECISION_BLOCK_REQUEST, 'firewall_untrusted_actor' ),
			new PolicyEvidence( [
				'detector'  => PolicyEvidence::DETECTOR_FIREWALL,
				'type'      => PolicyEvidence::TYPE_FIREWALL_NOISY,
				'severity'  => PolicyEvidence::SEVERITY_NOISY,
				'rule_slug' => 'shield/firewall',
			] ),
			$this->rule(),
			[]
		);

		$this->assertSame( [], $recorder->recordedTypes );
		$this->assertSame( [ PolicyDecision::DECISION_BLOCK_REQUEST ], $recorder->decisions );
	}

	public function test_action_router_allow_records_suppressed_ip_evidence_and_allows_request() :void {
		$recorder = new PolicyDecisionEnforcerRecorderStub();
		$this->installController( new PolicyDecisionEnforcerEventsStub() );

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
		$this->assertSame( [ PolicyEvidence::TYPE_SHIELD_AUTO_BLOCK ], $recorder->recordedTypes );
		$this->assertSame( [ PolicyDecision::DECISION_ALLOW ], $recorder->decisions );
	}

	public function test_action_router_no_decision_records_evidence_but_does_not_allow_request() :void {
		$recorder = new PolicyDecisionEnforcerRecorderStub();
		$this->installController( new PolicyDecisionEnforcerEventsStub() );

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
		$this->assertSame( [ PolicyEvidence::TYPE_NONE ], $recorder->recordedTypes );
		$this->assertSame( [ PolicyDecision::DECISION_NO_DECISION ], $recorder->decisions );
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
		$this->assertSame( [ PolicyEvidence::TYPE_SHIELD_AUTO_BLOCK ], $recorder->recordedTypes );
		$this->assertSame( [ PolicyDecision::DECISION_BLOCK_REQUEST ], $recorder->decisions );
		$this->assertSame( [ 'request_policy_decision' ], $events->events );
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
			'mode'      => $mode,
			'detector'  => $detector,
			'surface'   => RequestProfile::SURFACE_CONTENT_MUTATION,
			'decision'  => $decision,
			'reason'    => $reason,
			'rule_slug' => 'test/rule',
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

	public function fireEvent( string $event, array $meta = [] ) :void {
		unset( $meta );
		$this->events[] = $event;
	}
}

class PolicyDecisionEnforcerRecorderStub extends PolicyEvidenceRecorder {

	public array $recordedTypes = [];

	public array $decisions = [];

	public function __construct() {
	}

	public function record( string $ip, PolicyEvidence $evidence ) :\FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\RequestPolicy\PolicyState {
		unset( $ip );
		$this->recordedTypes[] = $evidence->type;
		return new \FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\RequestPolicy\PolicyState();
	}

	public function markDecision( string $ip, PolicyDecision $decision ) :void {
		unset( $ip );
		$this->decisions[] = $decision->decision;
	}
}
