<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Build\Core;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Conditions,
	Enum,
	Responses
};

class ShieldLogRequest extends BuildRuleCoreShieldBase {

	public const SLUG = 'shield/set_log_request';

	protected function getName() :string {
		return 'Log Request';
	}

	protected function getDescription() :string {
		return 'Log Request To Traffic Log.';
	}

	protected function getConditions() :array {
		return [
			'logic'      => Enum\EnumLogic::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => Conditions\ShieldConfigurationOption::class,
					'params'     => [
						'name'        => 'enable_audit_trail',
						'match_type'  => Enum\EnumMatchTypes::MATCH_TYPE_EQUALS,
						'match_value' => 'Y',
					]
				],
				[
					'logic'      => Enum\EnumLogic::LOGIC_OR,
					'conditions' => [
						[
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
										'name'        => 'auto_clean',
										'match_type'  => Enum\EnumMatchTypes::MATCH_TYPE_GREATER_THAN,
										'match_value' => 0,
									]
								],
							]
						],
						[
							'conditions' => Conditions\ShieldConfigIsTrafficRateLimitingEnabled::class,
						],
						[
							'conditions' => Conditions\ShieldConfigIsLiveLoggingEnabled::class,
						],
					]
				],
			]
		];
	}

	protected function getResponses() :array {
		return [
			[
				'response' => Responses\SetRequestToBeLogged::class,
				'params'   => [
					'do_log' => true,
				]
			],
		];
	}
}