<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	ActionProcessor,
	ActionRoutingController,
	Actions\AjaxRender,
	Actions\PluginImportExport_UpdateNotified,
	Actions\Render,
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
