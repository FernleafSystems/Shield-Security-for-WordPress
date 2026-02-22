<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\{
	PageActionsQueueLanding,
	PageConfigureLanding,
	PageInvestigateLanding,
	PageModeLandingBase,
	PageReportsLanding
};
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class ModeLandingInheritanceTest extends TestCase {

	public function test_mode_landing_pages_extend_shared_mode_landing_base() :void {
		$this->assertTrue( \is_subclass_of( PageActionsQueueLanding::class, PageModeLandingBase::class ) );
		$this->assertTrue( \is_subclass_of( PageConfigureLanding::class, PageModeLandingBase::class ) );
		$this->assertTrue( \is_subclass_of( PageInvestigateLanding::class, PageModeLandingBase::class ) );
		$this->assertTrue( \is_subclass_of( PageReportsLanding::class, PageModeLandingBase::class ) );
	}
}
