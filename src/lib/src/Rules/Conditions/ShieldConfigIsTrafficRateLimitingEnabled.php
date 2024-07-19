<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Conditions,
	Enum
};

class ShieldConfigIsTrafficRateLimitingEnabled extends Base {

	use Traits\TypeShield;

	public function getDescription() :string {
		return __( 'Is Shield Traffic Rate Limiting Enabled.', 'wp-simple-firewall' );
	}

	protected function getSubConditions() :array {
		return [
			'logic'      => Enum\EnumLogic::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => Conditions\ShieldConfigurationOption::class,
					'params'     => [
						'name'        => 'enable_logger',
						'match_type'  => Enum\EnumMatchTypes::MATCH_TYPE_EQUALS,
						'match_value' => 'Y',
					]
				],
				[
					'conditions' => Conditions\ShieldConfigurationOption::class,
					'params'     => [
						'name'        => 'enable_limiter',
						'match_type'  => Enum\EnumMatchTypes::MATCH_TYPE_EQUALS,
						'match_value' => 'Y',
					]
				],
				[
					'conditions' => Conditions\ShieldConfigurationOption::class,
					'params'     => [
						'name'        => 'limit_requests',
						'match_type'  => Enum\EnumMatchTypes::MATCH_TYPE_GREATER_THAN,
						'match_value' => 0,
					]
				],
				[
					'conditions' => Conditions\ShieldConfigurationOption::class,
					'params'     => [
						'name'        => 'limit_time_span',
						'match_type'  => Enum\EnumMatchTypes::MATCH_TYPE_GREATER_THAN,
						'match_value' => 0,
					]
				],
			]
		];
	}
}