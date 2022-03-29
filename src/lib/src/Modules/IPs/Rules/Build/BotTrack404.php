<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\BuildRuleCoreShieldBase,
	Conditions,
	Responses
};

class BotTrack404 extends BuildRuleCoreShieldBase {

	protected function getName() :string {
		return 'Bot-Track 404';
	}

	protected function getDescription() :string {
		return 'Tracking HTTP 404 errors by bots probing a site';
	}

	protected function getSlug() :string {
		return 'shield/is_bot_probe_404';
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
					'action' => Conditions\IsNotLoggedInNormal::SLUG
				],
				[
					'action' => Conditions\MatchRequestStatusCode::SLUG,
					'params' => [
						'code' => '404',
					],
				],
				[
					'logic' => static::LOGIC_OR,
					'group' => [
						[
							'action' => Conditions\NotMatchRequestPath::SLUG,
							'params' => [
								'is_match_regex' => true,
								'match_paths'    => [
									sprintf( "\\.(%s)$", implode( '|', $this->getAllowableExtensions() ) )
								],
							],
						],
						[
							'action' => Conditions\IsRequestToInvalidPlugin::SLUG,
						],
						[
							'action' => Conditions\IsRequestToInvalidTheme::SLUG,
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
				'action' => Responses\EventFire::SLUG,
				'params' => [
					'event'            => 'bottrack_404',
					'offense_count'    => $opts->getOffenseCountFor( 'track_404' ),
					'block'            => $opts->isTrackOptImmediateBlock( 'track_404' ),
					'audit_params_map' => $this->getCommonAuditParamsMapping(),
				],
			],
		];
	}

	private function getAllowableExtensions() :array {
		$defExt = $this->getOptions()->getDef( 'allowable_ext_404s' );
		$extensions = apply_filters( 'shield/allowable_extensions_404s', $defExt );
		return is_array( $extensions ) ? array_filter( $extensions ) : $defExt;
	}
}