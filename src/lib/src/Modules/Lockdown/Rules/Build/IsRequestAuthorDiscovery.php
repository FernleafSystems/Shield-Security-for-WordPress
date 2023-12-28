<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Lockdown\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Block\BlockAuthorFishing;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Conditions,
	Enum\EnumLogic,
	Enum\EnumMatchTypes,
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
			'logic'      => EnumLogic::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => Conditions\RequestBypassesAllRestrictions::class,
					'logic'      => EnumLogic::LOGIC_INVERT
				],
				[
					'conditions' => Conditions\IsLoggedInNormal::class,
					'logic'      => EnumLogic::LOGIC_INVERT,
				],
				[
					'conditions' => Conditions\RequestParamValueMatchesQuery::class,
					'params'     => [
						'match_type'    => EnumMatchTypes::MATCH_TYPE_REGEX,
						'param_name'    => 'author',
						'match_pattern' => '\\d',
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
				'response' => Responses\DisplayBlockPage::class,
				'params'   => [
					'block_page_slug' => BlockAuthorFishing::SLUG,
				],
			],
		];
	}
}