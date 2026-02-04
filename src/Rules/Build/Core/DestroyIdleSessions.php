<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Build\Core;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build,
	Conditions,
	Enum,
	Responses
};
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

class DestroyIdleSessions extends Build\Core\BuildRuleCoreShieldBase {

	public const SLUG = 'shield/destroy_idle_sessions';

	protected function getName() :string {
		return 'Destroy Idle Sessions';
	}

	protected function getDescription() :string {
		return 'Destroy session if idle.';
	}

	protected function getConditions() :array {
		$idle = self::con()->comps->opts_lookup->getSessionIdleInterval();
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
						'name'        => 'session_idle_timeout_interval',
						'match_type'  => Enum\EnumMatchTypes::MATCH_TYPE_GREATER_THAN,
						'match_value' => 0,
					]
				],
				[
					'conditions' => Conditions\ShieldSessionParameterValueMatches::class,
					'params'     => [
						'param_name'    => 'idle_interval',
						'match_type'    => Enum\EnumMatchTypes::MATCH_TYPE_GREATER_THAN,
						'match_pattern' => $idle,
					]
				],
			]
		];
	}

	protected function getResponses() :array {
		return [
			[
				'response' => Responses\EventFire::class,
				'params'   => [
					'event'            => 'session_idle',
					'audit_params_map' => [
						'user_login' => 'user_login',
					],
				],
			],
			[
				'response' => Responses\UserSessionLogoutCurrent::class,
			],
		];
	}
}