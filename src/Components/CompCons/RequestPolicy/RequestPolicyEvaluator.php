<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\RequestPolicy;

class RequestPolicyEvaluator {

	public const MODE_LEGACY = 'legacy';
	public const MODE_SHADOW = 'shadow';
	public const MODE_ADAPTIVE = 'adaptive';

	public const CRITICAL_FIREWALL_CATEGORIES = [
		'php_code',
		'dir_traversal',
		'aggressive',
	];

	private const NOISY_FIREWALL_CATEGORIES = [
		'sql_queries',
		'field_truncation',
	];

	public function evaluate(
		string $mode,
		string $sensitivity,
		RequestProfile $profile,
		ActorTrust $actorTrust,
		PolicyState $state,
		PolicyEvidence $evidence
	) :PolicyDecision {
		$mode = self::normaliseMode( $mode );
		if ( $mode === self::MODE_LEGACY ) {
			return $this->decision(
				$mode,
				'',
				$evidence,
				$profile,
				$actorTrust,
				PolicyState::BAND_NORMAL,
				'legacy_mode',
				PolicyDecision::DECISION_LEGACY,
				'legacy_mode',
				[]
			);
		}

		$sensitivity = PolicyRiskThresholds::normaliseSensitivity( $sensitivity );
		$risk = $this->riskBand( $state, $evidence, $sensitivity );

		switch ( $evidence->detector ) {
			case PolicyEvidence::DETECTOR_FIREWALL:
				[ $decision, $reason ] = $this->firewallDecision( $profile, $actorTrust, $risk[ 'band' ], $evidence );
				break;

			case PolicyEvidence::DETECTOR_CROWDSEC:
				[ $decision, $reason ] = $this->crowdsecDecision( $profile, $risk[ 'band' ], $state );
				break;

			case PolicyEvidence::DETECTOR_SHIELD_IP:
				[ $decision, $reason ] = $this->shieldIpDecision( $profile, $risk[ 'band' ], $evidence );
				break;

			default:
				$decision = PolicyDecision::DECISION_NO_DECISION;
				$reason = 'insufficient_information';
				break;
		}

		return $this->decision(
			$mode,
			$sensitivity,
			$evidence,
			$profile,
			$actorTrust,
			$risk[ 'band' ],
			$risk[ 'reason' ],
			$decision,
			$reason,
			$risk[ 'counters' ]
		);
	}

	private function firewallDecision(
		RequestProfile $profile,
		ActorTrust $actorTrust,
		string $riskBand,
		PolicyEvidence $evidence
	) :array {
		$category = (string)( $evidence->condition_meta[ 'match_category' ] ?? $evidence->condition_meta[ 'scan' ] ?? '' );

		if ( $evidence->isFirewallCritical() || \in_array( $category, self::CRITICAL_FIREWALL_CATEGORIES, true ) ) {
			return [ PolicyDecision::DECISION_BLOCK_REQUEST, 'firewall_critical' ];
		}

		if ( !$actorTrust->is_trusted_authenticated && !$actorTrust->is_trusted_service ) {
			return [ PolicyDecision::DECISION_BLOCK_REQUEST, 'firewall_untrusted_actor' ];
		}

		if ( $profile->isMutationSurface()
			 && $riskBand !== PolicyState::BAND_HOSTILE
			 && \in_array( $category, self::NOISY_FIREWALL_CATEGORIES, true ) ) {
			return [ PolicyDecision::DECISION_LOG_ONLY, 'firewall_trusted_mutation_log_only' ];
		}

		return [ PolicyDecision::DECISION_BLOCK_REQUEST, 'firewall_default_block' ];
	}

	private function crowdsecDecision(
		RequestProfile $profile,
		string $riskBand,
		PolicyState $state
	) :array {
		if ( $riskBand === PolicyState::BAND_HOSTILE ) {
			return [ PolicyDecision::DECISION_BLOCK_REQUEST, 'crowdsec_hostile_risk' ];
		}

		if ( $profile->isReadSurface() ) {
			return [ PolicyDecision::DECISION_ALLOW, 'crowdsec_safe_read' ];
		}

		if ( $profile->surface === RequestProfile::SURFACE_AUTH_ATTEMPT
			 && $state->counter( PolicyEvidence::TYPE_AUTH_ABUSE, '15m' ) === 0 ) {
			return [ PolicyDecision::DECISION_ALLOW, 'crowdsec_clean_auth_attempt' ];
		}

		return [ PolicyDecision::DECISION_BLOCK_REQUEST, 'crowdsec_sensitive_request' ];
	}

	private function shieldIpDecision( RequestProfile $profile, string $riskBand, PolicyEvidence $evidence ) :array {
		if ( $evidence->type === PolicyEvidence::TYPE_SHIELD_MANUAL_BLOCK ) {
			return [ PolicyDecision::DECISION_BLOCK_REQUEST, 'shield_manual_block' ];
		}

		if ( $riskBand === PolicyState::BAND_HOSTILE ) {
			return [ PolicyDecision::DECISION_BLOCK_REQUEST, 'shield_auto_hostile_risk' ];
		}

		if ( $profile->isReadSurface() ) {
			return [ PolicyDecision::DECISION_ALLOW, 'shield_auto_safe_read' ];
		}

		return [ PolicyDecision::DECISION_BLOCK_REQUEST, 'shield_auto_sensitive_request' ];
	}

