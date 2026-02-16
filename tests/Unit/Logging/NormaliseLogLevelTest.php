<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Logging;

use FernleafSystems\Wordpress\Plugin\Shield\Logging\NormaliseLogLevel;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class NormaliseLogLevelTest extends BaseUnitTest {

	public function test_event_level_mapping_and_fallback() :void {
		$this->assertSame( 'warning', NormaliseLogLevel::forEvent( 'warning' ) );
		$this->assertSame( 'notice', NormaliseLogLevel::forEvent( 'notice' ) );
		$this->assertSame( 'info', NormaliseLogLevel::forEvent( 'info' ) );
		$this->assertSame( 'warning', NormaliseLogLevel::forEvent( 'alert' ) );
		$this->assertSame( 'info', NormaliseLogLevel::forEvent( 'debug' ) );
		$this->assertSame( 'notice', NormaliseLogLevel::forEvent( 'unknown' ) );
	}

	public function test_db_selection_maps_legacy_and_deduplicates() :void {
		$this->assertSame(
			[ 'warning', 'notice', 'info' ],
			NormaliseLogLevel::forDbSelection( [ 'alert', 'notice', 'debug', 'info', 'alert' ] )
		);
	}

	public function test_db_selection_disabled_is_exclusive() :void {
		$this->assertSame(
			[ 'disabled' ],
			NormaliseLogLevel::forDbSelection( [ 'disabled', 'warning', 'notice', 'info' ] )
		);
	}

	public function test_db_selection_accepts_scalar_values() :void {
		$this->assertSame( [ 'disabled' ], NormaliseLogLevel::forDbSelection( 'disabled' ) );
		$this->assertSame( [ 'warning' ], NormaliseLogLevel::forDbSelection( 'alert' ) );
	}
}
