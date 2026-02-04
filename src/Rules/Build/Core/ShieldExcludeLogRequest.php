<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Build\Core;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Conditions,
	Enum,
	Responses
};
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

class ShieldExcludeLogRequest extends BuildRuleCoreShieldBase {

	public const SLUG = 'shield/exclude_log_request';

	protected function getName() :string {
		return 'Exclude Log Request';
	}

	protected function getDescription() :string {
		return 'Whether to exclude logging of the request.';
	}

	protected function getConditions() :array {
		return [
			'logic'      => Enum\EnumLogic::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => Conditions\ShieldConfigIsLiveLoggingEnabled::class,
					'logic'      => Enum\EnumLogic::LOGIC_INVERT,
				],
				[
					'logic'      => Enum\EnumLogic::LOGIC_OR,
					'conditions' => \array_merge(
						\array_map(
							function ( $customExclusion ) {
								return [
									'conditions' => Conditions\MatchRequestPath::class,
									'params'     => [
										'match_path' => $customExclusion,
										'match_type' => Enum\EnumMatchTypes::MATCH_TYPE_CONTAINS_I,
									]
								];
							},
							self::con()->opts->optGet( 'custom_exclusions' )
						),
						[
							[
								'logic'      => Enum\EnumLogic::LOGIC_AND,
								'conditions' => [
									[
										'conditions' => Conditions\WpIsCron::class,
									],
									[
										'conditions' => Conditions\ShieldConfigurationOption::class,
										'params'     => [
											'name'        => 'type_exclusions',
											'match_type'  => Enum\EnumMatchTypes::MATCH_TYPE_CONTAINS,
											'match_value' => 'cron',
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
											'name'        => 'type_exclusions',
											'match_type'  => Enum\EnumMatchTypes::MATCH_TYPE_CONTAINS,
											'match_value' => 'logged_in',
										]
									],
									[
										'conditions' => Conditions\IsLoggedInNormal::class,
									],
								]
							],
							[
								'logic'      => Enum\EnumLogic::LOGIC_AND,
								'conditions' => [
									[
										'conditions' => Conditions\ShieldConfigurationOption::class,
										'params'     => [
											'name'        => 'type_exclusions',
											'match_type'  => Enum\EnumMatchTypes::MATCH_TYPE_CONTAINS,
											'match_value' => 'server',
										]
									],
									[
										'conditions' => Conditions\MatchRequestIpIdentity::class,
										'params'     => [
											'match_type'  => Enum\EnumParameters::TYPE_STRING,
											'match_ip_id' => IpID::THIS_SERVER,
										],
									],
								]
							],
							[
								'logic'      => Enum\EnumLogic::LOGIC_AND,
								'conditions' => [
									[
										'conditions' => Conditions\ShieldConfigurationOption::class,
										'params'     => [
											'name'        => 'type_exclusions',
											'match_type'  => Enum\EnumMatchTypes::MATCH_TYPE_CONTAINS,
											'match_value' => 'search',
										]
									],
									[
										'conditions' => Conditions\IsBotOfType::class,
										'params'     => [
											'match_type' => 'search',
										],
									],
								]
							],
							[
								'logic'      => Enum\EnumLogic::LOGIC_AND,
								'conditions' => [
									[
										'conditions' => Conditions\ShieldConfigurationOption::class,
										'params'     => [
											'name'        => 'type_exclusions',
											'match_type'  => Enum\EnumMatchTypes::MATCH_TYPE_CONTAINS,
											'match_value' => 'uptime',
										]
									],
									[
										'conditions' => Conditions\IsBotOfType::class,
										'params'     => [
											'match_type' => 'uptime',
										],
									],
								]
							],
						]
					)
				]
			]
		];
	}

	protected function getResponses() :array {
		return [
			[
				'response' => Responses\SetRequestToBeLogged::class,
				'params'   => [
					'do_log' => false,
				]
			],
		];
	}
}