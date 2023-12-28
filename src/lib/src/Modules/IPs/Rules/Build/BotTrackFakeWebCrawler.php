<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Conditions,
	Enum\EnumLogic,
	Enum\EnumMatchTypes,
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
					'conditions' => Conditions\MatchRequestPath::class,
					'params'     => [
						'match_type' => EnumMatchTypes::MATCH_TYPE_REGEX,
						'match_path' => '.*',
					],
				],
				[
					'conditions' => Conditions\MatchRequestUseragents::class,
					'params'     => [
						'match_useragents' => Services::ServiceProviders()->getAllCrawlerUseragents(),
					],
				],
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