	private function riskBand( PolicyState $state, PolicyEvidence $evidence, string $sensitivity ) :array {
		$counters = $this->countersUsed( $state );

		if ( $evidence->type === PolicyEvidence::TYPE_SHIELD_MANUAL_BLOCK ) {
			return $this->risk( PolicyState::BAND_HOSTILE, 'manual_ip_block', $counters );
		}

		if ( $state->risk_band === PolicyState::BAND_HOSTILE ) {
			return $this->risk( PolicyState::BAND_HOSTILE, 'persisted_hostile', $counters );
		}

		if ( $evidence->severity === PolicyEvidence::SEVERITY_CRITICAL ) {
			return $this->risk(
				PolicyState::BAND_HOSTILE,
				$evidence->isFirewallCritical() ? 'critical_firewall' : 'critical_evidence',
				$counters
			);
		}

		if ( $evidence->type === PolicyEvidence::TYPE_SHIELD_AUTO_BLOCK
			 && $sensitivity === PolicyRiskThresholds::SENSITIVITY_AGGRESSIVE ) {
			return $this->risk( PolicyState::BAND_HOSTILE, 'shield_auto_block_aggressive', $counters );
		}

		foreach ( PolicyRiskThresholds::CATEGORY_WINDOWS as $category => $window ) {
			if ( $state->counter( $category, $window ) >= PolicyRiskThresholds::threshold( $sensitivity, $category, 'hostile' ) ) {
				return $this->risk( PolicyState::BAND_HOSTILE, 'threshold_'.$category, $counters );
			}
		}

		if ( $state->risk_band === PolicyState::BAND_SUSPICIOUS ) {
			return $this->risk( PolicyState::BAND_SUSPICIOUS, 'persisted_suspicious', $counters );
		}

		foreach ( PolicyRiskThresholds::CATEGORY_WINDOWS as $category => $window ) {
			if ( $state->counter( $category, $window ) >= PolicyRiskThresholds::threshold( $sensitivity, $category, 'suspicious' ) ) {
				return $this->risk( PolicyState::BAND_SUSPICIOUS, 'threshold_'.$category, $counters );
			}
		}

		if ( $evidence->type === PolicyEvidence::TYPE_CROWDSEC ) {
			return $this->risk( PolicyState::BAND_SUSPICIOUS, 'crowdsec_signal', $counters );
		}

		if ( $evidence->type === PolicyEvidence::TYPE_SHIELD_AUTO_BLOCK ) {
			return $this->risk( PolicyState::BAND_SUSPICIOUS, 'shield_auto_block', $counters );
		}

		return $this->risk( PolicyState::BAND_NORMAL, 'none', $counters );
	}

	private function countersUsed( PolicyState $state ) :array {
		$counters = [];
		foreach ( PolicyRiskThresholds::CATEGORY_WINDOWS as $category => $window ) {
			$counters[ $category.'_'.$window ] = $state->counter( $category, $window );
		}
		return $counters;
	}

	private function risk( string $band, string $reason, array $counters ) :array {
		return [
			'band'     => $band,
			'reason'   => $reason,
			'counters' => $counters,
		];
	}

	public static function normaliseMode( string $mode ) :string {
		return \in_array( $mode, [ self::MODE_LEGACY, self::MODE_SHADOW, self::MODE_ADAPTIVE ], true )
			? $mode
			: self::MODE_LEGACY;
	}

	private function decision(
		string $mode,
		string $sensitivity,
		PolicyEvidence $evidence,
		RequestProfile $profile,
		ActorTrust $actorTrust,
		string $riskBand,
		string $riskReason,
		string $decision,
		string $reason,
		array $counters
	) :PolicyDecision {
		return new PolicyDecision( [
			'mode'                   => $mode,
			'sensitivity'            => $sensitivity,
			'detector'               => $evidence->detector,
			'surface'                => $profile->surface,
			'actor_trust_flags'      => $actorTrust->flags(),
			'risk_band'              => $riskBand,
			'risk_reason'            => $riskReason,
			'decision'               => $decision,
			'reason'                 => $reason,
			'block_category'         => $this->blockCategory( $decision, $reason, $riskBand, $riskReason, $evidence ),
			'evidence_counters_used' => $counters,
			'rule_slug'              => $evidence->rule_slug,
		] );
	}

	private function blockCategory(
		string $decision,
		string $reason,
		string $riskBand,
		string $riskReason,
		PolicyEvidence $evidence
	) :string {
		if ( $decision !== PolicyDecision::DECISION_BLOCK_REQUEST ) {
			return '';
		}

		if ( $evidence->type === PolicyEvidence::TYPE_SHIELD_MANUAL_BLOCK || $reason === 'shield_manual_block' ) {
			return PolicyDecision::BLOCK_CATEGORY_MANUAL_IP_BLOCK;
		}

		if ( $reason === 'firewall_critical' ) {
			return PolicyDecision::BLOCK_CATEGORY_CRITICAL_FIREWALL;
		}

		if ( \strpos( $riskReason, 'threshold_' ) === 0 ) {
			return PolicyDecision::BLOCK_CATEGORY_REPEATED_SUSPICIOUS_ACTIVITY;
		}

		if ( $reason === 'firewall_untrusted_actor' ) {
			return PolicyDecision::BLOCK_CATEGORY_UNTRUSTED_ACTOR;
		}

		if ( \in_array( $reason, [ 'crowdsec_sensitive_request', 'shield_auto_sensitive_request' ], true ) ) {
			return PolicyDecision::BLOCK_CATEGORY_SENSITIVE_REQUEST;
		}

		if ( $riskBand === PolicyState::BAND_HOSTILE ) {
			return PolicyDecision::BLOCK_CATEGORY_HOSTILE_IP;
		}

		return PolicyDecision::BLOCK_CATEGORY_SENSITIVE_REQUEST;
	}
}
