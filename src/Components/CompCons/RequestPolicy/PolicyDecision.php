<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\RequestPolicy;

class PolicyDecision {

	public const DECISION_ALLOW = 'allow';
	public const DECISION_LOG_ONLY = 'log_only';
	public const DECISION_BLOCK_REQUEST = 'block_request';
	public const DECISION_LEGACY = 'legacy';
	public const DECISION_NO_DECISION = 'no_decision';

	public string $mode = 'legacy';

	public string $detector = '';

	public string $surface = '';

	public array $actor_trust_flags = [];

	public string $risk_band = PolicyState::BAND_NORMAL;

	public string $decision = self::DECISION_LEGACY;

	public string $reason = 'legacy_mode';

	public array $evidence_counters_used = [];

	public string $rule_slug = '';

	public function __construct( array $data = [] ) {
		foreach ( $data as $key => $value ) {
			if ( \property_exists( $this, $key ) ) {
				$this->{$key} = $value;
			}
		}
	}

	public function blocksRequest() :bool {
		return $this->decision === self::DECISION_BLOCK_REQUEST;
	}

	public function allowsRequest() :bool {
		return \in_array( $this->decision, [ self::DECISION_ALLOW, self::DECISION_LOG_ONLY ], true );
	}
}
