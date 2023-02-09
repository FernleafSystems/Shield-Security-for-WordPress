<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\{
	IPs,
	Plugin
};
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\BuildRuleCoreShieldBase,
	Conditions,
	Responses
};
use FernleafSystems\Wordpress\Services\Services;

class BotTrackFakeWebCrawler extends BuildRuleCoreShieldBase {

	public const SLUG = 'shield/is_bot_probe_fakewebcrawler';

	protected function getName() :string {
		return 'Bot-Track Fake Web Crawler';
	}

	protected function getDescription() :string {
		return 'Track probing bots that incorrectly identify as official web crawlers.';
	}

	protected function getConditions() :array {
		return [
			'logic' => static::LOGIC_AND,
			'group' => [
				[
					'rule'         => Plugin\Rules\Build\RequestBypassesAllRestrictions::SLUG,
					'invert_match' => true
				],
				[
					'condition' => Conditions\IsNotLoggedInNormal::SLUG
				],
				[
					'condition' => Conditions\MatchRequestPath::SLUG,
					'params'    => [
						'is_match_regex' => true,
						'match_paths'    => [
							'.*'
						],
					],
				],
				[
					'condition' => Conditions\MatchRequestUseragent::SLUG,
					'params'    => [
						'match_useragents' => Services::ServiceProviders()->getAllCrawlerUseragents(),
					],
				],
			]
		];
	}

	protected function getResponses() :array {
		/** @var IPs\Options $opts */
		$opts = $this->getOptions();
		return [
			[
				'response' => Responses\EventFire::SLUG,
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