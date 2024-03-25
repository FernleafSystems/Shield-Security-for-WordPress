<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Build\Core;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Block\BlockAuthorFishing;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Conditions,
	Enum,
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
			'logic'      => Enum\EnumLogic::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => Conditions\RequestBypassesAllRestrictions::class,
					'logic'      => Enum\EnumLogic::LOGIC_INVERT
				],
				[
					'conditions' => Conditions\IsLoggedInNormal::class,
					'logic'      => Enum\EnumLogic::LOGIC_INVERT,
				],
				[
					'conditions' => Conditions\ShieldConfigurationOption::class,
					'params'     => [
						'name'        => 'block_author_discovery',
						'match_type'  => Enum\EnumMatchTypes::MATCH_TYPE_EQUALS,
						'match_value' => 'Y',
					]
				],
				[
					'conditions' => Conditions\RequestParameterValueMatches::class,
					'params'     => [
						'req_param_source' => 'get',
						'match_type'       => Enum\EnumMatchTypes::MATCH_TYPE_REGEX,
						'param_name'       => 'author',
						'match_pattern'    => '#\\d#',
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
					'offense_count' => 1,
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