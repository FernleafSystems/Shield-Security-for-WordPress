<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons\RequestPolicy;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\RequestPolicy\{
	ActorTrust,
	PolicyDecision,
	PolicyEvidence,
	PolicyRiskThresholds,
	PolicyState,
	RequestPolicyEvaluator,
	RequestProfile
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class RequestPolicyEvaluatorTest extends BaseUnitTest {

	#[\PHPUnit\Framework\Attributes\DataProvider( 'provideDecisionScenarios' )]
	public function testDeterministicPolicyDecisions( array $scenario ) :void {
		$decision = ( new RequestPolicyEvaluator() )->evaluate(
			$scenario[ 'mode' ],
			$scenario[ 'sensitivity' ],
			new RequestProfile( $scenario[ 'profile' ] ),
			new ActorTrust( $scenario[ 'actor' ] ),
			new PolicyState( [
				'risk_band' => $scenario[ 'risk_band' ] ?? PolicyState::BAND_NORMAL,
				'meta'      => $this->stateMetaFromCounters( $scenario[ 'counters' ] ?? [] ),
			] ),
			new PolicyEvidence( $scenario[ 'evidence' ] )
		);

		$this->assertSame( $scenario[ 'expected_mode' ] ?? $scenario[ 'mode' ], $decision->mode );
		$this->assertSame( $scenario[ 'expected_sensitivity' ] ?? $scenario[ 'sensitivity' ], $decision->sensitivity );
		$this->assertSame( $scenario[ 'expected_decision' ], $decision->decision );
		$this->assertSame( $scenario[ 'expected_reason' ], $decision->reason );
		$this->assertSame( $scenario[ 'expected_risk_band' ], $decision->risk_band );
		$this->assertSame( $scenario[ 'expected_risk_reason' ], $decision->risk_reason );
		$this->assertSame( $scenario[ 'expected_block_category' ] ?? '', $decision->block_category );
		$this->assertSame( $scenario[ 'evidence' ][ 'detector' ], $decision->detector );
		$this->assertSame( $scenario[ 'profile' ][ 'surface' ], $decision->surface );

		foreach ( $scenario[ 'expected_counters' ] ?? [] as $key => $value ) {
			$this->assertSame( $value, $decision->evidence_counters_used[ $key ] ?? null, $key );
		}
	}

	public static function provideDecisionScenarios() :array {
		$scenarios = [
			'legacy firewall keeps current enforcement and no sensitivity trace' => self::scenario( [
				'mode'                    => RequestPolicyEvaluator::MODE_LEGACY,
				'sensitivity'             => PolicyRiskThresholds::SENSITIVITY_AGGRESSIVE,
				'evidence'                => self::firewallEvidence(),
				'ip_status'               => 'none',
				'expected_mode'           => RequestPolicyEvaluator::MODE_LEGACY,
				'expected_sensitivity'    => '',
				'expected_decision'       => PolicyDecision::DECISION_LEGACY,
				'expected_reason'         => 'legacy_mode',
				'expected_risk_band'      => PolicyState::BAND_NORMAL,
				'expected_risk_reason'    => 'legacy_mode',
				'expected_block_category' => '',
			] ),
			'invalid mode normalizes to legacy'                         => self::scenario( [
				'mode'                    => 'not-a-mode',
				'sensitivity'             => PolicyRiskThresholds::SENSITIVITY_AGGRESSIVE,
				'expected_mode'           => RequestPolicyEvaluator::MODE_LEGACY,
				'expected_sensitivity'    => '',
				'expected_decision'       => PolicyDecision::DECISION_LEGACY,
				'expected_reason'         => 'legacy_mode',
				'expected_risk_band'      => PolicyState::BAND_NORMAL,
				'expected_risk_reason'    => 'legacy_mode',
				'expected_block_category' => '',
			] ),
			'invalid sensitivity normalizes to balanced'                 => self::scenario( [
				'sensitivity'          => 'not-a-sensitivity',
				'expected_sensitivity' => PolicyRiskThresholds::SENSITIVITY_BALANCED,
			] ),
			'shadow computes adaptive firewall result without owning enforcement' => self::scenario( [
				'mode'              => RequestPolicyEvaluator::MODE_SHADOW,
				'profile'           => [ 'method' => 'POST', 'surface' => RequestProfile::SURFACE_CONTENT_MUTATION ],
				'actor'             => [ 'is_logged_in' => true, 'is_security_admin' => true ],
				'actor_key'         => 'logged-in trusted',
				'ip_status'         => 'none',
				'evidence'          => self::firewallEvidence(),
				'expected_decision' => PolicyDecision::DECISION_LOG_ONLY,
				'expected_reason'   => 'firewall_trusted_mutation_log_only',
				'expected_risk_band' => PolicyState::BAND_NORMAL,
				'expected_risk_reason' => 'none',
			] ),
			'trusted logged-in mutation with noisy firewall is log-only' => self::scenario( [
				'profile'           => [ 'method' => 'POST', 'surface' => RequestProfile::SURFACE_CONTENT_MUTATION ],
				'actor'             => [ 'is_logged_in' => true, 'is_high_reputation_ip' => true ],
				'actor_key'         => 'logged-in trusted',
				'ip_status'         => 'none',
				'evidence'          => self::firewallEvidence(),
				'expected_decision' => PolicyDecision::DECISION_LOG_ONLY,
				'expected_reason'   => 'firewall_trusted_mutation_log_only',
				'expected_risk_band' => PolicyState::BAND_NORMAL,
				'expected_risk_reason' => 'none',
			] ),
			'anonymous noisy firewall blocks'                           => self::scenario( [
				'profile'                 => [ 'method' => 'POST', 'surface' => RequestProfile::SURFACE_CONTENT_MUTATION ],
				'ip_status'               => 'none',
				'evidence'                => self::firewallEvidence(),
				'expected_decision'       => PolicyDecision::DECISION_BLOCK_REQUEST,
				'expected_reason'         => 'firewall_untrusted_actor',
				'expected_risk_band'      => PolicyState::BAND_NORMAL,
				'expected_risk_reason'    => 'none',
				'expected_block_category' => PolicyDecision::BLOCK_CATEGORY_UNTRUSTED_ACTOR,
			] ),
			'logged-in low trust noisy firewall still blocks'            => self::scenario( [
				'profile'                 => [ 'method' => 'POST', 'surface' => RequestProfile::SURFACE_CONTENT_MUTATION ],
				'actor'                   => [ 'is_logged_in' => true ],
				'actor_key'               => 'logged-in low trust',
				'ip_status'               => 'none',
				'evidence'                => self::firewallEvidence(),
				'expected_decision'       => PolicyDecision::DECISION_BLOCK_REQUEST,
				'expected_reason'         => 'firewall_untrusted_actor',
				'expected_risk_band'      => PolicyState::BAND_NORMAL,
				'expected_risk_reason'    => 'none',
				'expected_block_category' => PolicyDecision::BLOCK_CATEGORY_UNTRUSTED_ACTOR,
			] ),
			'trusted service api mutation with noisy firewall is log-only' => self::scenario( [
				'profile'           => [ 'method' => 'PUT', 'surface' => RequestProfile::SURFACE_API_MUTATION ],
				'actor'             => [ 'is_trusted_service' => true ],
				'actor_key'         => 'trusted service',
				'ip_status'         => 'none',
				'evidence'          => self::firewallEvidence(),
				'expected_decision' => PolicyDecision::DECISION_LOG_ONLY,
				'expected_reason'   => 'firewall_trusted_mutation_log_only',
				'expected_risk_band' => PolicyState::BAND_NORMAL,
				'expected_risk_reason' => 'none',
			] ),
			'critical firewall always blocks trusted actor'              => self::scenario( [
				'profile'                 => [ 'method' => 'POST', 'surface' => RequestProfile::SURFACE_CONTENT_MUTATION ],
				'actor'                   => [ 'is_logged_in' => true, 'is_security_admin' => true ],
				'evidence'                => self::firewallEvidence( 'php_code', PolicyEvidence::SEVERITY_CRITICAL ),
				'actor_key'               => 'logged-in trusted',
				'ip_status'               => 'none',
				'expected_decision'       => PolicyDecision::DECISION_BLOCK_REQUEST,
				'expected_reason'         => 'firewall_critical',
				'expected_risk_band'      => PolicyState::BAND_HOSTILE,
				'expected_risk_reason'    => 'critical_firewall',
				'expected_block_category' => PolicyDecision::BLOCK_CATEGORY_CRITICAL_FIREWALL,
			] ),
			'crowdsec adaptive allows public read'                       => self::scenario( [
				'profile'              => [ 'method' => 'GET', 'surface' => RequestProfile::SURFACE_PUBLIC_READ ],
				'evidence'             => self::crowdsecEvidence(),
				'expected_decision'    => PolicyDecision::DECISION_ALLOW,
				'expected_reason'      => 'crowdsec_safe_read',
				'expected_risk_band'   => PolicyState::BAND_SUSPICIOUS,
				'expected_risk_reason' => 'crowdsec_signal',
			] ),
			'crowdsec adaptive allows api read'                          => self::scenario( [
				'profile'              => [ 'method' => 'GET', 'surface' => RequestProfile::SURFACE_API_READ ],
				'evidence'             => self::crowdsecEvidence(),
				'expected_decision'    => PolicyDecision::DECISION_ALLOW,
				'expected_reason'      => 'crowdsec_safe_read',
				'expected_risk_band'   => PolicyState::BAND_SUSPICIOUS,
				'expected_risk_reason' => 'crowdsec_signal',
			] ),
			'crowdsec adaptive allows clean auth attempt'                 => self::scenario( [
				'profile'              => [ 'method' => 'POST', 'surface' => RequestProfile::SURFACE_AUTH_ATTEMPT ],
				'evidence'             => self::crowdsecEvidence(),
				'expected_decision'    => PolicyDecision::DECISION_ALLOW,
				'expected_reason'      => 'crowdsec_clean_auth_attempt',
				'expected_risk_band'   => PolicyState::BAND_SUSPICIOUS,
				'expected_risk_reason' => 'crowdsec_signal',
			] ),
			'crowdsec adaptive blocks auth attempt with recent auth abuse' => self::scenario( [
				'profile'                 => [ 'method' => 'POST', 'surface' => RequestProfile::SURFACE_AUTH_ATTEMPT ],
				'evidence'                => self::crowdsecEvidence(),
				'counters'                => [
					PolicyEvidence::TYPE_AUTH_ABUSE => [ '15m' => 1 ],
				],
				'expected_decision'       => PolicyDecision::DECISION_BLOCK_REQUEST,
				'expected_reason'         => 'crowdsec_sensitive_request',
				'expected_risk_band'      => PolicyState::BAND_SUSPICIOUS,
				'expected_risk_reason'    => 'threshold_auth_abuse',
				'expected_block_category' => PolicyDecision::BLOCK_CATEGORY_REPEATED_SUSPICIOUS_ACTIVITY,
				'expected_counters'       => [ 'auth_abuse_15m' => 1 ],
			] ),
			'crowdsec adaptive blocks hostile IP'                         => self::scenario( [
				'profile'                 => [ 'method' => 'GET', 'surface' => RequestProfile::SURFACE_PUBLIC_READ ],
				'evidence'                => self::crowdsecEvidence(),
				'counters'                => [
					PolicyEvidence::TYPE_AUTH_ABUSE => [ '15m' => 3 ],
				],
				'expected_decision'       => PolicyDecision::DECISION_BLOCK_REQUEST,
				'expected_reason'         => 'crowdsec_hostile_risk',
				'expected_risk_band'      => PolicyState::BAND_HOSTILE,
				'expected_risk_reason'    => 'threshold_auth_abuse',
				'expected_block_category' => PolicyDecision::BLOCK_CATEGORY_REPEATED_SUSPICIOUS_ACTIVITY,
			] ),
			'crowdsec adaptive blocks mutation surfaces'                  => self::scenario( [
				'profile'                 => [ 'method' => 'POST', 'surface' => RequestProfile::SURFACE_CONTENT_MUTATION ],
				'evidence'                => self::crowdsecEvidence(),
				'expected_decision'       => PolicyDecision::DECISION_BLOCK_REQUEST,
				'expected_reason'         => 'crowdsec_sensitive_request',
				'expected_risk_band'      => PolicyState::BAND_SUSPICIOUS,
				'expected_risk_reason'    => 'crowdsec_signal',
				'expected_block_category' => PolicyDecision::BLOCK_CATEGORY_SENSITIVE_REQUEST,
			] ),
			'crowdsec adaptive blocks xmlrpc surface'                     => self::scenario( [
				'profile'                 => [ 'method' => 'POST', 'surface' => RequestProfile::SURFACE_XMLRPC ],
				'evidence'                => self::crowdsecEvidence(),
				'expected_decision'       => PolicyDecision::DECISION_BLOCK_REQUEST,
				'expected_reason'         => 'crowdsec_sensitive_request',
				'expected_risk_band'      => PolicyState::BAND_SUSPICIOUS,
				'expected_risk_reason'    => 'crowdsec_signal',
				'expected_block_category' => PolicyDecision::BLOCK_CATEGORY_SENSITIVE_REQUEST,
			] ),
			'crowdsec adaptive blocks shield action surface'              => self::scenario( [
				'profile'                 => [ 'method' => 'DELETE', 'surface' => RequestProfile::SURFACE_SHIELD_ACTION ],
				'evidence'                => self::crowdsecEvidence(),
				'expected_decision'       => PolicyDecision::DECISION_BLOCK_REQUEST,
				'expected_reason'         => 'crowdsec_sensitive_request',
				'expected_risk_band'      => PolicyState::BAND_SUSPICIOUS,
				'expected_risk_reason'    => 'crowdsec_signal',
				'expected_block_category' => PolicyDecision::BLOCK_CATEGORY_SENSITIVE_REQUEST,
			] ),
			'crowdsec adaptive blocks api mutation surface'               => self::scenario( [
				'profile'                 => [ 'method' => 'PATCH', 'surface' => RequestProfile::SURFACE_API_MUTATION ],
				'evidence'                => self::crowdsecEvidence(),
				'expected_decision'       => PolicyDecision::DECISION_BLOCK_REQUEST,
				'expected_reason'         => 'crowdsec_sensitive_request',
				'expected_risk_band'      => PolicyState::BAND_SUSPICIOUS,
				'expected_risk_reason'    => 'crowdsec_signal',
				'expected_block_category' => PolicyDecision::BLOCK_CATEGORY_SENSITIVE_REQUEST,
			] ),
			'crowdsec adaptive blocks probe surface'                      => self::scenario( [
				'profile'                 => [ 'method' => 'GET', 'surface' => RequestProfile::SURFACE_PROBE ],
				'evidence'                => self::crowdsecEvidence(),
				'expected_decision'       => PolicyDecision::DECISION_BLOCK_REQUEST,
				'expected_reason'         => 'crowdsec_sensitive_request',
				'expected_risk_band'      => PolicyState::BAND_SUSPICIOUS,
				'expected_risk_reason'    => 'crowdsec_signal',
				'expected_block_category' => PolicyDecision::BLOCK_CATEGORY_SENSITIVE_REQUEST,
			] ),
			'shield manual block always blocks'                           => self::scenario( [
				'profile'                 => [ 'method' => 'GET', 'surface' => RequestProfile::SURFACE_PUBLIC_READ ],
				'evidence'                => self::shieldEvidence( PolicyEvidence::TYPE_SHIELD_MANUAL_BLOCK ),
				'ip_status'               => 'Shield manual block',
				'expected_decision'       => PolicyDecision::DECISION_BLOCK_REQUEST,
				'expected_reason'         => 'shield_manual_block',
				'expected_risk_band'      => PolicyState::BAND_HOSTILE,
				'expected_risk_reason'    => 'manual_ip_block',
				'expected_block_category' => PolicyDecision::BLOCK_CATEGORY_MANUAL_IP_BLOCK,
			] ),
			'shield auto block allows safe read in balanced'              => self::scenario( [
				'profile'              => [ 'method' => 'GET', 'surface' => RequestProfile::SURFACE_PUBLIC_READ ],
				'evidence'             => self::shieldEvidence( PolicyEvidence::TYPE_SHIELD_AUTO_BLOCK ),
				'ip_status'            => 'Shield auto-block',
				'expected_decision'    => PolicyDecision::DECISION_ALLOW,
				'expected_reason'      => 'shield_auto_safe_read',
				'expected_risk_band'   => PolicyState::BAND_SUSPICIOUS,
				'expected_risk_reason' => 'shield_auto_block',
			] ),
			'shield auto block blocks safe read in aggressive'            => self::scenario( [
				'sensitivity'             => PolicyRiskThresholds::SENSITIVITY_AGGRESSIVE,
				'profile'                 => [ 'method' => 'GET', 'surface' => RequestProfile::SURFACE_PUBLIC_READ ],
				'evidence'                => self::shieldEvidence( PolicyEvidence::TYPE_SHIELD_AUTO_BLOCK ),
				'ip_status'               => 'Shield auto-block',
				'expected_decision'       => PolicyDecision::DECISION_BLOCK_REQUEST,
				'expected_reason'         => 'shield_auto_hostile_risk',
				'expected_risk_band'      => PolicyState::BAND_HOSTILE,
				'expected_risk_reason'    => 'shield_auto_block_aggressive',
				'expected_block_category' => PolicyDecision::BLOCK_CATEGORY_HOSTILE_IP,
			] ),
			'shield auto block blocks sensitive mutation'                 => self::scenario( [
				'profile'                 => [ 'method' => 'POST', 'surface' => RequestProfile::SURFACE_ADMIN_MUTATION ],
				'evidence'                => self::shieldEvidence( PolicyEvidence::TYPE_SHIELD_AUTO_BLOCK ),
				'ip_status'               => 'Shield auto-block',
				'expected_decision'       => PolicyDecision::DECISION_BLOCK_REQUEST,
				'expected_reason'         => 'shield_auto_sensitive_request',
				'expected_risk_band'      => PolicyState::BAND_SUSPICIOUS,
				'expected_risk_reason'    => 'shield_auto_block',
				'expected_block_category' => PolicyDecision::BLOCK_CATEGORY_SENSITIVE_REQUEST,
			] ),
			'unknown detector with no evidence allows no decision'        => self::scenario( [
				'profile'              => [ 'method' => 'GET', 'surface' => RequestProfile::SURFACE_PUBLIC_READ ],
				'evidence'             => [
					'detector' => PolicyEvidence::DETECTOR_EVENT,
					'type'     => PolicyEvidence::TYPE_NONE,
					'severity' => PolicyEvidence::SEVERITY_INFO,
				],
				'ip_status'            => 'none',
				'expected_decision'    => PolicyDecision::DECISION_NO_DECISION,
				'expected_reason'      => 'insufficient_information',
				'expected_risk_band'   => PolicyState::BAND_NORMAL,
				'expected_risk_reason' => 'none',
			] ),
		];

		foreach ( PolicyRiskThresholds::CATEGORY_WINDOWS as $category => $window ) {
			foreach ( self::sensitivityThresholds()[ $category ] as $sensitivity => $thresholds ) {
				$below = \max( 0, $thresholds[ 'suspicious' ] - 1 );
				$scenarios[ "{$category} {$sensitivity} below suspicious" ] = self::thresholdScenario(
					$category,
					$window,
					$sensitivity,
					$below,
					PolicyState::BAND_SUSPICIOUS,
					PolicyState::BAND_SUSPICIOUS,
					'crowdsec_signal',
					$below
				);
				if ( $thresholds[ 'suspicious' ] < $thresholds[ 'hostile' ] ) {
					$scenarios[ "{$category} {$sensitivity} exactly suspicious" ] = self::thresholdScenario(
						$category,
						$window,
						$sensitivity,
						$thresholds[ 'suspicious' ],
						PolicyState::BAND_SUSPICIOUS,
						PolicyState::BAND_SUSPICIOUS,
						'threshold_'.$category,
						$thresholds[ 'suspicious' ]
					);
				}
				$scenarios[ "{$category} {$sensitivity} exactly hostile" ] = self::thresholdScenario(
					$category,
					$window,
					$sensitivity,
					$thresholds[ 'hostile' ],
					PolicyState::BAND_HOSTILE,
					PolicyState::BAND_HOSTILE,
					'threshold_'.$category,
					$thresholds[ 'hostile' ]
				);
			}
		}

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
		$scenarios = \array_map( static fn( array $row ) :array => $row[ 0 ], self::provideDecisionScenarios() );

		$this->assertCoverageContains( [
			RequestPolicyEvaluator::MODE_LEGACY,
			RequestPolicyEvaluator::MODE_SHADOW,
			RequestPolicyEvaluator::MODE_ADAPTIVE,
		], \array_column( $scenarios, 'mode' ) );
		$this->assertCoverageContains( [
			PolicyRiskThresholds::SENSITIVITY_LENIENT,
			PolicyRiskThresholds::SENSITIVITY_BALANCED,
			PolicyRiskThresholds::SENSITIVITY_AGGRESSIVE,
		], \array_column( $scenarios, 'sensitivity' ) );
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
			PolicyEvidence::TYPE_AUTH_ABUSE,
			PolicyEvidence::TYPE_PROBE_ABUSE,
			PolicyEvidence::TYPE_RATE_ABUSE,
			PolicyEvidence::TYPE_FIREWALL_ABUSE,
			PolicyEvidence::TYPE_CONTENT_ABUSE,
			PolicyEvidence::TYPE_IP_ENFORCEMENT,
		], $this->coveredEvidenceTypes( $scenarios ) );
	}

	private static function thresholdScenario(
		string $category,
		string $window,
		string $sensitivity,
		int $count,
		string $expectedDecisionRiskBand,
		string $expectedRiskBand,
		string $expectedRiskReason,
		int $expectedCounter
	) :array {
		$hostile = $expectedDecisionRiskBand === PolicyState::BAND_HOSTILE;
		return self::scenario( [
			'sensitivity'             => $sensitivity,
			'evidence'                => self::crowdsecEvidence(),
			'counters'                => $count > 0 ? [
				$category => [ $window => $count ],
			] : [],
			'expected_decision'       => $hostile ? PolicyDecision::DECISION_BLOCK_REQUEST : PolicyDecision::DECISION_ALLOW,
			'expected_reason'         => $hostile ? 'crowdsec_hostile_risk' : 'crowdsec_safe_read',
			'expected_risk_band'      => $expectedRiskBand,
			'expected_risk_reason'    => $expectedRiskReason,
			'expected_block_category' => $hostile ? PolicyDecision::BLOCK_CATEGORY_REPEATED_SUSPICIOUS_ACTIVITY : '',
			'expected_counters'       => [ $category.'_'.$window => $expectedCounter ],
		] );
	}

	private static function scenario( array $override ) :array {
		return \array_replace_recursive( [
			'mode'                    => RequestPolicyEvaluator::MODE_ADAPTIVE,
			'sensitivity'             => PolicyRiskThresholds::SENSITIVITY_BALANCED,
			'profile'                 => [ 'method' => 'GET', 'surface' => RequestProfile::SURFACE_PUBLIC_READ ],
			'actor'                   => [ 'is_logged_in' => false ],
			'actor_key'               => 'anonymous',
			'ip_status'               => 'CrowdSec',
			'evidence'                => self::crowdsecEvidence(),
			'counters'                => [],
			'risk_band'               => PolicyState::BAND_NORMAL,
			'expected_decision'       => PolicyDecision::DECISION_ALLOW,
			'expected_reason'         => 'crowdsec_safe_read',
			'expected_risk_band'      => PolicyState::BAND_SUSPICIOUS,
			'expected_risk_reason'    => 'crowdsec_signal',
			'expected_block_category' => '',
		], $override );
	}

	private static function sensitivityThresholds() :array {
		return [
			PolicyEvidence::TYPE_AUTH_ABUSE     => [
				PolicyRiskThresholds::SENSITIVITY_LENIENT    => [ 'suspicious' => 2, 'hostile' => 5 ],
				PolicyRiskThresholds::SENSITIVITY_BALANCED   => [ 'suspicious' => 1, 'hostile' => 3 ],
				PolicyRiskThresholds::SENSITIVITY_AGGRESSIVE => [ 'suspicious' => 1, 'hostile' => 2 ],
			],
			PolicyEvidence::TYPE_PROBE_ABUSE    => [
				PolicyRiskThresholds::SENSITIVITY_LENIENT    => [ 'suspicious' => 4, 'hostile' => 8 ],
				PolicyRiskThresholds::SENSITIVITY_BALANCED   => [ 'suspicious' => 2, 'hostile' => 4 ],
				PolicyRiskThresholds::SENSITIVITY_AGGRESSIVE => [ 'suspicious' => 1, 'hostile' => 2 ],
			],
			PolicyEvidence::TYPE_RATE_ABUSE     => [
				PolicyRiskThresholds::SENSITIVITY_LENIENT    => [ 'suspicious' => 2, 'hostile' => 4 ],
				PolicyRiskThresholds::SENSITIVITY_BALANCED   => [ 'suspicious' => 1, 'hostile' => 2 ],
				PolicyRiskThresholds::SENSITIVITY_AGGRESSIVE => [ 'suspicious' => 1, 'hostile' => 1 ],
			],
			PolicyEvidence::TYPE_FIREWALL_ABUSE => [
				PolicyRiskThresholds::SENSITIVITY_LENIENT    => [ 'suspicious' => 2, 'hostile' => 5 ],
				PolicyRiskThresholds::SENSITIVITY_BALANCED   => [ 'suspicious' => 1, 'hostile' => 2 ],
				PolicyRiskThresholds::SENSITIVITY_AGGRESSIVE => [ 'suspicious' => 1, 'hostile' => 1 ],
			],
			PolicyEvidence::TYPE_CONTENT_ABUSE  => [
				PolicyRiskThresholds::SENSITIVITY_LENIENT    => [ 'suspicious' => 2, 'hostile' => 5 ],
				PolicyRiskThresholds::SENSITIVITY_BALANCED   => [ 'suspicious' => 1, 'hostile' => 3 ],
				PolicyRiskThresholds::SENSITIVITY_AGGRESSIVE => [ 'suspicious' => 1, 'hostile' => 2 ],
			],
			PolicyEvidence::TYPE_IP_ENFORCEMENT => [
				PolicyRiskThresholds::SENSITIVITY_LENIENT    => [ 'suspicious' => 4, 'hostile' => 8 ],
				PolicyRiskThresholds::SENSITIVITY_BALANCED   => [ 'suspicious' => 2, 'hostile' => 4 ],
				PolicyRiskThresholds::SENSITIVITY_AGGRESSIVE => [ 'suspicious' => 1, 'hostile' => 1 ],
			],
		];
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

	private static function crowdsecEvidence() :array {
		return [
			'detector' => PolicyEvidence::DETECTOR_CROWDSEC,
			'type'     => PolicyEvidence::TYPE_CROWDSEC,
			'severity' => PolicyEvidence::SEVERITY_SIGNAL,
		];
	}

	private static function shieldEvidence( string $type ) :array {
		return [
			'detector' => PolicyEvidence::DETECTOR_SHIELD_IP,
			'type'     => $type,
			'severity' => $type === PolicyEvidence::TYPE_SHIELD_MANUAL_BLOCK
				? PolicyEvidence::SEVERITY_CRITICAL
				: PolicyEvidence::SEVERITY_SIGNAL,
		];
	}

	private static function firewallEvidence( string $category = 'sql_queries', string $severity = PolicyEvidence::SEVERITY_NOISY ) :array {
		return [
			'detector'       => PolicyEvidence::DETECTOR_FIREWALL,
			'type'           => PolicyEvidence::TYPE_FIREWALL_ABUSE,
			'severity'       => $severity,
			'rule_slug'      => 'shield/firewall',
			'condition_meta' => [
				'match_category' => $category,
			],
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
