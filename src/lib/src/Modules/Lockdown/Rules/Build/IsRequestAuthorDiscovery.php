<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Lockdown\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Conditions,
	Responses
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rules\Build\RequestBypassesAllRestrictions;

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
			'logic' => static::LOGIC_AND,
			'group' => [
				[
					'rule'         => RequestBypassesAllRestrictions::SLUG,
					'invert_match' => true
				],
				[
					'condition' => Conditions\IsNotLoggedInNormal::SLUG,
				],
				[
					'condition' => Conditions\RequestQueryParamIs::SLUG,
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
				'response' => Responses\EventFire::SLUG,
				'params'   => [
					'event'         => 'block_author_fishing',
					'offense_count' => $this->opts()->isBlockAuthorDiscovery() ? 1 : 0,
					'block'         => false,
				],
			],
			[
				'response' => Responses\BlockAuthorFishing::SLUG,
			],
		];
	}
}