<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Build\Core;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Conditions,
	Enum,
	Responses
};
use FernleafSystems\Wordpress\Services\Services;

class BotTrackFakeWebCrawler extends BuildRuleIpsBase {
	public const SLUG = 'shield/is_bot_probe_fakewebcrawler';

	protected function getName(): string {
		return 'Bot-Track Fake Web Crawler';
	}

	protected function getDescription(): string {
		return 'Track probing bots that incorrectly identify as official web crawlers.';
	}

	protected function getConditions(): array {
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
					'logic'      => Enum\EnumLogic::LOGIC_INVERT,
					'params'     => [
						'name'        => 'track_fakewebcrawler',
						'match_type'  => Enum\EnumMatchTypes::MATCH_TYPE_EQUALS,
						'match_value' => 'disabled',
					]
				],
				[
					'logic'      => Enum\EnumLogic::LOGIC_OR,
					'conditions' => $this->buildCrawlerUserAgentConditions(),
				]
			]
		];
	}

	private function buildCrawlerUserAgentConditions(): array {
		$conditions = [];
		foreach ( Services::ServiceProviders()->getProviders()[ 'crawlers' ] ?? [] as $crawler ) {
			$conditions = \array_merge( $conditions, $this->buildCrawlerUserAgentConditionsForSpec( $crawler ) );
		}
		return $conditions;
	}

	private function buildCrawlerUserAgentConditionsForSpec( array $crawler ): array {
		$identityPatterns = $crawler[ 'identity_user_agent_patterns' ] ?? [];

		if ( empty( $identityPatterns ) ) {
			$conditions = \array_map(
				fn( string $agent ) => $this->buildUserAgentCondition( Enum\EnumMatchTypes::MATCH_TYPE_CONTAINS_I, $agent ),
				\array_values( \array_filter( $crawler[ 'agents' ] ?? [], '\is_string' ) )
			);
		}
		else {
			$conditions = \array_map(
				fn( string $pattern ) => $this->buildUserAgentCondition( Enum\EnumMatchTypes::MATCH_TYPE_REGEX, $pattern ),
				$identityPatterns
			);
		}
		return $conditions;
	}

	private function buildUserAgentCondition( string $matchType, string $matchUserAgent ): array {
		return [
			'conditions' => Conditions\MatchRequestUseragent::class,
			'params'     => [
				'match_type'      => $matchType,
				'match_useragent' => $matchUserAgent,
			],
		];
	}

	protected function getResponses(): array {
		return [
			[
				'response' => Responses\EventFire::class,
				'params'   => [
					'event'            => 'bottrack_fakewebcrawler',
					'offense_count'    => self::con()->comps->opts_lookup->getBotTrackOffenseCountFor( 'track_fakewebcrawler' ),
					'block'            => self::con()->comps->opts_lookup->isBotTrackImmediateBlock( 'track_fakewebcrawler' ),
					'audit_params_map' => $this->getCommonAuditParamsMapping(),
				],
			],
		];
	}
}
