<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Meter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\{
	Component\AllComponents,
	Components
};

/**
 * Not actually displayed to the user, but used by Component\AllComponents to build an overall config score.
 */
class MeterOverallConfig extends MeterBase {

	public const SLUG = 'overall_config';

	public function title() :string {
		return sprintf( __( 'Entire %s Configuration', 'wp-simple-firewall' ), self::con()->labels->Name );
	}

	public function subtitle() :string {
		return sprintf( __( 'The cumulative score for your entire %s configuration', 'wp-simple-firewall' ), self::con()->labels->Name );
	}

	public function description() :array {
		return [
			__( "This combines all the security configuration components below and provides you with an overall configuration score.", 'wp-simple-firewall' ),
			__( "We recommend using the individual sections below to work through your site security items.", 'wp-simple-firewall' ),
		];
	}

	protected function getComponents() :array {
		return \array_diff( Components::COMPONENTS, [ AllComponents::class ] );
	}
}