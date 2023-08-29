<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\BuildRuleCoreShieldBase,
	Conditions,
	Responses
};

class BotTrack404 extends BuildRuleCoreShieldBase {

	public const SLUG = 'shield/is_bot_probe_404';

	protected function getName() :string {
		return 'Bot-Track 404';
	}

	protected function getDescription() :string {
		return 'Tracking HTTP 404 errors by bots probing a site';
	}

	protected function getConditions() :array {
		/** @var Shield\Modules\IPs\Options $opts */
		$opts = self::con()->getModule_IPs()->getOptions();
		return [
			'logic' => static::LOGIC_AND,
			'group' => [
				[
					'rule'         => Shield\Modules\Plugin\Rules\Build\RequestBypassesAllRestrictions::SLUG,
					'invert_match' => true
				],
				[
					'condition' => Conditions\IsNotLoggedInNormal::SLUG
				],
				[
					'condition' => Conditions\MatchRequestStatusCode::SLUG,
					'params'    => [
						'code' => '404',
					],
				],
				[
					'logic' => static::LOGIC_OR,
					'group' => [
						[
							'condition' => Conditions\NotMatchRequestPath::SLUG,
							'params'    => [
								'is_match_regex' => true,
								'match_paths'    => [
									sprintf( "\\.(%s)$", \implode( '|', $opts->botSignalsGetAllowable404s() ) )
								],
							],
						],
						[
							'condition' => Conditions\IsRequestToInvalidPlugin::SLUG,
						],
						[
							'condition' => Conditions\IsRequestToInvalidTheme::SLUG,
						],
					]
				]
			]
		];
	}

	protected function getResponses() :array {
		/** @var Shield\Modules\IPs\Options $opts */
		$opts = $this->getOptions();
		return [
			[
				'response' => Responses\EventFire::SLUG,
				'params'   => [
					'event'            => 'bottrack_404',
					'offense_count'    => $opts->getOffenseCountFor( 'track_404' ),
					'block'            => $opts->isTrackOptImmediateBlock( 'track_404' ),
					'audit_params_map' => $this->getCommonAuditParamsMapping(),
				],
			],
		];
	}
}