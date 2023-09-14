<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\BuildRuleCoreShieldBase,
	Conditions,
	Responses
};

class BotTrackXmlrpc extends BuildRuleCoreShieldBase {

	public const SLUG = 'shield/is_bot_probe_xmlrpc';

	protected function getName() :string {
		return 'Bot-Track XML-RPC';
	}

	protected function getDescription() :string {
		return 'Track probing bots that send requests to XML-RPC.';
	}

	protected function getConditions() :array {
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
					'condition' => Conditions\WpIsXmlrpc::SLUG,
				],
				[
					'condition' => Conditions\MatchRequestPath::SLUG,
					'params'    => [
						'is_match_regex' => true,
						'match_paths'    => [
							'/xmlrpc\\.php$'
						],
					],
				],
			]
		];
	}

	protected function getResponses() :array {
		/** @var Shield\Modules\IPs\Options $opts */
		$opts = $this->opts();
		return [
			[
				'response' => Responses\EventFire::SLUG,
				'params'   => [
					'event'            => 'bottrack_xmlrpc',
					'offense_count'    => $opts->getOffenseCountFor( 'track_xmlrpc' ),
					'block'            => $opts->isTrackOptImmediateBlock( 'track_xmlrpc' ),
					'audit_params_map' => $this->getCommonAuditParamsMapping(),
				],
			],
		];
	}
}