<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Conditions,
	Enum,
	Responses
};
use FernleafSystems\Wordpress\Services\Services;

class BotTrackFakeWebCrawler extends BuildRuleIpsBase {

	public const SLUG = 'shield/is_bot_probe_fakewebcrawler';

	protected function getName() :string {
		return 'Bot-Track Fake Web Crawler';
	}

	protected function getDescription() :string {
		return 'Track probing bots that incorrectly identify as official web crawlers.';
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
					'conditions' => Conditions\MatchRequestPath::class,
					'params'     => [
						'match_type' => Enum\EnumMatchTypes::MATCH_TYPE_REGEX,
						'match_path' => '.*',
					],
				],
				[
					'logic'      => Enum\EnumLogic::LOGIC_OR,
					'conditions' => \array_map(
						function ( $agent ) {
							return [
								'conditions' => Conditions\MatchRequestUseragent::class,
								'params'     => [
									'match_type'      => Enum\EnumMatchTypes::MATCH_TYPE_CONTAINS_I,
									'match_useragent' => $agent,
								],
							];
						},
						Services::ServiceProviders()->getAllCrawlerUseragents()
					),
				]
			]
		];
	}

	protected function getResponses() :array {
		$opts = $this->opts();
		return [
			[
				'response' => Responses\EventFire::class,
				'params'   => [
					'event'            => 'bottrack_fakewebcrawler',
					'offense_count'    => $opts->getOffenseCountFor( 'track_fakewebcrawler' ),
					'block'            => $opts->isTrackOptImmediateBlock( 'track_fakewebcrawler' ),
					'audit_params_map' => $this->getCommonAuditParamsMapping(),
				],
			],
		];
	}
}