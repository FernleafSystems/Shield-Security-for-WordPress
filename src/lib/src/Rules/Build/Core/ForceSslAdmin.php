<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Build\Core;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Conditions,
	Enum,
	Responses
};

/**
 * @deprecated 19.2
 */
class ForceSslAdmin extends BuildRuleLockdownBase {

	public const SLUG = 'shield/force_ssl_admin';

	protected function getName() :string {
		return 'Force SSL Admin';
	}

	protected function getDescription() :string {
		return 'Force SSL Admin.';
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
					'conditions' => Conditions\ShieldConfigurationOption::class,
					'params'     => [
						'name'        => 'force_ssl_admin',
						'match_type'  => Enum\EnumMatchTypes::MATCH_TYPE_EQUALS,
						'match_value' => 'Y',
					]
				],
			]
		];
	}

	protected function getResponses() :array {
		return [
			[
				'response' => Responses\PhpSetDefine::class,
				'params'   => [
					'name'  => 'FORCE_SSL_ADMIN',
					'value' => true,
				]
			],
			[
				'response' => Responses\PhpCallUserFuncArray::class,
				'params'   => [
					'callback' => '\\force_ssl_admin',
					'args'     => [ true ],
				]
			],
		];
	}
}