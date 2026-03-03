<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Zones\Common;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class EnumEnabledStatusTest extends BaseUnitTest {

	public function test_to_severity_maps_known_statuses() :void {
		$this->assertSame( 'critical', EnumEnabledStatus::toSeverity( EnumEnabledStatus::BAD ) );
		$this->assertSame( 'warning', EnumEnabledStatus::toSeverity( EnumEnabledStatus::OKAY ) );
		$this->assertSame( 'warning', EnumEnabledStatus::toSeverity( EnumEnabledStatus::NEUTRAL ) );
		$this->assertSame( 'good', EnumEnabledStatus::toSeverity( EnumEnabledStatus::GOOD ) );
		$this->assertSame( 'good', EnumEnabledStatus::toSeverity( EnumEnabledStatus::NEUTRAL_ENABLED ) );
	}

	public function test_to_severity_uses_valid_default_for_unknown_statuses() :void {
		$this->assertSame( 'warning', EnumEnabledStatus::toSeverity( 'unknown', 'warning' ) );
		$this->assertSame( 'good', EnumEnabledStatus::toSeverity( 'unknown', 'invalid-default' ) );
	}
}
