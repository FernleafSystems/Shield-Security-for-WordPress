<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Plugin\Lib\MeterAnalysis;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\{
	Component,
	Components,
	Meter\MeterSummary
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class MeterSummaryComponentCoverageTest extends BaseUnitTest {

	public function test_action_channel_components_are_listed_in_summary() :void {
		$components = ( new MeterSummaryTestDouble() )->listedComponents();
		$expectedActionComponents = [
			Component\WpUpdates::class,
			Component\WpPluginsUpdates::class,
			Component\WpPluginsInactive::class,
			Component\WpThemesUpdates::class,
			Component\WpThemesInactive::class,
			Component\ScanResultsMal::class,
			Component\ScanResultsWpv::class,
			Component\ScanResultsWcf::class,
			Component\ScanResultsPtg::class,
			Component\ScanResultsApc::class,
		];

		foreach ( $expectedActionComponents as $componentClass ) {
			$this->assertContains( $componentClass, $components );
		}
	}

	public function test_listed_summary_components_are_registered() :void {
		$components = ( new MeterSummaryTestDouble() )->listedComponents();
		foreach ( $components as $componentClass ) {
			$this->assertContains( $componentClass, Components::COMPONENTS );
		}
	}
}

class MeterSummaryTestDouble extends MeterSummary {

	public function listedComponents() :array {
		return $this->getComponents();
	}
}
