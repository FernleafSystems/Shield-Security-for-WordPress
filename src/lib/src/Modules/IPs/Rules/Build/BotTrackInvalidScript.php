<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Conditions,
	Enum\EnumLogic,
	Enum\EnumMatchTypes,
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
					'conditions' => Conditions\MatchRequestScriptName::class,
					'logic'      => EnumLogic::LOGIC_INVERT,
					'params'     => [
						'match_type'        => EnumMatchTypes::MATCH_TYPE_REGEX,
						'match_script_name' => sprintf( '(%s)',
							implode( '|', \array_map( function ( $script ) {
								return \preg_quote( $script, '#' );
							}, $this->opts()->botSignalsGetAllowableScripts() ) )
						),
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