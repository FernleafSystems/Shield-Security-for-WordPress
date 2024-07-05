<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Build\Core;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Conditions,
	Enum,
	Responses
};

class BotTrack404 extends BuildRuleIpsBase {

	public const SLUG = 'shield/is_bot_probe_404';

	protected function getName() :string {
		return 'Bot-Track 404';
	}

	protected function getDescription() :string {
		return 'Tracking HTTP 404 errors by bots probing a site';
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
					'conditions' => Conditions\IsRequestStatus404::class,
				],
				[
					'conditions' => Conditions\ShieldConfigurationOption::class,
					'logic'      => Enum\EnumLogic::LOGIC_INVERT,
					'params'     => [
						'name'        => 'track_404',
						'match_type'  => Enum\EnumMatchTypes::MATCH_TYPE_EQUALS,
						'match_value' => 'disabled',
					]
				],
				[
					'logic'      => Enum\EnumLogic::LOGIC_OR,
					'conditions' => [
						[
							'conditions' => Conditions\MatchRequestPath::class,
							'logic'      => Enum\EnumLogic::LOGIC_INVERT,
							'params'     => [
								'match_type' => Enum\EnumMatchTypes::MATCH_TYPE_REGEX,
								'match_path' => sprintf( "#\\.(%s)$#i", \implode( '|', self::con()->comps->bot_signals->getAllowable404s() ) ),
							],
						],
						[
							'conditions' => Conditions\IsRequestToInvalidPlugin::class,
						],
						[
							'conditions' => Conditions\IsRequestToInvalidTheme::class,
						],
					]
				]
			]
		];
	}

	protected function getResponses() :array {
		return [
			[
				'response' => Responses\EventFire::class,
				'params'   => [
					'event'            => 'bottrack_404',
					'offense_count'    => self::con()->comps->opts_lookup->getBotTrackOffenseCountFor( 'track_404' ),
					'block'            => self::con()->comps->opts_lookup->isBotTrackImmediateBlock( 'track_404' ),
					'audit_params_map' => $this->getCommonAuditParamsMapping(),
				],
			],
		];
	}
}