<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Build\Core;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Conditions,
	Enum,
	Responses
};

/**
 * @TODO sort out the preg_quote - do we build our own full preg strings, or wrap internally?
 */
class BotTrackInvalidScript extends BuildRuleIpsBase {

	public const SLUG = 'shield/is_bot_probe_invalidscript';

	protected function getName() :string {
		return 'Bot-Track Invalid Script';
	}

	protected function getDescription() :string {
		return 'Track probing bots that send requests to invalid scripts.';
	}

	protected function getConditions() :array {
		$botSignals = \method_exists( $this->mod(), 'getAllowableScripts' ) ?
			self::con()->getModule_IPs()->getAllowableScripts() : $this->opts()->botSignalsGetAllowableScripts();
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
						'name'        => 'track_invalidscript',
						'match_type'  => Enum\EnumMatchTypes::MATCH_TYPE_EQUALS,
						'match_value' => 'disabled',
					]
				],
				[
					'conditions' => Conditions\MatchRequestScriptName::class,
					'logic'      => Enum\EnumLogic::LOGIC_INVERT,
					'params'     => [
						'match_type'        => Enum\EnumMatchTypes::MATCH_TYPE_REGEX,
						'match_script_name' => sprintf( '#(%s)#i',
							implode( '|', \array_map( function ( $script ) {
								return \preg_quote( $script, '#' );
							}, $botSignals ) )
						),
					],
				],
			]
		];
	}

	protected function getResponses() :array {
		if ( self::con()->comps === null ) {
			$count = $this->opts()->getOffenseCountFor( 'track_invalidscript' );
			$block = $this->opts()->isTrackOptImmediateBlock( 'track_invalidscript' );
		}
		else {
			$count = self::con()->comps->opts_lookup->getBotTrackOffenseCountFor( 'track_invalidscript' );
			$block = self::con()->comps->opts_lookup->isBotTrackImmediateBlock( 'track_invalidscript' );
		}

		return [
			[
				'response' => Responses\EventFire::class,
				'params'   => [
					'event'            => 'bottrack_invalidscript',
					'offense_count'    => $count,
					'block'            => $block,
					'audit_params_map' => $this->getCommonAuditParamsMapping(),
				],
			],
		];
	}
}