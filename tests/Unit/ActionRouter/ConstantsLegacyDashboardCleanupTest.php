<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class ConstantsLegacyDashboardCleanupTest extends BaseUnitTest {

	public function testLegacyDashboardToggleActionIsNotRegistered() :void {
		$legacyActionClass = 'FernleafSystems\\Wordpress\\Plugin\\Shield\\ActionRouter\\Actions\\Dashboard'
							 . 'ViewToggle';

		$this->assertNotContains(
			$legacyActionClass,
			Constants::ACTIONS
		);
	}

	public function testOperatorModeSwitchActionRemainsRegistered() :void {
		$this->assertContains(
			'FernleafSystems\\Wordpress\\Plugin\\Shield\\ActionRouter\\Actions\\OperatorModeSwitch',
			Constants::ACTIONS
		);
	}
}
