<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Utilities\Tool;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\StatusPriority;

class StatusPriorityTest extends BaseUnitTest {

	public function test_highest_prefers_warning_over_good() :void {
		$this->assertSame( 'warning', StatusPriority::highest( [ 'good', 'warning' ] ) );
	}

	public function test_highest_prefers_critical_over_good() :void {
		$this->assertSame( 'critical', StatusPriority::highest( [ 'good', 'critical' ] ) );
	}

	public function test_highest_empty_returns_info_default() :void {
		$this->assertSame( 'info', StatusPriority::highest( [], 'info' ) );
	}

	public function test_unknown_statuses_are_ignored_and_default_is_stable() :void {
		$this->assertSame( 'warning', StatusPriority::highest( [ 'mystery', 'unknown' ], 'warning' ) );
	}

	public function test_invalid_default_falls_back_to_info() :void {
		$this->assertSame( 'info', StatusPriority::highest( [], 'not-valid' ) );
	}
}
