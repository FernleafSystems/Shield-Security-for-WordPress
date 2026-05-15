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
		$this->recorder->markDecision( self::con()->this_req->ip, $decision );

		if ( $decision->mode === RequestPolicyEvaluator::MODE_SHADOW ) {
			if ( $decision->blocksRequest() ) {
				$this->recordPolicyBlockEvidence( $evidence );
			}
			$this->legacyDispatcher()->dispatch( $rule, $legacyResponses );
			return;
		}

		if ( $decision->decision === PolicyDecision::DECISION_LOG_ONLY ) {
			$this->recordAllowedEvidence( $evidence, true );
			return;
		}

		if ( $decision->decision === PolicyDecision::DECISION_ALLOW
			 || $decision->decision === PolicyDecision::DECISION_NO_DECISION ) {
			$this->recordAllowedEvidence( $evidence, false );
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
				$this->recorder->markDecision( self::con()->this_req->ip, $decision );
				if ( $decision->blocksRequest() ) {
					$this->recordPolicyBlockEvidence( $evidence );
				}
			}
			return false;
		}

		$this->firePolicyDecisionEvent( $decision );
		$this->recorder->markDecision( self::con()->this_req->ip, $decision );
		if ( $decision->blocksRequest() ) {
			$this->recordPolicyBlockEvidence( $evidence );
		}
		elseif ( $decision->allowsRequest() || $decision->decision === PolicyDecision::DECISION_NO_DECISION ) {
			$this->recordAllowedEvidence( $evidence, false );
		}

		return $decision->allowsRequest();
	}

	private function recordPolicyBlockEvidence( PolicyEvidence $evidence ) :void {
		if ( $evidence->detector !== PolicyEvidence::DETECTOR_FIREWALL ) {
			$this->recorder->record( self::con()->this_req->ip, $evidence );
		}
	}

	private function recordAllowedEvidence( PolicyEvidence $evidence, bool $isLogOnly ) :void {
		if ( $isLogOnly && $evidence->detector === PolicyEvidence::DETECTOR_FIREWALL ) {
			$evidence = new PolicyEvidence( [
				'detector'       => $evidence->detector,
				'type'           => PolicyEvidence::TYPE_FIREWALL_LOG,
				'severity'       => PolicyEvidence::SEVERITY_NOISY,
				'rule_slug'      => $evidence->rule_slug,
				'condition_meta' => $evidence->condition_meta,
			] );
		}
		$this->recorder->record( self::con()->this_req->ip, $evidence );
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
