<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Plugin\Lib\MeterAnalysis;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component\{
	Base,
	ScanResultsApc,
	ScanResultsMal,
	ScanResultsPtg,
	ScanResultsWcf,
	ScanResultsWpv,
	SystemPhpVersion,
	WpPluginsInactive,
	WpPluginsUpdates,
	WpThemesInactive,
	WpThemesUpdates,
	WpUpdates
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class ComponentChannelClassificationTest extends BaseUnitTest {

	public function test_maintenance_components_are_action_channel() :void {
		$this->assertSame( Base::CHANNEL_ACTION, ( new WpUpdates() )->channel() );
		$this->assertSame( Base::CHANNEL_ACTION, ( new WpPluginsUpdates() )->channel() );
		$this->assertSame( Base::CHANNEL_ACTION, ( new WpThemesUpdates() )->channel() );
		$this->assertSame( Base::CHANNEL_ACTION, ( new WpPluginsInactive() )->channel() );
		$this->assertSame( Base::CHANNEL_ACTION, ( new WpThemesInactive() )->channel() );
	}

	public function test_scan_result_components_are_action_channel() :void {
		$this->assertSame( Base::CHANNEL_ACTION, ( new ScanResultsWcf() )->channel() );
		$this->assertSame( Base::CHANNEL_ACTION, ( new ScanResultsWpv() )->channel() );
		$this->assertSame( Base::CHANNEL_ACTION, ( new ScanResultsMal() )->channel() );
		$this->assertSame( Base::CHANNEL_ACTION, ( new ScanResultsPtg() )->channel() );
		$this->assertSame( Base::CHANNEL_ACTION, ( new ScanResultsApc() )->channel() );
	}

	public function test_non_action_component_defaults_to_config_channel() :void {
		$this->assertSame( Base::CHANNEL_CONFIG, ( new SystemPhpVersion() )->channel() );
	}
}

