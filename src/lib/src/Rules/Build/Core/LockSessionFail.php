<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Build\Core;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build,
	Conditions,
	Enum,
	Responses
};
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

class LockSessionFail extends Build\Core\BuildRuleCoreShieldBase {

	public const SLUG = 'shield/lock_session';

	protected function getName() :string {
		return 'Session Lock';
	}

	protected function getDescription() :string {
		return 'Lock sessions to either IP address, browser, or both and prevent usage.';
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
					'conditions' => Conditions\ShieldHasValidCurrentSession::class,
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
										'name'        => 'enable_user_management',
										'match_type'  => Enum\EnumMatchTypes::MATCH_TYPE_EQUALS,
										'match_value' => 'Y',
									]
								],
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
						[
							'logic'      => Enum\EnumLogic::LOGIC_AND,
							'conditions' => [
								[
									'conditions' => Conditions\ShieldConfigurationOption::class,
									'params'     => [
										'name'        => 'session_lock',
										'match_type'  => Enum\EnumMatchTypes::MATCH_TYPE_CONTAINS,
										'match_value' => 'host',
									]
								],
								[
									'conditions' => Conditions\ShieldSessionParameterValueMatches::class,
									'logic'      => Enum\EnumLogic::LOGIC_INVERT,
									'params'     => [
										'param_name'    => 'host',
										'match_type'    => Enum\EnumMatchTypes::MATCH_TYPE_EQUALS,
										'match_pattern' => '{{request.host}}',
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
				'response' => Responses\EventFire::class,
				'params'   => [
					'event'            => 'session_lock',
					'audit_params_map' => [
						'user_login' => 'user_login',
					],
				],
			],
			[
				'response' => Responses\UserSessionLogoutCurrent::class,
			],
			[
				'response' => Responses\HttpRedirect::class,
				'params'   => [
					'redirect_url' => Services::WpGeneral()->getLoginUrl(),
					'status_code'  => 302,
				]
			],
		];
	}
}