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

	public function testDocsActionsAreNotRegistered() :void {
		$this->assertNotContains(
			'FernleafSystems\\Wordpress\\Plugin\\Shield\\ActionRouter\\Actions\\Render\\PluginAdminPages\\PageDocs',
			Constants::ACTIONS
		);
		$this->assertNotContains(
			'FernleafSystems\\Wordpress\\Plugin\\Shield\\ActionRouter\\Actions\\Render\\Components\\Docs\\Changelog',
			Constants::ACTIONS
		);
		$this->assertNotContains(
			'FernleafSystems\\Wordpress\\Plugin\\Shield\\ActionRouter\\Actions\\Render\\Components\\Docs\\EventsEnum',
			Constants::ACTIONS
		);
	}

	public function testLegacyMeterActionsAreNotRegistered() :void {
		$this->assertNotContains(
			'FernleafSystems\\Wordpress\\Plugin\\Shield\\ActionRouter\\Actions\\Render\\PluginAdminPages\\PageDashboardMeters',
			Constants::ACTIONS
		);
		$this->assertNotContains(
			'FernleafSystems\\Wordpress\\Plugin\\Shield\\ActionRouter\\Actions\\Render\\Components\\Meters\\ProgressMeters',
			Constants::ACTIONS
		);
		$this->assertNotContains(
			'FernleafSystems\\Wordpress\\Plugin\\Shield\\ActionRouter\\Actions\\Render\\Components\\OffCanvas\\MeterAnalysis',
			Constants::ACTIONS
		);
	}

	public function testSecurityOverviewViewAsActionIsNotRegistered() :void {
		$this->assertNotContains(
			'FernleafSystems\\Wordpress\\Plugin\\Shield\\ActionRouter\\Actions\\SecurityOverviewViewAs',
			Constants::ACTIONS
		);
	}
}
