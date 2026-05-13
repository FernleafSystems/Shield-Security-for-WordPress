<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	Actions\Render\PluginAdminPages\InvestigateByCorePanelBody,
	Constants
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\{
	PluginAdminRouteRenderAssertions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class InvestigateByCorePageIntegrationTest extends ShieldIntegrationTestCase {

	use PluginAdminRouteRenderAssertions;

	public function set_up() {
		parent::set_up();
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
	}

	private function renderByCorePage() :array {
		return $this->renderPluginAdminRoutePayload(
			PluginNavs::NAV_ACTIVITY,
			PluginNavs::SUBNAV_ACTIVITY_BY_CORE
		);
	}

	private function renderByCorePanelBody() :array {
		return $this->processActionPayloadWithAdminBypass( InvestigateByCorePanelBody::SLUG, [
			Constants::NAV_ID     => PluginNavs::NAV_ACTIVITY,
			Constants::NAV_SUB_ID => PluginNavs::SUBNAV_ACTIVITY_BY_CORE,
		] );
	}

	public function test_core_page_renders_file_status_and_activity_tables() :void {
		$renderData = (array)( $this->renderByCorePanelBody()[ 'render_data' ] ?? [] );
		$tables = (array)( $renderData[ 'vars' ][ 'tables' ] ?? [] );
		$fileStatusCount = (int)( $renderData[ 'vars' ][ 'tabs' ][ 'file_status' ][ 'count' ] ?? 0 );
		$activityCount = (int)( $renderData[ 'vars' ][ 'tabs' ][ 'activity' ][ 'count' ] ?? 0 );
		$this->assertSame( true, $renderData[ 'flags' ][ 'has_subject' ] ?? null );
		$this->assertArrayNotHasKey( 'back_to_investigate', $renderData[ 'hrefs' ] ?? [] );
		$this->assertArrayNotHasKey( 'back_to_investigate', $renderData[ 'strings' ] ?? [] );
		$this->assertSame( true, $tables[ 'file_status' ][ 'is_flat' ] ?? null );
		$this->assertSame( $fileStatusCount > 0, isset( $tables[ 'file_status' ][ 'table_id' ] ) );
		$this->assertSame( $activityCount > 0, isset( $tables[ 'activity' ][ 'table_type' ] ) );
		if ( $activityCount > 0 ) {
			$this->assertSame( true, $tables[ 'activity' ][ 'is_flat' ] ?? null );
			$this->assertSame( 'core', (string)( $tables[ 'activity' ][ 'subject_type' ] ?? '' ) );
		}
		if ( $fileStatusCount > 0 ) {
			$tableAction = \json_decode( (string)( $tables[ 'file_status' ][ 'table_action_attr' ] ?? '' ), true, 512, \JSON_THROW_ON_ERROR );
			$this->assertSame( 'wordpress', $tableAction[ 'type' ] ?? '' );
			$this->assertSame( 'wordpress', $tableAction[ 'file' ] ?? '' );
		}
		$routePayload = $this->renderByCorePage();
		$this->assertRouteRenderOutputHealthy( $routePayload, 'activity by-core route' );
		$this->assertPluginAdminShellRouteState( $routePayload, PluginNavs::SUBNAV_ACTIVITY_BY_CORE );
	}

	public function test_panel_body_action_renders_core_markup_contract() :void {
		$payload = $this->renderByCorePanelBody();
		$this->assertRouteRenderOutputHealthy(
			$payload,
			'investigate by-core panel body action'
		);
		$renderData = (array)( $payload[ 'render_data' ] ?? [] );
		$this->assertSame( '/wpadmin/components/investigate/core_body.twig', (string)( $payload[ 'render_template' ] ?? '' ) );
		$this->assertSame( true, $renderData[ 'flags' ][ 'has_subject' ] ?? null );
		$this->assertArrayHasKey( 'subject_header', (array)( $renderData[ 'vars' ] ?? [] ) );
		$this->assertSame( [ 'file_status', 'activity' ], \array_keys( (array)( $renderData[ 'vars' ][ 'tables' ] ?? [] ) ) );
	}
}
