<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons\RequestPolicy;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\RequestPolicy\{
	ActorTrust,
	PolicyDecision,
	PolicyEvidence,
	PolicyState,
	RequestPolicyEvaluator,
	RequestProfile
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class RequestPolicyEvaluatorTest extends BaseUnitTest {

	/**
	 * @dataProvider provideDecisionScenarios
	 */
	public function testDeterministicPolicyDecisions( array $scenario ) :void {
		$decision = ( new RequestPolicyEvaluator() )->evaluate(
			$scenario[ 'mode' ],
			new RequestProfile( $scenario[ 'profile' ] ),
			new ActorTrust( $scenario[ 'actor' ] ),
			new PolicyState( [
				'risk_band' => $scenario[ 'risk_band' ] ?? PolicyState::BAND_NORMAL,
				'meta'      => $this->stateMetaFromCounters( $scenario[ 'counters' ] ?? [] ),
			] ),
			new PolicyEvidence( $scenario[ 'evidence' ] )
		);

		$this->assertSame( $scenario[ 'expected_decision' ], $decision->decision );
		$this->assertSame( $scenario[ 'expected_reason' ], $decision->reason );
		$this->assertSame( $scenario[ 'expected_risk_band' ], $decision->risk_band );
		$this->assertSame( $scenario[ 'mode' ], $decision->mode );
		$this->assertSame( $scenario[ 'evidence' ][ 'detector' ], $decision->detector );
		$this->assertSame( $scenario[ 'profile' ][ 'surface' ], $decision->surface );
	}

	public function provideDecisionScenarios() :array {
		$firewallNoisy = [
			'detector'       => PolicyEvidence::DETECTOR_FIREWALL,
			'type'           => PolicyEvidence::TYPE_FIREWALL_NOISY,
			'severity'       => PolicyEvidence::SEVERITY_NOISY,
			'rule_slug'      => 'shield/firewall',
			'condition_meta' => [
				'match_category' => 'sql_queries',
			],
		];

		$firewallCritical = $firewallNoisy;
		$firewallCritical[ 'type' ] = PolicyEvidence::TYPE_FIREWALL_CRITICAL;
		$firewallCritical[ 'severity' ] = PolicyEvidence::SEVERITY_CRITICAL;
		$firewallCritical[ 'condition_meta' ][ 'match_category' ] = 'php_code';

		$scenarios = [
			'legacy firewall keeps current enforcement'                => $this->scenario( [
				'mode'              => RequestPolicyEvaluator::MODE_LEGACY,
				'evidence'          => $firewallNoisy,
				'ip_status'         => 'none',
				'expected_decision' => PolicyDecision::DECISION_LEGACY,
				'expected_reason'   => 'legacy_mode',
			] ),
			'shadow computes adaptive firewall result without owning enforcement' => $this->scenario( [
				'mode'              => RequestPolicyEvaluator::MODE_SHADOW,
				'profile'           => [ 'method' => 'POST', 'surface' => RequestProfile::SURFACE_CONTENT_MUTATION ],
				'actor'             => [ 'is_logged_in' => true, 'is_security_admin' => true ],
				'actor_key'         => 'logged-in trusted',
				'ip_status'         => 'none',
				'evidence'          => $firewallNoisy,
				'expected_decision' => PolicyDecision::DECISION_LOG_ONLY,
				'expected_reason'   => 'firewall_trusted_mutation_log_only',
			] ),
			'trusted logged-in mutation with noisy firewall is log-only' => $this->scenario( [
				'profile'           => [ 'method' => 'POST', 'surface' => RequestProfile::SURFACE_CONTENT_MUTATION ],
				'actor'             => [ 'is_logged_in' => true, 'is_high_reputation_ip' => true ],
				'actor_key'         => 'logged-in trusted',
				'ip_status'         => 'none',
				'evidence'          => $firewallNoisy,
				'expected_decision' => PolicyDecision::DECISION_LOG_ONLY,
				'expected_reason'   => 'firewall_trusted_mutation_log_only',
			] ),
			'anonymous noisy firewall blocks'                           => $this->scenario( [
				'profile'           => [ 'method' => 'POST', 'surface' => RequestProfile::SURFACE_CONTENT_MUTATION ],
				'ip_status'         => 'none',
				'evidence'          => $firewallNoisy,
				'expected_decision' => PolicyDecision::DECISION_BLOCK_REQUEST,
				'expected_reason'   => 'firewall_untrusted_actor',
			] ),
			'logged-in low trust noisy firewall still blocks'            => $this->scenario( [
				'profile'           => [ 'method' => 'POST', 'surface' => RequestProfile::SURFACE_CONTENT_MUTATION ],
				'actor'             => [ 'is_logged_in' => true ],
				'actor_key'         => 'logged-in low trust',
				'ip_status'         => 'none',
				'evidence'          => $firewallNoisy,
				'expected_decision' => PolicyDecision::DECISION_BLOCK_REQUEST,
				'expected_reason'   => 'firewall_untrusted_actor',
			] ),
			'trusted service api mutation with noisy firewall is log-only' => $this->scenario( [
				'profile'           => [ 'method' => 'PUT', 'surface' => RequestProfile::SURFACE_API_MUTATION ],
				'actor'             => [ 'is_trusted_service' => true ],
				'actor_key'         => 'trusted service',
				'ip_status'         => 'none',
				'evidence'          => $firewallNoisy,
				'expected_decision' => PolicyDecision::DECISION_LOG_ONLY,
				'expected_reason'   => 'firewall_trusted_mutation_log_only',
			] ),
			'critical firewall always blocks trusted actor'              => $this->scenario( [
				'profile'            => [ 'method' => 'POST', 'surface' => RequestProfile::SURFACE_CONTENT_MUTATION ],
				'actor'              => [ 'is_logged_in' => true, 'is_security_admin' => true ],
				'evidence'           => $firewallCritical,
				'actor_key'          => 'logged-in trusted',
				'ip_status'          => 'none',
				'expected_decision'  => PolicyDecision::DECISION_BLOCK_REQUEST,
				'expected_reason'    => 'firewall_critical',
				'expected_risk_band' => PolicyState::BAND_HOSTILE,
			] ),
			'crowdsec adaptive allows public read'                       => $this->scenario( [
				'profile'            => [ 'method' => 'GET', 'surface' => RequestProfile::SURFACE_PUBLIC_READ ],
				'evidence'           => $this->crowdsecEvidence(),
				'expected_decision'  => PolicyDecision::DECISION_ALLOW,
				'expected_reason'    => 'crowdsec_safe_read',
				'expected_risk_band' => PolicyState::BAND_SUSPICIOUS,
			] ),
			'crowdsec adaptive allows api read'                          => $this->scenario( [
				'profile'            => [ 'method' => 'GET', 'surface' => RequestProfile::SURFACE_API_READ ],
				'evidence'           => $this->crowdsecEvidence(),
				'expected_decision'  => PolicyDecision::DECISION_ALLOW,
				'expected_reason'    => 'crowdsec_safe_read',
				'expected_risk_band' => PolicyState::BAND_SUSPICIOUS,
			] ),
			'crowdsec adaptive allows clean auth attempt'                 => $this->scenario( [
				'profile'            => [ 'method' => 'POST', 'surface' => RequestProfile::SURFACE_AUTH_ATTEMPT ],
				'evidence'           => $this->crowdsecEvidence(),
				'expected_decision'  => PolicyDecision::DECISION_ALLOW,
				'expected_reason'    => 'crowdsec_clean_auth_attempt',
				'expected_risk_band' => PolicyState::BAND_SUSPICIOUS,
			] ),
			'crowdsec adaptive blocks auth attempt with recent auth failure' => $this->scenario( [
				'profile'            => [ 'method' => 'POST', 'surface' => RequestProfile::SURFACE_AUTH_ATTEMPT ],
				'evidence'           => $this->crowdsecEvidence(),
				'counters'           => [
					PolicyEvidence::TYPE_AUTH_FAILURE => [ '15m' => 1 ],
				],
				'expected_decision'  => PolicyDecision::DECISION_BLOCK_REQUEST,
				'expected_reason'    => 'crowdsec_sensitive_request',
				'expected_risk_band' => PolicyState::BAND_SUSPICIOUS,
			] ),
			'crowdsec adaptive blocks auth attempt with recent invalid username' => $this->scenario( [
				'profile'            => [ 'method' => 'POST', 'surface' => RequestProfile::SURFACE_AUTH_ATTEMPT ],
				'evidence'           => $this->crowdsecEvidence(),
				'counters'           => [
					PolicyEvidence::TYPE_AUTH_INVALID_USER => [ '15m' => 1 ],
				],
				'expected_decision'  => PolicyDecision::DECISION_BLOCK_REQUEST,
				'expected_reason'    => 'crowdsec_sensitive_request',
				'expected_risk_band' => PolicyState::BAND_SUSPICIOUS,
			] ),
			'crowdsec adaptive blocks hostile IP'                         => $this->scenario( [
				'profile'            => [ 'method' => 'GET', 'surface' => RequestProfile::SURFACE_PUBLIC_READ ],
				'evidence'           => $this->crowdsecEvidence(),
				'counters'           => [
					PolicyEvidence::TYPE_AUTH_FAILURE => [ '15m' => 3 ],
				],
				'expected_decision'  => PolicyDecision::DECISION_BLOCK_REQUEST,
				'expected_reason'    => 'crowdsec_hostile_risk',
				'expected_risk_band' => PolicyState::BAND_HOSTILE,
			] ),
			'crowdsec adaptive blocks mutation surfaces'                  => $this->scenario( [
				'profile'            => [ 'method' => 'POST', 'surface' => RequestProfile::SURFACE_CONTENT_MUTATION ],
				'evidence'           => $this->crowdsecEvidence(),
				'expected_decision'  => PolicyDecision::DECISION_BLOCK_REQUEST,
				'expected_reason'    => 'crowdsec_sensitive_request',
				'expected_risk_band' => PolicyState::BAND_SUSPICIOUS,
			] ),
			'crowdsec adaptive blocks delete shield action'               => $this->scenario( [
				'profile'            => [ 'method' => 'DELETE', 'surface' => RequestProfile::SURFACE_SHIELD_ACTION ],
				'evidence'           => $this->crowdsecEvidence(),
				'expected_decision'  => PolicyDecision::DECISION_BLOCK_REQUEST,
				'expected_reason'    => 'crowdsec_sensitive_request',
				'expected_risk_band' => PolicyState::BAND_SUSPICIOUS,
			] ),
			'crowdsec adaptive blocks xmlrpc surface'                     => $this->scenario( [
				'profile'            => [ 'method' => 'POST', 'surface' => RequestProfile::SURFACE_XMLRPC ],
				'evidence'           => $this->crowdsecEvidence(),
				'expected_decision'  => PolicyDecision::DECISION_BLOCK_REQUEST,
				'expected_reason'    => 'crowdsec_sensitive_request',
				'expected_risk_band' => PolicyState::BAND_SUSPICIOUS,
			] ),
			'crowdsec adaptive blocks probe surface'                      => $this->scenario( [
				'profile'            => [ 'method' => 'GET', 'surface' => RequestProfile::SURFACE_PROBE ],
				'evidence'           => $this->crowdsecEvidence(),
				'expected_decision'  => PolicyDecision::DECISION_BLOCK_REQUEST,
				'expected_reason'    => 'crowdsec_sensitive_request',
				'expected_risk_band' => PolicyState::BAND_SUSPICIOUS,
			] ),
			'shield manual block always blocks'                           => $this->scenario( [
				'profile'            => [ 'method' => 'GET', 'surface' => RequestProfile::SURFACE_PUBLIC_READ ],
				'evidence'           => $this->shieldEvidence( PolicyEvidence::TYPE_SHIELD_MANUAL_BLOCK ),
				'ip_status'          => 'Shield manual block',
				'expected_decision'  => PolicyDecision::DECISION_BLOCK_REQUEST,
				'expected_reason'    => 'shield_manual_block',
				'expected_risk_band' => PolicyState::BAND_HOSTILE,
			] ),
			'shield auto block allows safe read'                          => $this->scenario( [
				'profile'            => [ 'method' => 'GET', 'surface' => RequestProfile::SURFACE_PUBLIC_READ ],
				'evidence'           => $this->shieldEvidence( PolicyEvidence::TYPE_SHIELD_AUTO_BLOCK ),
				'ip_status'          => 'Shield auto-block',
				'expected_decision'  => PolicyDecision::DECISION_ALLOW,
				'expected_reason'    => 'shield_auto_safe_read',
				'expected_risk_band' => PolicyState::BAND_SUSPICIOUS,
			] ),
			'shield auto block blocks sensitive mutation'                  => $this->scenario( [
				'profile'            => [ 'method' => 'POST', 'surface' => RequestProfile::SURFACE_ADMIN_MUTATION ],
				'evidence'           => $this->shieldEvidence( PolicyEvidence::TYPE_SHIELD_AUTO_BLOCK ),
				'ip_status'          => 'Shield auto-block',
				'expected_decision'  => PolicyDecision::DECISION_BLOCK_REQUEST,
				'expected_reason'    => 'shield_auto_sensitive_request',
				'expected_risk_band' => PolicyState::BAND_SUSPICIOUS,
			] ),
			'shield auto patch api mutation blocks sensitive request'      => $this->scenario( [
				'profile'            => [ 'method' => 'PATCH', 'surface' => RequestProfile::SURFACE_API_MUTATION ],
				'evidence'           => $this->shieldEvidence( PolicyEvidence::TYPE_SHIELD_AUTO_BLOCK ),
				'ip_status'          => 'Shield auto-block',
				'expected_decision'  => PolicyDecision::DECISION_BLOCK_REQUEST,
				'expected_reason'    => 'shield_auto_sensitive_request',
				'expected_risk_band' => PolicyState::BAND_SUSPICIOUS,
			] ),
			'shield auto block blocks hostile read'                        => $this->scenario( [
				'profile'            => [ 'method' => 'GET', 'surface' => RequestProfile::SURFACE_PUBLIC_READ ],
				'evidence'           => $this->shieldEvidence( PolicyEvidence::TYPE_SHIELD_AUTO_BLOCK ),
				'ip_status'          => 'Shield auto-block',
				'counters'           => [
					PolicyEvidence::TYPE_RATE_LIMIT => [ '15m' => 1 ],
				],
				'expected_decision'  => PolicyDecision::DECISION_BLOCK_REQUEST,
				'expected_reason'    => 'shield_auto_hostile_risk',
				'expected_risk_band' => PolicyState::BAND_HOSTILE,
			] ),
			'unknown detector with no evidence allows no decision'         => $this->scenario( [
				'profile'            => [ 'method' => 'GET', 'surface' => RequestProfile::SURFACE_PUBLIC_READ ],
				'evidence'           => [
					'detector' => PolicyEvidence::DETECTOR_EVENT,
					'type'     => PolicyEvidence::TYPE_NONE,
					'severity' => PolicyEvidence::SEVERITY_INFO,
				],
				'ip_status'          => 'none',
				'expected_decision'  => PolicyDecision::DECISION_NO_DECISION,
				'expected_reason'    => 'insufficient_information',
				'expected_risk_band' => PolicyState::BAND_NORMAL,
			] ),
			'username fishing counter makes request hostile'               => $this->scenario( [
				'profile'            => [ 'method' => 'GET', 'surface' => RequestProfile::SURFACE_PUBLIC_READ ],
				'evidence'           => $this->crowdsecEvidence(),
				'counters'           => [
					PolicyEvidence::TYPE_USERNAME_FISHING => [ '24h' => 1 ],
				],
				'expected_decision'  => PolicyDecision::DECISION_BLOCK_REQUEST,
				'expected_reason'    => 'crowdsec_hostile_risk',
				'expected_risk_band' => PolicyState::BAND_HOSTILE,
			] ),
			'invalid username counter makes request suspicious'            => $this->scenario( [
				'profile'            => [ 'method' => 'GET', 'surface' => RequestProfile::SURFACE_PUBLIC_READ ],
				'evidence'           => $this->crowdsecEvidence(),
				'counters'           => [
					PolicyEvidence::TYPE_AUTH_INVALID_USER => [ '24h' => 1 ],
				],
				'expected_decision'  => PolicyDecision::DECISION_ALLOW,
				'expected_reason'    => 'crowdsec_safe_read',
				'expected_risk_band' => PolicyState::BAND_SUSPICIOUS,
			] ),
			'xmlrpc counter can make request hostile'                      => $this->scenario( [
				'profile'            => [ 'method' => 'GET', 'surface' => RequestProfile::SURFACE_PUBLIC_READ ],
				'evidence'           => $this->crowdsecEvidence(),
				'counters'           => [
					PolicyEvidence::TYPE_XMLRPC => [ '15m' => 3 ],
				],
				'expected_decision'  => PolicyDecision::DECISION_BLOCK_REQUEST,
				'expected_reason'    => 'crowdsec_hostile_risk',
				'expected_risk_band' => PolicyState::BAND_HOSTILE,
			] ),
		];

		return \array_map( static fn( array $scenario ) :array => [ $scenario ], $scenarios );
	}

	public function testAllFixedSurfacesRemainRecognised() :void {
		$this->assertSame( [
			RequestProfile::SURFACE_PUBLIC_READ,
			RequestProfile::SURFACE_API_READ,
			RequestProfile::SURFACE_AUTH_ATTEMPT,
			RequestProfile::SURFACE_CONTENT_MUTATION,
			RequestProfile::SURFACE_ADMIN_MUTATION,
			RequestProfile::SURFACE_API_MUTATION,
			RequestProfile::SURFACE_XMLRPC,
			RequestProfile::SURFACE_SHIELD_ACTION,
			RequestProfile::SURFACE_PROBE,
		], [
			'public_read',
			'api_read',
			'auth_attempt',
			'content_mutation',
			'admin_mutation',
			'api_mutation',
			'xmlrpc',
			'shield_action',
			'probe',
		] );
	}

	public function testScenarioManifestCoversRequiredAxes() :void {
		$scenarios = \array_map( static fn( array $row ) :array => $row[ 0 ], $this->provideDecisionScenarios() );

		$this->assertCoverageContains( [ RequestPolicyEvaluator::MODE_LEGACY, RequestPolicyEvaluator::MODE_SHADOW, RequestPolicyEvaluator::MODE_ADAPTIVE ], \array_column( $scenarios, 'mode' ) );
		$this->assertCoverageContains( [ 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ], \array_map( static fn( array $scenario ) :string => $scenario[ 'profile' ][ 'method' ], $scenarios ) );
		$this->assertCoverageContains( [
			RequestProfile::SURFACE_PUBLIC_READ,
			RequestProfile::SURFACE_API_READ,
			RequestProfile::SURFACE_AUTH_ATTEMPT,
			RequestProfile::SURFACE_CONTENT_MUTATION,
			RequestProfile::SURFACE_ADMIN_MUTATION,
			RequestProfile::SURFACE_API_MUTATION,
			RequestProfile::SURFACE_XMLRPC,
			RequestProfile::SURFACE_SHIELD_ACTION,
			RequestProfile::SURFACE_PROBE,
		], \array_map( static fn( array $scenario ) :string => $scenario[ 'profile' ][ 'surface' ], $scenarios ) );
		$this->assertCoverageContains( [ 'anonymous', 'logged-in low trust', 'logged-in trusted', 'trusted service' ], \array_column( $scenarios, 'actor_key' ) );
		$this->assertCoverageContains( [ 'none', 'CrowdSec', 'Shield auto-block', 'Shield manual block' ], \array_column( $scenarios, 'ip_status' ) );
		$this->assertCoverageContains( [
			PolicyEvidence::TYPE_NONE,
			PolicyEvidence::TYPE_AUTH_FAILURE,
			PolicyEvidence::TYPE_AUTH_INVALID_USER,
			PolicyEvidence::TYPE_XMLRPC,
			PolicyEvidence::TYPE_USERNAME_FISHING,
			PolicyEvidence::TYPE_RATE_LIMIT,
			PolicyEvidence::TYPE_FIREWALL_NOISY,
			PolicyEvidence::TYPE_FIREWALL_CRITICAL,
		], $this->coveredEvidenceTypes( $scenarios ) );
	}

	public function testDecisionTraceIncludesCountersUsedByEvaluator() :void {
		$decision = ( new RequestPolicyEvaluator() )->evaluate(
			RequestPolicyEvaluator::MODE_ADAPTIVE,
			new RequestProfile( [ 'method' => 'POST', 'surface' => RequestProfile::SURFACE_AUTH_ATTEMPT ] ),
			new ActorTrust( [ 'is_logged_in' => false ] ),
			new PolicyState( [
				'meta' => $this->stateMetaFromCounters( [
					PolicyEvidence::TYPE_AUTH_INVALID_USER => [ '15m' => 1 ],
				] ),
			] ),
			new PolicyEvidence( $this->crowdsecEvidence() )
		);

		$this->assertSame( 1, $decision->evidence_counters_used[ 'auth_invalid_user_15m' ] ?? null );
	}

	public function test_invalid_policy_mode_normalizes_to_legacy_decision() :void {
		$decision = ( new RequestPolicyEvaluator() )->evaluate(
			'not-a-mode',
			new RequestProfile( [ 'method' => 'GET', 'surface' => RequestProfile::SURFACE_PUBLIC_READ ] ),
			new ActorTrust( [] ),
			new PolicyState(),
			new PolicyEvidence( [
				'detector' => PolicyEvidence::DETECTOR_CROWDSEC,
				'type'     => PolicyEvidence::TYPE_CROWDSEC,
				'severity' => PolicyEvidence::SEVERITY_SIGNAL,
			] )
		);

		$this->assertSame( RequestPolicyEvaluator::MODE_LEGACY, $decision->mode );
		$this->assertSame( PolicyDecision::DECISION_LEGACY, $decision->decision );
		$this->assertSame( 'legacy_mode', $decision->reason );
	}

	private function scenario( array $override ) :array {
		return \array_replace_recursive( [
			'mode'               => RequestPolicyEvaluator::MODE_ADAPTIVE,
			'profile'            => [ 'method' => 'GET', 'surface' => RequestProfile::SURFACE_PUBLIC_READ ],
			'actor'              => [ 'is_logged_in' => false ],
			'actor_key'          => 'anonymous',
			'ip_status'          => 'CrowdSec',
			'evidence'           => $this->crowdsecEvidence(),
			'counters'           => [],
			'risk_band'          => PolicyState::BAND_NORMAL,
			'expected_decision'  => PolicyDecision::DECISION_ALLOW,
			'expected_reason'    => 'crowdsec_safe_read',
			'expected_risk_band' => PolicyState::BAND_NORMAL,
		], $override );
	}

	private function assertCoverageContains( array $expected, array $actual ) :void {
		foreach ( $expected as $value ) {
			$this->assertContains( $value, $actual );
		}
	}

	private function coveredEvidenceTypes( array $scenarios ) :array {
		$types = [];
		foreach ( $scenarios as $scenario ) {
			$types[] = $scenario[ 'evidence' ][ 'type' ];
			foreach ( \array_keys( $scenario[ 'counters' ] ?? [] ) as $counterType ) {
				$types[] = $counterType;
			}
		}
		return \array_unique( $types );
	}

	private function crowdsecEvidence() :array {
		return [
			'detector' => PolicyEvidence::DETECTOR_CROWDSEC,
			'type'     => PolicyEvidence::TYPE_CROWDSEC,
			'severity' => PolicyEvidence::SEVERITY_SIGNAL,
		];
	}

	private function shieldEvidence( string $type ) :array {
		return [
			'detector' => PolicyEvidence::DETECTOR_SHIELD_IP,
			'type'     => $type,
			'severity' => $type === PolicyEvidence::TYPE_SHIELD_MANUAL_BLOCK
				? PolicyEvidence::SEVERITY_CRITICAL
				: PolicyEvidence::SEVERITY_SIGNAL,
		];
	}

	private function stateMetaFromCounters( array $counters ) :array {
		$meta = [];
		foreach ( $counters as $type => $windows ) {
			foreach ( $windows as $window => $count ) {
				$meta[ 'evidence' ][ $type ][ $window ] = [
					'started_at' => 1,
					'count'      => $count,
				];
			}
		}
		return $meta;
	}
}
