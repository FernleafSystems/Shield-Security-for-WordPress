<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\RequestPolicy;

class RequestPolicyEvaluator {

	public const MODE_LEGACY = 'legacy';
	public const MODE_SHADOW = 'shadow';
	public const MODE_ADAPTIVE = 'adaptive';

	public const CROWDSEC_MODE_HARD = 'hard';
	public const CROWDSEC_MODE_ADAPTIVE_SIGNAL = 'adaptive_signal';

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
		string $crowdsecMode,
		RequestProfile $profile,
		ActorTrust $actorTrust,
		PolicyState $state,
		PolicyEvidence $evidence
	) :PolicyDecision {
		$mode = self::normaliseMode( $mode );
		$risk = $this->riskBand( $state, $evidence );

		if ( $mode === self::MODE_LEGACY ) {
			return $this->decision(
				$mode,
				$evidence,
				$profile,
				$actorTrust,
				$risk[ 'band' ],
				PolicyDecision::DECISION_LEGACY,
				'legacy_mode',
				$risk[ 'counters' ]
			);
		}

		switch ( $evidence->detector ) {
			case PolicyEvidence::DETECTOR_FIREWALL:
				[ $decision, $reason ] = $this->firewallDecision( $profile, $actorTrust, $risk[ 'band' ], $evidence );
				break;

			case PolicyEvidence::DETECTOR_CROWDSEC:
				[ $decision, $reason ] = $this->crowdsecDecision( $crowdsecMode, $profile, $risk[ 'band' ], $state );
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
			$evidence,
			$profile,
			$actorTrust,
			$risk[ 'band' ],
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
		$category = (string)( $evidence->condition_meta[ 'match_category' ] ?? '' );

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
		string $crowdsecMode,
		RequestProfile $profile,
		string $riskBand,
		PolicyState $state
	) :array {
		if ( $crowdsecMode !== self::CROWDSEC_MODE_ADAPTIVE_SIGNAL ) {
			return [ PolicyDecision::DECISION_BLOCK_REQUEST, 'crowdsec_hard_mode' ];
		}

		if ( $riskBand === PolicyState::BAND_HOSTILE ) {
			return [ PolicyDecision::DECISION_BLOCK_REQUEST, 'crowdsec_hostile_risk' ];
		}

		if ( $profile->isReadSurface() ) {
			return [ PolicyDecision::DECISION_ALLOW, 'crowdsec_safe_read' ];
		}

		if ( $profile->surface === RequestProfile::SURFACE_AUTH_ATTEMPT
			 && $state->counter( PolicyEvidence::TYPE_AUTH_FAILURE, '15m' ) === 0
			 && $state->counter( PolicyEvidence::TYPE_AUTH_INVALID_USER, '15m' ) === 0 ) {
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

	private function riskBand( PolicyState $state, PolicyEvidence $evidence ) :array {
		$counters = $this->countersUsed( $state );

		$hostile = $state->risk_band === PolicyState::BAND_HOSTILE
				   || $evidence->isFirewallCritical()
				   || $evidence->type === PolicyEvidence::TYPE_SHIELD_MANUAL_BLOCK
				   || $counters[ 'auth_failure_15m' ] >= 3
				   || $counters[ 'username_fishing_24h' ] >= 1
				   || $counters[ 'xmlrpc_15m' ] >= 3
				   || $counters[ 'rate_limit_15m' ] >= 1
				   || $counters[ 'firewall_block_24h' ] >= 2
				   || $counters[ 'ip_blocked_24h' ] >= 1;

		if ( $hostile ) {
			return [
				'band'     => PolicyState::BAND_HOSTILE,
				'counters' => $counters,
			];
		}

		$suspicious = $state->risk_band === PolicyState::BAND_SUSPICIOUS
					  || $evidence->type === PolicyEvidence::TYPE_CROWDSEC
					  || $evidence->type === PolicyEvidence::TYPE_SHIELD_AUTO_BLOCK
					  || $counters[ 'auth_failure_15m' ] >= 1
					  || $counters[ 'auth_invalid_user_24h' ] >= 1
					  || $counters[ 'xmlrpc_24h' ] >= 1
					  || $counters[ 'rate_limit_24h' ] >= 1
					  || $counters[ 'firewall_log_24h' ] >= 1
					  || $counters[ 'ip_offense_24h' ] >= 1;

		return [
			'band'     => $suspicious ? PolicyState::BAND_SUSPICIOUS : PolicyState::BAND_NORMAL,
			'counters' => $counters,
		];
	}

	private function countersUsed( PolicyState $state ) :array {
		return [
			'auth_failure_15m'      => $state->counter( PolicyEvidence::TYPE_AUTH_FAILURE, '15m' ),
			'auth_invalid_user_15m' => $state->counter( PolicyEvidence::TYPE_AUTH_INVALID_USER, '15m' ),
			'auth_invalid_user_24h' => $state->counter( PolicyEvidence::TYPE_AUTH_INVALID_USER, '24h' ),
			'username_fishing_24h'  => $state->counter( PolicyEvidence::TYPE_USERNAME_FISHING, '24h' ),
			'xmlrpc_15m'            => $state->counter( PolicyEvidence::TYPE_XMLRPC, '15m' ),
			'xmlrpc_24h'            => $state->counter( PolicyEvidence::TYPE_XMLRPC, '24h' ),
			'rate_limit_15m'        => $state->counter( PolicyEvidence::TYPE_RATE_LIMIT, '15m' ),
			'rate_limit_24h'        => $state->counter( PolicyEvidence::TYPE_RATE_LIMIT, '24h' ),
			'firewall_block_24h'    => $state->counter( PolicyEvidence::TYPE_FIREWALL_NOISY, '24h' )
									   + $state->counter( PolicyEvidence::TYPE_FIREWALL_CRITICAL, '24h' ),
			'firewall_log_24h'      => $state->counter( PolicyEvidence::TYPE_FIREWALL_LOG, '24h' ),
			'ip_offense_24h'        => $state->counter( PolicyEvidence::TYPE_IP_OFFENSE, '24h' ),
			'ip_blocked_24h'        => $state->counter( PolicyEvidence::TYPE_IP_BLOCKED, '24h' ),
		];
	}

	public static function normaliseMode( string $mode ) :string {
		return \in_array( $mode, [ self::MODE_LEGACY, self::MODE_SHADOW, self::MODE_ADAPTIVE ], true )
			? $mode
			: self::MODE_LEGACY;
	}

	private function decision(
		string $mode,
		PolicyEvidence $evidence,
		RequestProfile $profile,
		ActorTrust $actorTrust,
		string $riskBand,
		string $decision,
		string $reason,
		array $counters
	) :PolicyDecision {
		return new PolicyDecision( [
			'mode'                   => $mode,
			'detector'               => $evidence->detector,
			'surface'                => $profile->surface,
			'actor_trust_flags'      => $actorTrust->flags(),
			'risk_band'              => $riskBand,
			'decision'               => $decision,
			'reason'                 => $reason,
			'evidence_counters_used' => $counters,
			'rule_slug'              => $evidence->rule_slug,
		] );
	}
}
