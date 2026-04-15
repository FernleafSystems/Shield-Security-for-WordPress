<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component\LoginHide;

class LoginHideStatusTest extends BaseUnitTest {

	public function test_login_hide_status_is_neutral_and_option_independent() :void {
		$component = new LoginHide();

		$this->assertSame( EnumEnabledStatus::NEUTRAL, $component->enabledStatus() );
		$this->assertSame( [], $component->explanation() );
	}
}
