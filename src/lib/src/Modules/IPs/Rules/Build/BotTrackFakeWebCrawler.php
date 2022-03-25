<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\BuildRuleCoreShieldBase,
	Conditions,
	Responses
};
use FernleafSystems\Wordpress\Services\Services;

class BotTrackFakeWebCrawler extends BuildRuleCoreShieldBase {

	protected function getName() :string {
		return 'Bot-Track Fake Web Crawler';
	}

	protected function getDescription() :string {
		return 'Track probing bots that incorrectly identify as official web crawlers.';
	}

	protected function getSlug() :string {
		return 'shield/is_bot_probe_fakewebcrawler';
	}

	protected function getConditions() :array {
		return [
			'logic' => static::LOGIC_AND,
			'group' => [
				[
					'action' => Conditions\IsNotLoggedInNormal::SLUG
				],
				[
					'action'       => Conditions\MatchOtherCondition::SLUG,
					'invert_match' => true,
					'params'       => [
						'other_condition_slug' => 'shield/is_trusted_bot',
					],
				],
				[
					'action' => Conditions\MatchRequestUseragent::SLUG,
					'params' => [
						'match_useragents' => Services::ServiceProviders()->getAllCrawlerUseragents(),
					],
				],
			]
		];
	}

	protected function getResponses() :array {
		/** @var Shield\Modules\IPs\Options $opts */
		$opts = $this->getOptions();
		return [
			[
				'action' => Responses\EventFire::SLUG,
				'params' => [
					'event'            => 'bottrack_fakewebcrawler',
					'offense_count'    => $opts->getOffenseCountFor( 'track_fakewebcrawler' ),
					'block'            => $opts->isTrackOptImmediateBlock( 'track_fakewebcrawler' ),
					'audit_params_map' => $this->getCommonAuditParamsMapping(),
				],
			],
		];
	}
}