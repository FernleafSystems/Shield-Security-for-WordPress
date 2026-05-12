<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Utilities\Tool;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\StatusPriority;

class StatusPriorityTest extends BaseUnitTest {

	public function test_normalize_trims_and_lowercases_valid_status() :void {
		$this->assertSame( 'critical', StatusPriority::normalize( '  CRITICAL ' ) );
	}

	public function test_normalize_uses_default_and_falls_back_to_info() :void {
		$this->assertSame( 'warning', StatusPriority::normalize( 'unknown', 'warning' ) );
		$this->assertSame( 'warning', StatusPriority::normalize( 'unknown', ' WARNING ' ) );
		$this->assertSame( 'info', StatusPriority::normalize( 'unknown', 'invalid-default' ) );
	}

	public function test_rank_returns_known_value_or_unknown_rank_override() :void {
		$this->assertSame( 2, StatusPriority::rank( 'warning' ) );
		$this->assertSame( 99, StatusPriority::rank( 'unknown', 99 ) );
	}

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
