<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Enum,
	Utility
};

class IsRestApiRequestToRoute extends BaseRequestToRestAPI {

	/**
	 * @throws \Exception
	 */
	protected function execConditionCheck() :bool {
		return ( new Utility\PerformConditionMatch(
			$this->getRestRoute(),
			$this->p->match_value,
			$this->p->match_type
		) )->doMatch();
	}

	public function getParamsDef() :array {
		return [
			'match_type'  => [
				'type'      => Enum\EnumParameters::TYPE_ENUM,
				'type_enum' => Enum\EnumMatchTypes::MatchTypesForStrings(),
				'default'   => Enum\EnumMatchTypes::MATCH_TYPE_EQUALS_I,
				'label'     => __( 'Match Type', 'wp-simple-firewall' ),
			],
			'match_value' => [
				'type'  => Enum\EnumParameters::TYPE_STRING,
				'label' => __( 'Match Route', 'wp-simple-firewall' ),
			],
		];
	}
}