<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\AjaxRender,
	Actions\FullPageDisplay\DisplayBlockPage,
	Actions\FullPageDisplay\FullPageDisplayDynamic,
	Actions\Render,
	Actions\Render\Components\Scans\Results\Wordpress,
	Actions\Render\FullPage\Block\BlockTrafficRateLimitExceeded,
	Actions\Render\FullPage\MainWP\TabManageSitePage,
	Actions\Render\FullPage\Mfa\ShieldLoginIntentPage,
	CaptureAjaxAction,
	CapturePluginAction
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;

class PublicFullPageTransportPolicyIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;

	private array $requestSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
	}

	public function tear_down() {
		$this->restoreCurrentRequestState( $this->requestSnapshot );
		parent::tear_down();
	}

	public function test_plugin_capture_rejects_direct_external_render() :void {
		$this->applyCurrentRequestState( [
			'REQUEST_URI' => '/',
		], [
			ActionData::FIELD_ACTION  => ActionData::FIELD_SHIELD,
			ActionData::FIELD_EXECUTE => Render::SLUG,
			'render_action_slug'      => ShieldLoginIntentPage::SLUG,
			'render_action_data'      => [
				'msg_error' => '<img src=x onerror=alert(1)>',
			],
		] );

		$this->assertFalse( ( new PublicFullPagePluginCaptureTestDouble() )->canRunForTest() );
	}

	public function test_public_block_recovery_transport_allows_known_block_target_without_external_render_data() :void {
		$request = ActionData::Build( DisplayBlockPage::class, false, [
			'render_slug' => BlockTrafficRateLimitExceeded::SLUG,
		] );
		$this->applyCurrentRequestState( [
			'REQUEST_URI' => '/?'.\http_build_query( $request ),
		], $request );

		$this->assertTrue( ( new PublicFullPagePluginCaptureTestDouble() )->canRunForTest() );
	}

	public function test_public_block_recovery_transport_rejects_external_render_data() :void {
		$request = ActionData::Build( DisplayBlockPage::class, false, [
			'render_slug' => BlockTrafficRateLimitExceeded::SLUG,
			'render_data' => [
				'block_meta_data' => [
					'unsafe' => 'external',
				],
			],
		] );
		$this->applyCurrentRequestState( [
			'REQUEST_URI' => '/?'.\http_build_query( $request ),
		], $request );

		$this->assertFalse( ( new PublicFullPagePluginCaptureTestDouble() )->canRunForTest() );
	}

	public function test_mainwp_dynamic_iframe_transport_accepts_generated_admin_url_shape() :void {
		$this->loginAsAdministrator();
		$request = ActionData::Build( FullPageDisplayDynamic::class, false, [
			'render_slug' => TabManageSitePage::SLUG,
			'render_data' => [
				'site_id' => 123,
			],
		] );
		$this->applyCurrentRequestState( [
			'REQUEST_URI' => '/wp-admin/?'.\http_build_query( $request ),
		], $request );

		$this->assertTrue( ( new PublicFullPagePluginCaptureTestDouble() )->canRunForTest() );
	}

	public function test_mainwp_dynamic_iframe_transport_rejects_missing_nonce() :void {
		$this->loginAsAdministrator();
		$request = ActionData::Build( FullPageDisplayDynamic::class, false, [
			'render_slug' => TabManageSitePage::SLUG,
			'render_data' => [
				'site_id' => 123,
			],
		] );
		unset( $request[ ActionData::FIELD_NONCE ] );
		$this->applyCurrentRequestState( [
			'REQUEST_URI' => '/wp-admin/?'.\http_build_query( $request ),
		], $request );

		$this->assertFalse( ( new PublicFullPagePluginCaptureTestDouble() )->canRunForTest() );
	}

	public function test_mainwp_dynamic_iframe_transport_rejects_wrong_render_slug() :void {
		$this->loginAsAdministrator();
		$request = ActionData::Build( FullPageDisplayDynamic::class, false, [
			'render_slug' => ShieldLoginIntentPage::SLUG,
			'render_data' => [
				'site_id' => 123,
			],
		] );
		$this->applyCurrentRequestState( [
			'REQUEST_URI' => '/wp-admin/?'.\http_build_query( $request ),
		], $request );

		$this->assertFalse( ( new PublicFullPagePluginCaptureTestDouble() )->canRunForTest() );
	}

	public function test_mainwp_dynamic_iframe_transport_rejects_invalid_site_id() :void {
		$this->loginAsAdministrator();
		$request = ActionData::Build( FullPageDisplayDynamic::class, false, [
			'render_slug' => TabManageSitePage::SLUG,
			'render_data' => [
				'site_id' => 0,
			],
		] );
		$this->applyCurrentRequestState( [
			'REQUEST_URI' => '/wp-admin/?'.\http_build_query( $request ),
		], $request );

		$this->assertFalse( ( new PublicFullPagePluginCaptureTestDouble() )->canRunForTest() );
	}

	public function test_mainwp_dynamic_iframe_transport_rejects_unauthenticated_user() :void {
		$request = ActionData::Build( FullPageDisplayDynamic::class, false, [
			'render_slug' => TabManageSitePage::SLUG,
			'render_data' => [
				'site_id' => 123,
			],
		] );
		\wp_set_current_user( 0 );
		$this->applyCurrentRequestState( [
			'REQUEST_URI' => '/wp-admin/?'.\http_build_query( $request ),
		], $request );

		$this->assertFalse( ( new PublicFullPagePluginCaptureTestDouble() )->canRunForTest() );
	}

	public function test_ajax_render_capture_remains_allowed_for_ajax_transport() :void {
		$this->loginAsAdministrator();
		$request = ActionData::BuildAjaxRender( Wordpress::class, [
			'display_context' => 'actions_queue',
		] );
		$this->applyCurrentShieldAjaxRequest( $request, false );

		$this->assertTrue( ( new PublicFullPageAjaxCaptureTestDouble() )->canRunForTest() );
		$this->assertSame( AjaxRender::SLUG, (string)( $request[ ActionData::FIELD_EXECUTE ] ?? '' ) );
	}
}

class PublicFullPagePluginCaptureTestDouble extends CapturePluginAction {

	public function canRunForTest() :bool {
		return $this->canRun();
	}
}

class PublicFullPageAjaxCaptureTestDouble extends CaptureAjaxAction {

	public function canRunForTest() :bool {
		return $this->canRun();
	}
}
