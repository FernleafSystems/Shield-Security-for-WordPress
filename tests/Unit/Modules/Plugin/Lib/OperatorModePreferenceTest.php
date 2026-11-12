<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Plugin\Lib;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\OperatorModePreference;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class OperatorModePreferenceTest extends BaseUnitTest {

	public function test_empty_meta_returns_empty_string() :void {
		Functions\when( 'get_current_user_id' )->justReturn( 21 );
		Functions\when( 'get_user_meta' )->justReturn( '' );

		$service = new OperatorModePreference();
		$this->assertSame( '', $service->getCurrent() );
	}

	public function test_invalid_meta_sanitizes_to_empty_string() :void {
		Functions\when( 'get_current_user_id' )->justReturn( 22 );
		Functions\when( 'get_user_meta' )->justReturn( 'bad-mode' );

		$service = new OperatorModePreference();
		$this->assertSame( '', $service->getCurrent() );
	}
}
