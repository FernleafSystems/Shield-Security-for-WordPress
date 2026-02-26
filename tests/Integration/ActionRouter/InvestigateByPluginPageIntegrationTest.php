<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	Actions\Render\PageAdminPlugin,
	Actions\Render\PluginAdminPages\PageInvestigateByPlugin,
	Constants
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\LookupRouteFormAssertions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class InvestigateByPluginPageIntegrationTest extends ShieldIntegrationTestCase {

	use LookupRouteFormAssertions;

	public function set_up() {
		parent::set_up();

		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
	}

	private function processor() :ActionProcessor {
		return new ActionProcessor();
	}

	private function renderByPluginPage( string $pluginSlug = '' ) :array {
		return $this->renderByPluginPageAction( PageAdminPlugin::SLUG, $pluginSlug );
	}

	private function renderByPluginInnerPage( string $pluginSlug = '' ) :array {
		return $this->renderByPluginPageAction( PageInvestigateByPlugin::SLUG, $pluginSlug );
	}

	private function renderByPluginPageAction( string $actionSlug, string $pluginSlug = '' ) :array {
		$filter = self::con()->prefix( 'bypass_is_plugin_admin' );
		add_filter( $filter, '__return_true', 1000 );

		try {
			$params = [
				Constants::NAV_ID     => PluginNavs::NAV_ACTIVITY,
				Constants::NAV_SUB_ID => PluginNavs::SUBNAV_ACTIVITY_BY_PLUGIN,
			];
			if ( $pluginSlug !== '' ) {
				$params[ 'plugin_slug' ] = $pluginSlug;
			}

			return $this->processor()
						->processAction( $actionSlug, $params )
						->payload();
		}
		finally {
			remove_filter( $filter, '__return_true', 1000 );
		}
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

		$this->assertSame( $expectedTableCount, \substr_count( $html, 'data-investigation-table="1"' ) );
		if ( $fileStatusCount > 0 ) {
			$this->assertHtmlContainsMarker( 'data-table-type="file_scan_results"', $html, 'By-plugin file status table marker' );
		}
		else {
			$this->assertHtmlNotContainsMarker( 'data-table-type="file_scan_results"', $html, 'By-plugin file status empty state' );
		}
		if ( $activityCount > 0 ) {
			$this->assertHtmlContainsMarker( 'data-table-type="activity"', $html, 'By-plugin activity table marker' );
		}
		else {
			$this->assertHtmlNotContainsMarker( 'data-table-type="activity"', $html, 'By-plugin activity empty state' );
		}
		if ( $expectedTableCount > 0 ) {
			$this->assertHtmlContainsMarker( 'data-subject-type="plugin"', $html, 'By-plugin subject type marker' );
		}
		else {
			$this->assertHtmlNotContainsMarker( 'data-subject-type="plugin"', $html, 'By-plugin subject type absent on empty tables' );
		}
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
