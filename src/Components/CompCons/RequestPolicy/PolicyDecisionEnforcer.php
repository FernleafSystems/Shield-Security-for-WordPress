<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\RequestPolicy;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\RuleVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class PolicyDecisionEnforcer {

	use PluginControllerConsumer;

	private PolicyEvidenceRecorder $recorder;

	private PolicyLegacyResponseDispatcher $legacyDispatcher;

	public function __construct( PolicyEvidenceRecorder $recorder ) {
		$this->recorder = $recorder;
	}

	public function enforce( PolicyDecision $decision, PolicyEvidence $evidence, RuleVO $rule, array $legacyResponses ) :void {
		if ( $decision->mode === RequestPolicyEvaluator::MODE_LEGACY ) {
			$this->legacyDispatcher()->dispatch( $rule, $legacyResponses );
			return;
		}

		$this->firePolicyDecisionEvent( $decision );

		if ( $decision->mode === RequestPolicyEvaluator::MODE_SHADOW ) {
			if ( $decision->blocksRequest() ) {
				$this->recordPolicyBlockEvidence( $evidence );
			}
			$this->legacyDispatcher()->dispatch( $rule, $legacyResponses );
			return;
		}

		if ( $decision->decision === PolicyDecision::DECISION_LOG_ONLY ) {
			$this->recordLogOnlyEvidence( $evidence );
			return;
		}

		if ( $decision->decision === PolicyDecision::DECISION_ALLOW
			 || $decision->decision === PolicyDecision::DECISION_NO_DECISION ) {
			if ( \in_array( $evidence->detector, [ PolicyEvidence::DETECTOR_CROWDSEC, PolicyEvidence::DETECTOR_SHIELD_IP ], true ) ) {
				$this->legacyDispatcher()->updateIpLastAccess( $rule );
			}
			return;
		}

		$this->recordPolicyBlockEvidence( $evidence );
		$this->legacyDispatcher()->dispatch( $rule, $legacyResponses );
	}

	public function allowsActionRouterRequest( PolicyDecision $decision, PolicyEvidence $evidence ) :bool {
		if ( $decision->mode === RequestPolicyEvaluator::MODE_LEGACY || $decision->mode === RequestPolicyEvaluator::MODE_SHADOW ) {
			if ( $decision->mode === RequestPolicyEvaluator::MODE_SHADOW ) {
				$this->firePolicyDecisionEvent( $decision );
				if ( $decision->blocksRequest() ) {
					$this->recordPolicyBlockEvidence( $evidence );
				}
			}
			return false;
		}

		$this->firePolicyDecisionEvent( $decision );
		if ( $decision->blocksRequest() ) {
			$this->recordPolicyBlockEvidence( $evidence );
		}

		return $decision->allowsRequest();
	}

	private function recordPolicyBlockEvidence( PolicyEvidence $evidence ) :void {
		if ( $evidence->detector !== PolicyEvidence::DETECTOR_FIREWALL ) {
			$this->recorder->record( self::con()->this_req->ip, $evidence );
		}
	}

	private function recordLogOnlyEvidence( PolicyEvidence $evidence ) :void {
		if ( $evidence->detector !== PolicyEvidence::DETECTOR_FIREWALL ) {
			return;
		}

		$this->recorder->record( self::con()->this_req->ip, new PolicyEvidence( [
			'detector'       => $evidence->detector,
			'type'           => PolicyEvidence::TYPE_FIREWALL_LOG,
			'severity'       => PolicyEvidence::SEVERITY_NOISY,
			'rule_slug'      => $evidence->rule_slug,
			'condition_meta' => $evidence->condition_meta,
		] ) );
	}

	private function firePolicyDecisionEvent( PolicyDecision $decision ) :void {
		self::con()->comps->events->fireEvent( 'request_policy_decision', [
			'audit_params' => [
				'mode'      => $decision->mode,
				'detector'  => $decision->detector,
				'decision'  => $decision->decision,
				'reason'    => $decision->reason,
				'surface'   => $decision->surface,
				'risk_band' => $decision->risk_band,
				'rule'      => $decision->rule_slug,
			],
		] );
	}

	private function legacyDispatcher() :PolicyLegacyResponseDispatcher {
		return $this->legacyDispatcher ??= new PolicyLegacyResponseDispatcher();
	}
}
