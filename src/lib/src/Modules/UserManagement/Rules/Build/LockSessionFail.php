<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build,
	Conditions,
	Enum,
	Responses
};
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

class LockSessionFail extends Build\BuildRuleCoreShieldBase {

	public const SLUG = 'shield/lock_session';

	protected function getName() :string {
		return 'The Request Is Blocked By Site Lockdown';
	}

	protected function getDescription() :string {
		return 'Does the request fail to meet any Site Lockdown exclusions and is to be blocked.';
	}

	protected function getConditions() :array {
		return [
			'logic'      => Enum\EnumLogic::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => Conditions\RequestBypassesAllRestrictions::class,
					'logic'      => Enum\EnumLogic::LOGIC_INVERT
				],
				[
					'logic'      => Enum\EnumLogic::LOGIC_INVERT,
					'conditions' => Conditions\MatchRequestIpIdentity::class,
					'params'     => [
						'match_type'  => Enum\EnumMatchTypes::MATCH_TYPE_EQUALS,
						'match_ip_id' => IpID::THIS_SERVER,
					],
				],
				[
					'logic'      => Enum\EnumLogic::LOGIC_INVERT,
					'conditions' => Conditions\MatchRequestIpIdentity::class,
					'params'     => [
						'match_type'  => Enum\EnumMatchTypes::MATCH_TYPE_EQUALS,
						'match_ip_id' => IpID::LOOPBACK,
					],
				],
				[
					'conditions' => Conditions\IsLoggedInNormal::class,
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
										'name'        => 'session_lock',
										'match_type'  => Enum\EnumMatchTypes::MATCH_TYPE_CONTAINS,
										'match_value' => 'ip',
									]
								],
								[
									'conditions' => Conditions\ShieldSessionParameterValueMatches::class,
									'logic'      => Enum\EnumLogic::LOGIC_INVERT,
									'params'     => [
										'param_name'    => 'ip',
										'match_type'    => Enum\EnumMatchTypes::MATCH_TYPE_EQUALS,
										'match_pattern' => '{{request.ip}}',
									]
								],
							]
						],
						[
							'logic'      => Enum\EnumLogic::LOGIC_AND,
							'conditions' => [
								[
									'conditions' => Conditions\ShieldConfigurationOption::class,
									'params'     => [
										'name'        => 'session_lock',
										'match_type'  => Enum\EnumMatchTypes::MATCH_TYPE_CONTAINS,
										'match_value' => 'useragent',
									]
								],
								[
									'conditions' => Conditions\ShieldSessionParameterValueMatches::class,
									'logic'      => Enum\EnumLogic::LOGIC_INVERT,
									'params'     => [
										'param_name'    => 'useragent',
										'match_type'    => Enum\EnumMatchTypes::MATCH_TYPE_EQUALS,
										'match_pattern' => '{{request.useragent}}',
									]
								],
							]
						],
					]
				]
			]
		];
	}

	protected function getResponses() :array {
		return [
			[
				'response' => Responses\UserClearAuthCookies::class,
			],
			[
				'response' => Responses\HttpRedirect::class,
				'params'   => [
					'redirect_url' => Services::WpGeneral()->getHomeUrl(),
					'status_code'  => 302,
				]
			],
		];
	}
}