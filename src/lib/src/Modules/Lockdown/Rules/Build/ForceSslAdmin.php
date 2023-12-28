<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Lockdown\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Conditions,
	Enum,
	Responses
};

class ForceSslAdmin extends BuildRuleLockdownBase {

	public const SLUG = 'shield/force_ssl_admin';

	protected function getName() :string {
		return 'Force SSL Admin';
	}

	protected function getDescription() :string {
		return 'Force SSL Admin.';
	}

	protected function getResponses() :array {
		return [
			[
				'response' => Responses\SetPhpDefine::class,
				'params'   => [
					'define_name'  => 'FORCE_SSL_ADMIN',
					'define_value' => true,
				]
			],
			[
				'response' => Responses\CallUserFuncArray::class,
				'params'   => [
					'callback' => '\\force_ssl_admin',
					'args'     => [ true ],
				]
			],
		];
	}

	protected function getConditions() :array {
		return [
			'conditions' => Conditions\RequestBypassesAllRestrictions::class,
			'logic'      => Enum\EnumLogic::LOGIC_INVERT
		];
	}
}