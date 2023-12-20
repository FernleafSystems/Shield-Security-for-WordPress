<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Conditions,
	Enum\EnumLogic,
	Enum\EnumMatchTypes,
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
		$opts = $this->opts();
		return [
			'logic'      => EnumLogic::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => Conditions\RequestBypassesAllRestrictions::class,
					'logic'      => EnumLogic::LOGIC_INVERT
				],
				[
					'conditions' => Conditions\IsNotLoggedInNormal::class
				],
				[
					'conditions' => Conditions\IsRequestStatus404::class,
				],
				[
					'logic'      => EnumLogic::LOGIC_OR,
					'conditions' => [
						[
							'conditions' => Conditions\MatchRequestPath::class,
							'logic'      => EnumLogic::LOGIC_INVERT,
							'params'     => [
								'match_type' => EnumMatchTypes::MATCH_TYPE_REGEX,
								'match_path' => sprintf( "\\.(%s)$", \implode( '|', $opts->botSignalsGetAllowable404s() ) ),
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
		$opts = $this->opts();
		return [
			[
				'response' => Responses\EventFire::class,
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