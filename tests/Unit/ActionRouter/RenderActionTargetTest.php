<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\{
	AjaxRender,
	PluginImportExport_UpdateNotified
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\Contexts\EmailReportAlert;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Report\SecurityReportAlert;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ActionsQueueAssetFileStatusDetail;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\RenderActionTarget;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class RenderActionTargetTest extends BaseUnitTest {

	public function test_resolve_accepts_render_action_slug() :void {
		$this->assertSame(
			ActionsQueueAssetFileStatusDetail::class,
			RenderActionTarget::resolve( ActionsQueueAssetFileStatusDetail::SLUG )
		);
	}

	public function test_resolve_accepts_render_action_class() :void {
		$this->assertSame(
			ActionsQueueAssetFileStatusDetail::class,
			RenderActionTarget::resolve( ActionsQueueAssetFileStatusDetail::class )
		);
	}

	public function test_resolve_accepts_new_report_render_actions() :void {
		$this->assertSame( EmailReportAlert::class, RenderActionTarget::resolve( EmailReportAlert::SLUG ) );
		$this->assertSame( SecurityReportAlert::class, RenderActionTarget::resolve( SecurityReportAlert::SLUG ) );
	}

	public function test_resolve_rejects_non_render_action_slug() :void {
		$this->assertSame( '', RenderActionTarget::resolve( PluginImportExport_UpdateNotified::SLUG ) );
	}

	public function test_resolve_rejects_transport_action_slug() :void {
		$this->assertSame( '', RenderActionTarget::resolve( AjaxRender::SLUG ) );
	}
}
