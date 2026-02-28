<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	Actions\Render\PluginAdminPages\PageInvestigateByPlugin,
	Constants
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\{
	InvestigatePageAssertions,
	LookupRouteFormAssertions,
	PluginAdminRouteRenderAssertions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class InvestigateByPluginPageIntegrationTest extends ShieldIntegrationTestCase {

	use InvestigatePageAssertions, LookupRouteFormAssertions, PluginAdminRouteRenderAssertions;

	public function set_up() {
		parent::set_up();

		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
	}

	private function renderByPluginPage( string $pluginSlug = '' ) :array {
		return $this->renderPluginAdminRoutePayload(
			PluginNavs::NAV_ACTIVITY,
			PluginNavs::SUBNAV_ACTIVITY_BY_PLUGIN,
			$pluginSlug !== '' ? [ 'plugin_slug' => $pluginSlug ] : []
		);
	}

	private function renderByPluginInnerPage( string $pluginSlug = '' ) :array {
		$params = [
			Constants::NAV_ID     => PluginNavs::NAV_ACTIVITY,
			Constants::NAV_SUB_ID => PluginNavs::SUBNAV_ACTIVITY_BY_PLUGIN,
		];
		if ( $pluginSlug !== '' ) {
			$params[ 'plugin_slug' ] = $pluginSlug;
		}
		return $this->processActionPayloadWithAdminBypass( PageInvestigateByPlugin::SLUG, $params );
	}

	public function test_valid_lookup_renders_file_status_and_activity_tables() :void {
		$pluginSlug = $this->firstInstalledPluginSlug();
		$renderData = (array)( $this->renderByPluginInnerPage( $pluginSlug )[ 'render_data' ] ?? [] );
		$this->assertSame( true, $renderData[ 'flags' ][ 'has_lookup' ] ?? null );
		$this->assertSame( true, $renderData[ 'flags' ][ 'has_subject' ] ?? null );
		$fileStatusCount = (int)( $renderData[ 'vars' ][ 'tabs' ][ 'file_status' ][ 'count' ] ?? 0 );
		$activityCount = (int)( $renderData[ 'vars' ][ 'tabs' ][ 'activity' ][ 'count' ] ?? 0 );
		$expectedTableCount = ( $fileStatusCount > 0 ? 1 : 0 ) + ( $activityCount > 0 ? 1 : 0 );

		$payload = $this->renderByPluginPage( $pluginSlug );
		$html = (string)( $payload[ 'render_output' ] ?? '' );
		$xpath = $this->investigateDomXPath( $html );
		$this->assertHtmlContainsMarker( 'File Scan Status', $html, 'By-plugin file tab label marker' );
		$this->assertHtmlContainsMarker( 'Full Scan Results', $html, 'By-plugin file CTA label marker' );
		$this->assertInvestigateOverviewLabel( $xpath, 'Name', 'By-plugin overview table row marker' );
		$this->assertHtmlNotContainsMarker( 'Back To Investigate', $html, 'By-plugin back button removed marker' );
		$this->assertHtmlNotContainsMarker( 'investigate-summary-grid', $html, 'By-plugin summary cards removed marker' );

		$this->assertInvestigateDatatableCount( $xpath, $expectedTableCount, 'By-plugin datatable count marker' );
		$this->assertInvestigateTableTypeByCount( $xpath, 'file_scan_results', $fileStatusCount, 'By-plugin file status table marker' );
		$this->assertInvestigateTableTypeByCount( $xpath, 'activity', $activityCount, 'By-plugin activity table marker' );
		$this->assertInvestigateSubjectTypeByCount( $xpath, 'plugin', $expectedTableCount, 'By-plugin subject type marker' );
	}

	public function test_no_lookup_renders_without_investigation_tables() :void {
		$payload = $this->renderByPluginPage();
		$html = (string)( $payload[ 'render_output' ] ?? '' );

		$this->assertHtmlNotContainsMarker( 'data-investigation-table="1"', $html, 'By-plugin page without lookup' );
	}

	public function test_lookup_form_includes_route_preservation_contract() :void {
		$payload = $this->renderByPluginPage();
		$html = (string)( $payload[ 'render_output' ] ?? '' );

		$form = $this->extractLookupFormForSubNav( $html, PluginNavs::SUBNAV_ACTIVITY_BY_PLUGIN );
		$this->assertLookupFormRouteContract( $form, PluginNavs::SUBNAV_ACTIVITY_BY_PLUGIN );
	}

	private function firstInstalledPluginSlug() :string {
		$plugins = Services::WpPlugins()->getInstalledPluginFiles();
		if ( empty( $plugins ) ) {
			$this->markTestSkipped( 'No installed plugins were available for investigate-by-plugin integration test.' );
		}
		return (string)\array_values( $plugins )[ 0 ];
	}
}
