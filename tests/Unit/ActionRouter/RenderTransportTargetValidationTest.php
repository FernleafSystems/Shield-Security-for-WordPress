<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\{
	AjaxRender,
	PluginImportExport_UpdateNotified,
	Render
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class RenderTransportTargetValidationTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
	}

	public function test_render_rejects_non_render_target() :void {
		$this->expectException( ActionException::class );

		( new RenderTransportTargetValidationRenderTestDouble( [
			'render_action_slug' => PluginImportExport_UpdateNotified::SLUG,
			'render_action_data' => [],
		] ) )->runExecForTest();
	}

	public function test_ajax_render_rejects_non_render_target() :void {
		$this->expectException( ActionException::class );

		( new RenderTransportTargetValidationAjaxRenderTestDouble( [
			'render_slug' => PluginImportExport_UpdateNotified::SLUG,
		] ) )->runExecForTest();
	}
}

class RenderTransportTargetValidationRenderTestDouble extends Render {

	public function runExecForTest() :void {
		$this->exec();
	}
}

class RenderTransportTargetValidationAjaxRenderTestDouble extends AjaxRender {

	public function runExecForTest() :void {
		$this->exec();
	}
}
