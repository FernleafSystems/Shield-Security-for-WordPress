<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\RequestPolicy\PolicyEvidence;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;

class RequestPolicyGate extends Base {

	public const SLUG = 'request_policy_gate';

	public function execResponse() :void {
		self::con()->comps->request_policy->enforceRule(
			$this->rule,
			(string)$this->p->detector,
			$this->p->legacy_responses
		);
	}

	public function getParamsDef() :array {
		$detectors = [
			PolicyEvidence::DETECTOR_FIREWALL  => 'Firewall',
			PolicyEvidence::DETECTOR_CROWDSEC  => 'CrowdSec',
			PolicyEvidence::DETECTOR_SHIELD_IP => 'Shield IP',
		];
		return [
			'detector' => [
				'type'        => EnumParameters::TYPE_ENUM,
				'type_enum'   => \array_keys( $detectors ),
				'enum_labels' => $detectors,
				'label'       => __( 'Policy Detector', 'wp-simple-firewall' ),
			],
			'legacy_responses' => [
				'type'  => EnumParameters::TYPE_ARRAY,
				'label' => __( 'Legacy Responses', 'wp-simple-firewall' ),
			],
		];
	}
}
