<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\FullPageDisplay\{
	DisplayBlockPage,
	FullPageDisplayDynamic,
	FullPageDisplayNonTerminating
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Block\{
	BlockAuthorFishing,
	BlockTrafficRateLimitExceeded
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Mfa\{
	ShieldLoginIntentPage,
	WpReplicaLoginIntentPage
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\MainWP\TabManageSitePage;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Report\{
	SecurityReport,
	SecurityReportAlert
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\OverviewTraffic;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class FullPageDisplayTargetPolicyTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
	}

	public function test_dynamic_display_allows_only_mfa_and_mainwp_targets() :void {
		$this->assertFullPageTargetAllowed( new FullPageDisplayDynamicTargetPolicyTestDouble( [
			'render_slug' => ShieldLoginIntentPage::SLUG,
		] ) );
		$this->assertFullPageTargetAllowed( new FullPageDisplayDynamicTargetPolicyTestDouble( [
			'render_slug' => WpReplicaLoginIntentPage::class,
		] ) );
		$this->assertFullPageTargetAllowed( new FullPageDisplayDynamicTargetPolicyTestDouble( [
			'render_slug' => TabManageSitePage::SLUG,
		] ) );

		$this->assertFullPageTargetRejected( new FullPageDisplayDynamicTargetPolicyTestDouble( [
			'render_slug' => OverviewTraffic::SLUG,
		] ) );
	}

	public function test_block_display_allows_only_public_block_targets() :void {
		$this->assertFullPageTargetAllowed( new DisplayBlockPageTargetPolicyTestDouble( [
			'render_slug' => BlockTrafficRateLimitExceeded::SLUG,
		] ) );
		$this->assertFullPageTargetAllowed( new DisplayBlockPageTargetPolicyTestDouble( [
			'render_slug' => BlockAuthorFishing::class,
		] ) );

		$this->assertFullPageTargetRejected( new DisplayBlockPageTargetPolicyTestDouble( [
			'render_slug' => ShieldLoginIntentPage::SLUG,
		] ) );
	}

	public function test_non_terminating_display_allows_only_report_renderers() :void {
		$this->assertFullPageTargetAllowed( new FullPageDisplayNonTerminatingTargetPolicyTestDouble( [
			'render_slug' => SecurityReport::SLUG,
		] ) );
		$this->assertFullPageTargetAllowed( new FullPageDisplayNonTerminatingTargetPolicyTestDouble( [
			'render_slug' => SecurityReportAlert::class,
		] ) );

		$this->assertFullPageTargetRejected( new FullPageDisplayNonTerminatingTargetPolicyTestDouble( [
			'render_slug' => ShieldLoginIntentPage::SLUG,
		] ) );
	}

	private function assertFullPageTargetAllowed( FullPageTargetPolicyTestSubject $subject ) :void {
		$subject->checkAvailableDataForTest();
		$this->addToAssertionCount( 1 );
	}

	private function assertFullPageTargetRejected( FullPageTargetPolicyTestSubject $subject ) :void {
		try {
			$subject->checkAvailableDataForTest();
			$this->fail( 'Expected full-page render target to be rejected.' );
		}
		catch ( ActionException $e ) {
			$this->assertNotSame( '', $e->getMessage() );
		}
	}
}

interface FullPageTargetPolicyTestSubject {

	public function checkAvailableDataForTest() :void;
}

class FullPageDisplayDynamicTargetPolicyTestDouble extends FullPageDisplayDynamic implements FullPageTargetPolicyTestSubject {

	public function checkAvailableDataForTest() :void {
		$this->checkAvailableData();
	}

	protected function exec() {
	}
}

class DisplayBlockPageTargetPolicyTestDouble extends DisplayBlockPage implements FullPageTargetPolicyTestSubject {

	public function checkAvailableDataForTest() :void {
		$this->checkAvailableData();
	}

	protected function exec() {
	}
}

class FullPageDisplayNonTerminatingTargetPolicyTestDouble extends FullPageDisplayNonTerminating implements FullPageTargetPolicyTestSubject {

	public function checkAvailableDataForTest() :void {
		$this->checkAvailableData();
	}

	protected function exec() {
	}
}
