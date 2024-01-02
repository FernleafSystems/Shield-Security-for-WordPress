<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Enum,
	Utility
};

class MatchRequestScriptName extends Base {

	use Traits\TypeRequest;

	public const SLUG = 'match_request_script_name';

	protected function execConditionCheck() :bool {
		// always add this in-case we need to invert_match
		$this->addConditionTriggerMeta( 'script', $this->req->script_name );
		return ( new Utility\PerformConditionMatch(
			$this->req->script_name,
			$this->p->match_script_name,
			$this->p->match_type
		) )->doMatch();
	}

	public function getDescription() :string {
		return __( 'Does the request script name match the given name.', 'wp-simple-firewall' );
	}

	public function getParamsDef() :array {
		return [
			'match_type'        => [
				'type'      => Enum\EnumParameters::TYPE_ENUM,
				'type_enum' => Enum\EnumMatchTypes::MatchTypesForStrings(),
				'default'   => Enum\EnumMatchTypes::MATCH_TYPE_REGEX,
				'label'     => __( 'Match Type', 'wp-simple-firewall' ),
			],
			'match_script_name' => [
				'type'  => Enum\EnumParameters::TYPE_STRING,
				'label' => __( 'Script Name To Match', 'wp-simple-firewall' ),
			],
		];
	}
}