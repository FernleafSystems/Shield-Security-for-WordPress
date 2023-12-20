<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Conditions,
	Enum\EnumLogic,
	Enum\EnumMatchTypes,
	Responses
};

class BotTrackXmlrpc extends BuildRuleIpsBase {

	public const SLUG = 'shield/is_bot_probe_xmlrpc';

	protected function getName() :string {
		return 'Bot-Track XML-RPC';
	}

	protected function getDescription() :string {
		return 'Track probing bots that send requests to XML-RPC.';
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
					'conditions' => Conditions\WpIsXmlrpc::class,
				],
				[
					'conditions' => Conditions\MatchRequestPath::class,
					'params'     => [
						'match_type' => EnumMatchTypes::MATCH_TYPE_REGEX,
						'match_path' => '/xmlrpc\\.php$',
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
					'event'            => 'bottrack_xmlrpc',
					'offense_count'    => $this->opts()->getOffenseCountFor( 'track_xmlrpc' ),
					'block'            => $this->opts()->isTrackOptImmediateBlock( 'track_xmlrpc' ),
					'audit_params_map' => $this->getCommonAuditParamsMapping(),
				],
			],
		];
	}
}