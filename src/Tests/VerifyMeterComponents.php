<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Components;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class VerifyMeterComponents {

	use PluginControllerConsumer;

	public function run() {

		$declaredButNotExists = [];
		foreach ( Components::COMPONENTS as $component ) {
			if ( !\class_exists( $component ) ) {
				$declaredButNotExists[] = $component;
			}
		}
		if ( !empty( $declaredButNotExists ) ) {
			var_dump( 'Meter Components classes exist, but not declared: '.\var_export( $declaredButNotExists, true ) );
		}
	}
}