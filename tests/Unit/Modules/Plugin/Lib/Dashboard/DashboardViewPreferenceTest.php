<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Plugin\Lib\Dashboard;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Dashboard\DashboardViewPreference;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class DashboardViewPreferenceTest extends BaseUnitTest {

	public function test_unset_meta_returns_simple() :void {
		Functions\when( 'get_current_user_id' )->justReturn( 11 );
		Functions\when( 'get_user_meta' )->justReturn( '' );

		$service = new DashboardViewPreference();
		$this->assertSame( DashboardViewPreference::VIEW_SIMPLE, $service->getCurrent() );
	}

	public function test_invalid_meta_returns_simple() :void {
		Functions\when( 'get_current_user_id' )->justReturn( 12 );
		Functions\when( 'get_user_meta' )->justReturn( 'invalid-value' );

		$service = new DashboardViewPreference();
		$this->assertSame( DashboardViewPreference::VIEW_SIMPLE, $service->getCurrent() );
	}

	public function test_advanced_value_preserved() :void {
		Functions\when( 'get_current_user_id' )->justReturn( 13 );
		Functions\when( 'get_user_meta' )->justReturn( DashboardViewPreference::VIEW_ADVANCED );

		$service = new DashboardViewPreference();
		$this->assertSame( DashboardViewPreference::VIEW_ADVANCED, $service->getCurrent() );
	}

	public function test_toggle_target_resolution_works() :void {
		Functions\when( 'get_current_user_id' )->justReturn( 14 );
		Functions\when( 'get_user_meta' )->alias( function () {
			static $calls = 0;
			$calls++;
			return $calls === 1 ? DashboardViewPreference::VIEW_SIMPLE : DashboardViewPreference::VIEW_ADVANCED;
		} );

		$service = new DashboardViewPreference();
		$this->assertSame( DashboardViewPreference::VIEW_ADVANCED, $service->getToggleTarget() );
		$this->assertSame( DashboardViewPreference::VIEW_SIMPLE, $service->getToggleTarget() );
	}

	public function test_set_current_persists_advanced_value() :void {
		Functions\when( 'get_current_user_id' )->justReturn( 15 );
		Functions\expect( 'update_user_meta' )
			->once()
			->with(
				15,
				DashboardViewPreference::META_KEY,
				DashboardViewPreference::VIEW_ADVANCED
			)->andReturn( true );

		( new DashboardViewPreference() )->setCurrent( DashboardViewPreference::VIEW_ADVANCED );
		$this->addToAssertionCount( 1 );
	}

	public function test_set_current_persists_only_allowed_values() :void {
		Functions\when( 'get_current_user_id' )->justReturn( 16 );
		Functions\expect( 'update_user_meta' )
			->once()
			->with(
				16,
				DashboardViewPreference::META_KEY,
				DashboardViewPreference::VIEW_SIMPLE
			)->andReturn( true );

		( new DashboardViewPreference() )->setCurrent( 'not-allowed' );
		$this->addToAssertionCount( 1 );
	}
}
