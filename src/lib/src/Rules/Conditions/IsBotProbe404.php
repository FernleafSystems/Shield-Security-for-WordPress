<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Options;

class IsBotProbe404 extends Base {

	const SLUG = 'is_bot_probe_404';

	protected function execConditionCheck() :bool {
		$match = false;

		$is404Handler = ( new Is404() )->setCon( $this->getCon() );
		if ( $is404Handler->run() ) {

			// if the request's file extension is allowed to trigger 404s, we fire only the event, without transgression.
			// However, if the requested asset is within a plugin or theme that doesn't exist, it's not allowed.
			$pathMatcher = ( new NotMatchRequestPath() )->setCon( $this->getCon() );
			$pathMatcher->is_match_regex = true;
			$pathMatcher->match_paths = [
				sprintf( '\.(%s)$', implode( '|', $this->getAllowableExtensions() ) )
			];
			$match = $pathMatcher->run()
					 || ( new IsRequestToInvalidPlugin() )->setCon( $this->getCon() )->run()
					 || ( new IsRequestToInvalidTheme() )->setCon( $this->getCon() )->run();

			if ( $match ) {
				$this->conditionTriggerMeta = $is404Handler->getConditionTriggerMetaData();
				/** @var Options $opts */
				$opts = $this->getCon()->getModule_IPs()->getOptions();
				$this->addConditionTriggerMeta( 'offense_count', $opts->getOffenseCountFor( 'track_404' ) );
			}
		}
		return $match;
	}

	public static function RequiredConditions() :array {
		return [
			Is404::class,
			NotMatchRequestPath::class,
			IsNotLoggedInNormal::class,
			IsRequestToInvalidPlugin::class,
			IsRequestToInvalidTheme::class,
		];
	}

	private function getAllowableExtensions() :array {
		$defExts = $this->getCon()->getModule_IPs()->getOptions()->getDef( 'allowable_ext_404s' );
		$extensions = apply_filters( 'shield/allowable_extensions_404s', $defExts );
		return is_array( $extensions ) ? $extensions : $defExts;
	}
}