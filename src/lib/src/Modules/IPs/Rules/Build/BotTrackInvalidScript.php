<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Conditions,
	Constants,
	Responses
};

class BotTrackInvalidScript extends BuildRuleIpsBase {

	public const SLUG = 'shield/is_bot_probe_invalidscript';

	protected function getName() :string {
		return 'Bot-Track Invalid Script';
	}

	protected function getDescription() :string {
		return 'Track probing bots that send requests to invalid scripts.';
	}

	protected function getConditions() :array {
		return [
			'logic' => Constants::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => Conditions\RequestBypassesAllRestrictions::class,
					'logic'      => Constants::LOGIC_INVERT
				],
				[
					'conditions' => Conditions\IsNotLoggedInNormal::class
				],
				[
					'conditions' => Conditions\MatchRequestScriptNames::class,
					'logic'      => Constants::LOGIC_INVERT,
					'params'       => [
						'is_match_regex'     => false,
						'match_script_names' => $this->opts()->botSignalsGetAllowableScripts(),
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
					'event'            => 'bottrack_invalidscript',
					'offense_count'    => $this->opts()->getOffenseCountFor( 'track_invalidscript' ),
					'block'            => $this->opts()->isTrackOptImmediateBlock( 'track_invalidscript' ),
					'audit_params_map' => $this->getCommonAuditParamsMapping(),
				],
			],
		];
	}
}