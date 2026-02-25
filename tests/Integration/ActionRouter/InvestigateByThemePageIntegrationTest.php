<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	Actions\Render\PageAdminPlugin,
	Constants
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\LookupRouteFormAssertions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class InvestigateByThemePageIntegrationTest extends ShieldIntegrationTestCase {

	use LookupRouteFormAssertions;

	public function set_up() {
		parent::set_up();
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
	}

	private function processor() :ActionProcessor {
		return new ActionProcessor();
	}

	private function renderByThemePage( string $themeSlug = '' ) :array {
		$filter = self::con()->prefix( 'bypass_is_plugin_admin' );
		add_filter( $filter, '__return_true', 1000 );

		try {
			$params = [
				Constants::NAV_ID     => PluginNavs::NAV_ACTIVITY,
				Constants::NAV_SUB_ID => PluginNavs::SUBNAV_ACTIVITY_BY_THEME,
			];
			if ( $themeSlug !== '' ) {
				$params[ 'theme_slug' ] = $themeSlug;
			}

			return $this->processor()
						->processAction( PageAdminPlugin::SLUG, $params )
						->payload();
		}
		finally {
			remove_filter( $filter, '__return_true', 1000 );
		}
	}

	public function test_valid_lookup_renders_file_status_and_activity_tables() :void {
		$themeSlug = $this->firstInstalledThemeSlug();
		$payload = $this->renderByThemePage( $themeSlug );
		$html = (string)( $payload[ 'render_output' ] ?? '' );

		$this->assertHtmlContainsMarker( 'data-investigation-table="1"', $html, 'By-theme investigation table marker' );
		$this->assertHtmlContainsMarker( 'data-table-type="file_scan_results"', $html, 'By-theme file status table marker' );
		$this->assertHtmlContainsMarker( 'data-table-type="activity"', $html, 'By-theme activity table marker' );
		$this->assertHtmlContainsMarker( 'data-subject-type="theme"', $html, 'By-theme subject type marker' );
	}

	public function test_no_lookup_renders_without_investigation_tables() :void {
		$payload = $this->renderByThemePage();
		$html = (string)( $payload[ 'render_output' ] ?? '' );

		$this->assertHtmlNotContainsMarker( 'data-investigation-table="1"', $html, 'By-theme page without lookup' );
	}

	public function test_lookup_form_includes_route_preservation_contract() :void {
		$payload = $this->renderByThemePage();
		$html = (string)( $payload[ 'render_output' ] ?? '' );

		$form = $this->extractLookupFormForSubNav( $html, PluginNavs::SUBNAV_ACTIVITY_BY_THEME );
		$this->assertLookupFormRouteContract( $form, PluginNavs::SUBNAV_ACTIVITY_BY_THEME );
	}

	private function firstInstalledThemeSlug() :string {
		$themes = Services::WpThemes()->getInstalledStylesheets();
		if ( empty( $themes ) ) {
			$this->markTestSkipped( 'No installed themes were available for investigate-by-theme integration test.' );
		}
		return (string)\array_values( $themes )[ 0 ];
	}
}
