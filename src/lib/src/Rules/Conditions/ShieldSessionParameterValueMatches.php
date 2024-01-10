<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Enum,
	Utility
};

class ShieldSessionParameterValueMatches extends Base {

	use Traits\TypeRequest;

	protected function execConditionCheck() :bool {
		$matched = false;

		$value = self::con()
					 ->getModule_Plugin()
					 ->getSessionCon()
					 ->current()
					 ->shield[ $this->p->param_name ] ?? null;
		if ( $value !== null ) {
			$matched = ( new Utility\PerformConditionMatch( $value, $this->p->match_pattern, $this->p->match_type ) )->doMatch();
			$this->addConditionTriggerMeta( 'match_pattern', $this->p->match_pattern );
			$this->addConditionTriggerMeta( 'match_request_param', $this->p->param_name );
			$this->addConditionTriggerMeta( 'match_request_value', $value );
		}
		return $matched;
	}

	public function getDescription() :string {
		return __( 'Does the value of the given Shield Session parameter match the given pattern.', 'wp-simple-firewall' );
	}

	public function getParamsDef() :array {
		return [
			'param_name'    => [
				'type'  => Enum\EnumParameters::TYPE_STRING,
				'label' => __( 'Session Parameter Name', 'wp-simple-firewall' ),
			],
			'match_type'    => [
				'type'      => Enum\EnumParameters::TYPE_ENUM,
				'type_enum' => Enum\EnumMatchTypes::MatchTypesForStrings(),
				'default'   => Enum\EnumMatchTypes::MATCH_TYPE_EQUALS,
				'label'     => __( 'Match Type', 'wp-simple-firewall' ),
			],
			'match_pattern' => [
				'type'  => Enum\EnumParameters::TYPE_STRING,
				'label' => __( 'Compare Parameter Value To', 'wp-simple-firewall' ),
			],
		];
	}

	protected function getSubConditions() :array {
		return [
			'logic'      => Enum\EnumLogic::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => IsLoggedInNormal::class,
				],
				[
					'conditions' => $this->getDefaultConditionCheckCallable(),
				],
			]
		];
	}
}