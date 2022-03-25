<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Conditions,
	Responses,
	RuleVO
};

class BuildBotTrack404 extends BuildRuleBase {

	use Shield\Modules\ModConsumer;

	public function build() {
		/** @var IPs\Options $opts */
		$opts = $this->getOptions();

		$rules = new RuleVO();
		$rules->name = 'Bot-Track 404';
		$rules->description = 'Tracking HTTP 404 errors by bots probing a site.';
		$rules->slug = 'shield/is_bot_probe_404';
		$rules->flags = [
			'is_core_shield' => true
		];
		$rules->conditions = [
			'logic' => static::LOGIC_AND,
			'group' => [
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
		$rules->responses = [
			'logic' => static::LOGIC_AND,
			'group' => [
				[
					'action' => Conditions\IsNotLoggedInNormal::SLUG
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
							'action' => Responses\EventFire::SLUG,
							'params' => [
								'event'         => 'bottrack_404',
								'offense_count' => $opts->getOffenseCountFor( 'track_404' ),
								'block'         => $opts->isTrackOptImmediateBlock( 'track_404' ),
							],
						],
					]
				]
			]
		];
	}

	private function getAllowableExtensions() :array {
		$defExt = $this->getOptions()->getDef( 'allowable_ext_404s' );
		$extensions = apply_filters( 'shield/allowable_extensions_404s', $defExt );
		return is_array( $extensions ) ? array_filter( $extensions ) : $defExt;
	}
}