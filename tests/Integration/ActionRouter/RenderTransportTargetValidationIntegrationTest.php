<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	ActionProcessor,
	ActionRoutingController,
	Actions\AjaxRender,
	Actions\PluginImportExport_UpdateNotified,
	Actions\Render,
	Actions\Render\Components\OffCanvas\SearchHelp,
	Exceptions\ActionException
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;

class RenderTransportTargetValidationIntegrationTest extends ShieldIntegrationTestCase {

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

	public function test_render_rejects_non_render_action_target() :void {
		$this->expectException( ActionException::class );

		( new ActionProcessor() )->processAction( Render::SLUG, [
			'render_action_slug' => PluginImportExport_UpdateNotified::SLUG,
			'render_action_data' => [],
		] );
	}

	public function test_ajax_render_request_cannot_replace_the_display_data_all_renders_share() :void {
		$this->loginAsSecurityAdmin();

		$renderData = $this->requireController()->action_router->action(
			AjaxRender::SLUG,
			[
				'render_slug'      => SearchHelp::SLUG,
				'unique_render_id' => 'from-the-request',
				'flags'            => [ 'is_whitelabelled' => 'from-the-request' ],
				'strings'          => [ 'from_the_request' => 'from-the-request' ],
				'hrefs'            => [ 'from_the_request' => 'https://example.com/' ],
			],
			ActionRoutingController::ACTION_AJAX
		)->payload()[ 'render_data' ] ?? [];

		$this->assertNotSame( '', $renderData[ 'content' ][ 'canvas_title' ] ?? '' );
		$this->assertNotSame( 'from-the-request', $renderData[ 'unique_render_id' ] ?? '' );
		$this->assertIsBool( $renderData[ 'flags' ][ 'is_whitelabelled' ] ?? null );
		$this->assertArrayNotHasKey( 'from_the_request', $renderData[ 'strings' ] ?? [] );
		$this->assertArrayNotHasKey( 'from_the_request', $renderData[ 'hrefs' ] ?? [] );
	}

	public function test_ajax_render_target_cannot_be_named_by_its_class() :void {
		$this->loginAsSecurityAdmin();

		$this->expectException( ActionException::class );

		$this->requireController()->action_router->action(
			AjaxRender::SLUG,
			[ 'render_slug' => SearchHelp::class ],
			ActionRoutingController::ACTION_AJAX
		);
	}

	public function test_ajax_render_rejects_non_render_action_target() :void {
		$this->loginAsAdministrator();

		$request = ActionData::Build( AjaxRender::class, true, [
			'render_slug' => PluginImportExport_UpdateNotified::SLUG,
		] );
		$this->applyCurrentShieldAjaxRequest( $request, false );

		$this->expectException( ActionException::class );

		$this->requireController()->action_router->action(
			AjaxRender::SLUG,
			$request,
			ActionRoutingController::ACTION_AJAX
		);
	}
}
