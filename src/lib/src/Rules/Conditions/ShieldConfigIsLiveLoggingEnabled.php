<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Conditions,
	Enum
};

class ShieldConfigIsLiveLoggingEnabled extends Base {

	use Traits\TypeShield;

	public function getDescription() :string {
		return __( 'Is Shield live logging enabled.', 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		return self::con()->comps !== null && self::con()->comps->opts_lookup->getTrafficLiveLogTimeRemaining() > 0;
	}

	protected function getSubConditions() :array {
		return [
			'logic'      => Enum\EnumLogic::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => Conditions\ShieldConfigurationOption::class,
					'params'     => [
						'name'        => 'enable_traffic',
						'match_type'  => Enum\EnumMatchTypes::MATCH_TYPE_EQUALS,
						'match_value' => 'Y',
					]
				],
				[
					'conditions' => $this->getDefaultConditionCheckCallable(),
				],
			]
		];
	}
}