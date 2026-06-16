<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionRoutingController,
	Actions\AjaxRender,
	Actions\FullPageDisplay\DisplayBlockPage,
	Actions\FullPageDisplay\DisplayReport,
	Actions\FullPageDisplay\DisplayReportAdmin,
	Actions\FullPageDisplay\FullPageDisplayDynamic,
	Actions\FullPageDisplay\FullPageDisplayNonTerminating,
	Actions\OperatorModeSwitch,
	Actions\PluginImportExport_NetworkInviteRequest,
	Actions\Render,
	Actions\Render\Components\Reports\Components\ReportAreaChanges,
	Actions\Render\Components\Scans\Results\Wordpress,
	Actions\Render\Components\ToastPlaceholder,
	Actions\Render\FullPage\Block\BlockFirewall,
	Actions\Render\FullPage\Block\BlockTrafficRateLimitExceeded,
	Actions\Render\FullPage\Mfa\Components\LoginIntentFormShield,
	Actions\Render\FullPage\Mfa\ShieldLoginIntentPage,
	Actions\Render\FullPage\Report\SecurityReport,
	Actions\TestRestFetchRequests,
	Utility\ExternalActionTransportPolicy
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class ExternalActionTransportPolicyTest extends BaseUnitTest {

	private static bool $isAdminContext = false;

	protected function setUp() :void {
		parent::setUp();
		self::$isAdminContext = false;
		Functions\when( 'is_admin' )->alias( static fn() :bool => self::$isAdminContext );
		Functions\when( 'is_network_admin' )->justReturn( false );
	}

	public function test_render_is_never_allowed_from_external_transports() :void {
		$policy = new ExternalActionTransportPolicy();

		foreach ( $this->externalTransportTypes() as $type ) {
			$this->assertFalse( $policy->isAllowed( Render::SLUG, [], $type ) );
			$this->assertFalse( $policy->isAllowed( Render::class, [], $type ) );
		}
	}

	public function test_ajax_render_is_only_allowed_from_ajax_transport() :void {
		$policy = new ExternalActionTransportPolicy();

		$this->assertFalse( $policy->isAllowed( AjaxRender::SLUG, [], ActionRoutingController::ACTION_SHIELD ) );
		$this->assertTrue( $policy->isAllowed( AjaxRender::SLUG, [], ActionRoutingController::ACTION_AJAX ) );
		$this->assertFalse( $policy->isAllowed( AjaxRender::SLUG, [], ActionRoutingController::ACTION_REST ) );
	}

	public function test_block_page_transport_allows_only_public_block_render_targets_without_render_data() :void {
		$policy = new ExternalActionTransportPolicy();

		$this->assertTrue( $policy->isAllowed( DisplayBlockPage::SLUG, [
			'render_slug' => BlockTrafficRateLimitExceeded::SLUG,
		], ActionRoutingController::ACTION_SHIELD ) );
		$this->assertTrue( $policy->isAllowed( DisplayBlockPage::SLUG, [
			'render_slug' => BlockFirewall::class,
		], ActionRoutingController::ACTION_SHIELD ) );
		$this->assertFalse( $policy->isAllowed( DisplayBlockPage::SLUG, [
			'render_slug' => ShieldLoginIntentPage::SLUG,
		], ActionRoutingController::ACTION_SHIELD ) );
		$this->assertFalse( $policy->isAllowed( DisplayBlockPage::SLUG, [
			'render_slug' => BlockTrafficRateLimitExceeded::SLUG,
			'render_data' => [
				'external' => 'not-allowed',
			],
		], ActionRoutingController::ACTION_SHIELD ) );
		$this->assertFalse( $policy->isAllowed( DisplayBlockPage::SLUG, [
			'render_slug' => BlockTrafficRateLimitExceeded::SLUG,
		], ActionRoutingController::ACTION_REST ) );
	}

	public function test_dynamic_full_page_transport_is_denied_for_non_mainwp_shapes() :void {
		$policy = new ExternalActionTransportPolicy();

		$this->assertFalse( $policy->isAllowed( FullPageDisplayDynamic::SLUG, [
			'render_slug' => ShieldLoginIntentPage::SLUG,
			'render_data' => [],
		], ActionRoutingController::ACTION_SHIELD ) );
		$this->assertFalse( $policy->isAllowed( FullPageDisplayDynamic::SLUG, [
			'render_slug' => ShieldLoginIntentPage::SLUG,
			'render_data' => [],
		], ActionRoutingController::ACTION_AJAX ) );
		$this->assertFalse( $policy->isAllowed( FullPageDisplayDynamic::SLUG, [
			'render_slug' => ShieldLoginIntentPage::SLUG,
			'render_data' => [],
		], ActionRoutingController::ACTION_REST ) );
	}

	public function test_non_terminating_full_page_transport_is_never_allowed_externally() :void {
		$policy = new ExternalActionTransportPolicy();

		foreach ( $this->externalTransportTypes() as $type ) {
			$this->assertFalse( $policy->isAllowed( FullPageDisplayNonTerminating::SLUG, [], $type ) );
			$this->assertFalse( $policy->isAllowed( FullPageDisplayNonTerminating::class, [], $type ) );
		}
	}

	public function test_report_transport_is_only_allowed_from_normal_shield_transport() :void {
		$policy = new ExternalActionTransportPolicy();

		$this->assertTrue( $policy->isAllowed( DisplayReport::SLUG, [], ActionRoutingController::ACTION_SHIELD ) );
		$this->assertFalse( $policy->isAllowed( DisplayReport::SLUG, [], ActionRoutingController::ACTION_AJAX ) );
		$this->assertFalse( $policy->isAllowed( DisplayReport::SLUG, [], ActionRoutingController::ACTION_REST ) );
	}

	public function test_admin_report_transport_is_allowed_only_from_admin_shield_transport() :void {
		$policy = new ExternalActionTransportPolicy();

		self::$isAdminContext = true;
		$this->assertTrue( $policy->isAllowed( DisplayReportAdmin::SLUG, [], ActionRoutingController::ACTION_SHIELD ) );
		$this->assertFalse( $policy->isAllowed( DisplayReportAdmin::SLUG, [], ActionRoutingController::ACTION_AJAX ) );
		$this->assertFalse( $policy->isAllowed( DisplayReportAdmin::SLUG, [], ActionRoutingController::ACTION_REST ) );

		self::$isAdminContext = false;
		$this->assertFalse( $policy->isAllowed( DisplayReportAdmin::SLUG, [], ActionRoutingController::ACTION_SHIELD ) );
	}

	public function test_network_invite_request_transport_is_only_allowed_from_normal_shield_transport() :void {
		$policy = new ExternalActionTransportPolicy();

		$this->assertTrue( $policy->isAllowed(
			PluginImportExport_NetworkInviteRequest::SLUG,
			[],
			ActionRoutingController::ACTION_SHIELD
		) );
		$this->assertFalse( $policy->isAllowed(
			PluginImportExport_NetworkInviteRequest::SLUG,
			[],
			ActionRoutingController::ACTION_AJAX
		) );
		$this->assertFalse( $policy->isAllowed(
			PluginImportExport_NetworkInviteRequest::SLUG,
			[],
			ActionRoutingController::ACTION_REST
		) );
	}

	/**
	 * @dataProvider directRenderActionProvider
	 */
	public function test_direct_render_actions_are_denied_from_external_transports( string $actionClass ) :void {
		$policy = new ExternalActionTransportPolicy();

		foreach ( $this->externalTransportTypes() as $type ) {
			$this->assertFalse( $policy->isAllowed( $actionClass::SLUG, [], $type ) );
			$this->assertFalse( $policy->isAllowed( $actionClass, [], $type ) );
		}
	}

	public function test_rest_transport_allows_only_explicit_allowlisted_actions() :void {
		$policy = new ExternalActionTransportPolicy();

		$this->assertTrue( $policy->isAllowed(
			TestRestFetchRequests::SLUG,
			[],
			ActionRoutingController::ACTION_REST
		) );
		$this->assertTrue( $policy->isAllowed(
			TestRestFetchRequests::class,
			[],
			ActionRoutingController::ACTION_REST
		) );
	}

	public function test_unlisted_actions_are_denied_from_rest_transport() :void {
		$policy = new ExternalActionTransportPolicy();

		$this->assertFalse( $policy->isAllowed(
			OperatorModeSwitch::SLUG,
			[],
			ActionRoutingController::ACTION_REST
		) );
		$this->assertFalse( $policy->isAllowed(
			OperatorModeSwitch::class,
			[],
			ActionRoutingController::ACTION_REST
		) );
	}

	public static function directRenderActionProvider() :array {
		return [
			[ SecurityReport::class ],
			[ ReportAreaChanges::class ],
			[ BlockFirewall::class ],
			[ LoginIntentFormShield::class ],
			[ ToastPlaceholder::class ],
			[ Wordpress::class ],
		];
	}

	private function externalTransportTypes() :array {
		return [
			ActionRoutingController::ACTION_SHIELD,
			ActionRoutingController::ACTION_AJAX,
			ActionRoutingController::ACTION_REST,
		];
	}
}
