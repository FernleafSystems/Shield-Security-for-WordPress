<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Lockdown\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Conditions,
	Constants,
	Responses
};

class IsRequestAuthorDiscovery extends BuildRuleLockdownBase {

	public const SLUG = 'shield/is_request_author_discovery';

	protected function getName() :string {
		return 'Detect Author Discovery';
	}

	protected function getDescription() :string {
		return 'Detect and block Author Discovery requests via ?author=x query.';
	}

	protected function getConditions() :array {
		return [
			'logic' => Constants::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => Conditions\RequestBypassesAllRestrictions::class,
					'logic'      => Constants::LOGIC_INVERT
				],
				[
					'conditions' => Conditions\IsNotLoggedInNormal::class,
				],
				[
					'conditions' => Conditions\RequestQueryParamIs::class,
					'params'    => [
						'match_param'    => 'author',
						'match_patterns' => [
							'\d'
						],
					],
				],
			]
		];
	}

	protected function getResponses() :array {
		return [
			[
				'response' => Responses\EventFire::class,
				'params'   => [
					'event'         => 'block_author_fishing',
					'offense_count' => $this->opts()->isBlockAuthorDiscovery() ? 1 : 0,
					'block'         => false,
				],
			],
			[
				'response' => Responses\BlockAuthorFishing::class,
			],
		];
	}
}