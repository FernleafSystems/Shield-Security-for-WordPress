<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\RequestPolicy;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\RuleVO;

class RequestPolicyCon {

	use ExecOnce;
	use PluginControllerConsumer;

	private PolicyStateRepository $stateRepository;

	private PolicyEvidenceRecorder $evidenceRecorder;

	private PolicyDecisionEnforcer $decisionEnforcer;

	private PolicyLegacyResponseDispatcher $legacyDispatcher;

	protected function run() {
		if ( $this->requestPolicyMode() === RequestPolicyEvaluator::MODE_LEGACY ) {
			return;
		}

		$this->evidenceRecorder()->execute();
	}

	public function enforceRule( RuleVO $rule, string $detector, array $legacyResponses ) :void {
		$mode = $this->requestPolicyMode();
		if ( $mode === RequestPolicyEvaluator::MODE_LEGACY ) {
			$this->legacyDispatcher()->dispatch( $rule, $legacyResponses );
			return;
		}

		$evidence = $this->evidenceForRule( $rule, $detector );
		$decision = $this->evaluate( $mode, $this->requestPolicySensitivity(), $evidence );
		$this->decisionEnforcer()->enforce( $decision, $evidence, $rule, $legacyResponses );
	}

	public function isActionRouterIpAllowed() :bool {
		$mode = $this->requestPolicyMode();
		if ( $mode === RequestPolicyEvaluator::MODE_LEGACY ) {
			return false;
		}

		$evidence = $this->evidenceForActionRouter();
		$profile = ( new RequestProfileBuilder() )->build();
		$profile->surface = RequestProfile::SURFACE_SHIELD_ACTION;
		$profile->is_sensitive = true;

		$decision = $this->evaluator()->evaluate(
			$mode,
			$this->requestPolicySensitivity(),
			$profile,
			( new ActorTrustResolver() )->resolve(),
			$this->stateRepository()->forIp( self::con()->this_req->ip ),
			$evidence
		);

		return $this->decisionEnforcer()->allowsActionRouterRequest( $decision, $evidence );
	}

	public function stateRepository() :PolicyStateRepository {
		return $this->stateRepository ??= new PolicyStateRepository();
	}

	public function evidenceRecorder() :PolicyEvidenceRecorder {
		return $this->evidenceRecorder ??= new PolicyEvidenceRecorder( $this->stateRepository() );
	}

	public function decisionEnforcer() :PolicyDecisionEnforcer {
		return $this->decisionEnforcer ??= new PolicyDecisionEnforcer( $this->evidenceRecorder() );
	}

	private function legacyDispatcher() :PolicyLegacyResponseDispatcher {
		return $this->legacyDispatcher ??= new PolicyLegacyResponseDispatcher();
	}

	private function evaluate( string $mode, string $sensitivity, PolicyEvidence $evidence ) :PolicyDecision {
		return $this->evaluator()->evaluate(
			$mode,
			$sensitivity,
			( new RequestProfileBuilder() )->build(),
			( new ActorTrustResolver() )->resolve(),
			$this->stateRepository()->forIp( self::con()->this_req->ip ),
			$evidence
		);
	}

	private function evidenceForRule( RuleVO $rule, string $detector ) :PolicyEvidence {
		$conditionMeta = $rule->condition_meta;
		$evidence = [
			'detector'       => $detector,
			'rule_slug'      => $rule->slug,
			'condition_meta' => $conditionMeta,
		];

		if ( $detector === PolicyEvidence::DETECTOR_FIREWALL ) {
			$category = (string)( $conditionMeta[ 'match_category' ] ?? '' );
			$isCritical = \in_array( $category, RequestPolicyEvaluator::CRITICAL_FIREWALL_CATEGORIES, true );
			$evidence[ 'type' ] = PolicyEvidence::TYPE_FIREWALL_ABUSE;
			$evidence[ 'severity' ] = $isCritical ? PolicyEvidence::SEVERITY_CRITICAL : PolicyEvidence::SEVERITY_NOISY;
		}
		elseif ( $detector === PolicyEvidence::DETECTOR_CROWDSEC ) {
			$evidence[ 'type' ] = PolicyEvidence::TYPE_CROWDSEC;
			$evidence[ 'severity' ] = PolicyEvidence::SEVERITY_SIGNAL;
		}
		elseif ( $detector === PolicyEvidence::DETECTOR_SHIELD_IP ) {
			$isManual = (bool)self::con()->this_req->is_ip_blocked_shield_manual;
			$evidence[ 'type' ] = $isManual ? PolicyEvidence::TYPE_SHIELD_MANUAL_BLOCK : PolicyEvidence::TYPE_SHIELD_AUTO_BLOCK;
			$evidence[ 'severity' ] = $isManual ? PolicyEvidence::SEVERITY_CRITICAL : PolicyEvidence::SEVERITY_SIGNAL;
		}
		else {
			$evidence[ 'type' ] = PolicyEvidence::TYPE_NONE;
			$evidence[ 'severity' ] = PolicyEvidence::SEVERITY_INFO;
		}

		return new PolicyEvidence( $evidence );
	}

	private function evidenceForActionRouter() :PolicyEvidence {
		$req = self::con()->this_req;
		if ( $req->is_ip_blocked_shield ) {
			return new PolicyEvidence( [
				'detector'  => PolicyEvidence::DETECTOR_SHIELD_IP,
				'type'      => $req->is_ip_blocked_shield_manual
					? PolicyEvidence::TYPE_SHIELD_MANUAL_BLOCK
					: PolicyEvidence::TYPE_SHIELD_AUTO_BLOCK,
				'severity'  => $req->is_ip_blocked_shield_manual ? PolicyEvidence::SEVERITY_CRITICAL : PolicyEvidence::SEVERITY_SIGNAL,
				'rule_slug' => 'action_router_ip_guard',
			] );
		}

		return new PolicyEvidence( [
			'detector'  => PolicyEvidence::DETECTOR_CROWDSEC,
			'type'      => PolicyEvidence::TYPE_CROWDSEC,
			'severity'  => PolicyEvidence::SEVERITY_SIGNAL,
			'rule_slug' => 'action_router_ip_guard',
		] );
	}

	private function evaluator() :RequestPolicyEvaluator {
		return new RequestPolicyEvaluator();
	}

	private function requestPolicyMode() :string {
		return RequestPolicyEvaluator::normaliseMode( (string)self::con()->opts->optGet( 'request_policy_mode' ) );
	}

	private function requestPolicySensitivity() :string {
		return PolicyRiskThresholds::normaliseSensitivity( (string)self::con()->opts->optGet( 'request_policy_sensitivity' ) );
	}
}
