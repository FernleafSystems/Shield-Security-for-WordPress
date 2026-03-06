<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Assets\Enqueue;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class AssetsCustomizerReportsRouteIntegrationTest extends ShieldIntegrationTestCase {

	private array $originalQuery = [];
	private $localisationsFilterBackup = null;

	public function set_up() {
		parent::set_up();
		$this->originalQuery = \is_array( Services::Request()->query ) ? Services::Request()->query : [];
		$this->backupLocalisationsFilter();
		$this->requireController()->comps->assets_customizer->execute();
	}

	public function tear_down() {
		Services::Request()->query = $this->originalQuery;
		$this->restoreLocalisationsFilter();
		parent::tear_down();
	}

	public function test_reports_table_is_localized_for_reports_overview() :void {
		$comps = $this->getMainLocalisedComponentsForRoute(
			PluginNavs::NAV_REPORTS,
			PluginNavs::SUBNAV_REPORTS_OVERVIEW
		);

		$this->assertReportsTableLocalized( $comps );
	}

	public function test_reports_table_is_localized_for_reports_list() :void {
		$comps = $this->getMainLocalisedComponentsForRoute(
			PluginNavs::NAV_REPORTS,
			PluginNavs::SUBNAV_REPORTS_LIST
		);

		$this->assertReportsTableLocalized( $comps );
	}

	public function test_reports_table_is_not_localized_for_reports_settings() :void {
		$comps = $this->getMainLocalisedComponentsForRoute(
			PluginNavs::NAV_REPORTS,
			PluginNavs::SUBNAV_REPORTS_SETTINGS
		);

		$this->assertArrayHasKey( 'tables', $comps );
		$this->assertArrayNotHasKey( 'reports', $comps[ 'tables' ] ?? [] );
	}

	private function getMainLocalisedComponentsForRoute( string $nav, string $subNav ) :array {
		Services::Request()->query = \array_merge( $this->originalQuery, [
			PluginNavs::FIELD_NAV    => $nav,
			PluginNavs::FIELD_SUBNAV => $subNav,
		] );

		$locals = \apply_filters( 'shield/custom_localisations', [], Enqueue::PLUGIN_ADMIN_HOOK_SUFFIX, [ 'main' ] );
		foreach ( \is_array( $locals ) ? $locals : [] as $local ) {
			if ( \is_array( $local ) && ( $local[ 0 ] ?? '' ) === 'main' ) {
				$comps = $local[ 2 ][ 'comps' ] ?? [];
				return \is_array( $comps ) ? $comps : [];
			}
		}

		return [];
	}

	private function backupLocalisationsFilter() :void {
		global $wp_filter;
		$current = $wp_filter[ 'shield/custom_localisations' ] ?? null;
		$this->localisationsFilterBackup = \is_object( $current ) ? clone $current : $current;
	}

	private function restoreLocalisationsFilter() :void {
		global $wp_filter;

		if ( $this->localisationsFilterBackup === null ) {
			unset( $wp_filter[ 'shield/custom_localisations' ] );
		}
		else {
			$wp_filter[ 'shield/custom_localisations' ] = $this->localisationsFilterBackup;
		}
	}

	private function assertReportsTableLocalized( array $comps ) :void {
		$this->assertArrayHasKey( 'tables', $comps );
		$this->assertArrayHasKey( 'reports', $comps[ 'tables' ] ?? [] );
		$this->assertArrayHasKey( 'ajax', $comps[ 'tables' ][ 'reports' ] ?? [] );
		$this->assertArrayHasKey( 'table_action', $comps[ 'tables' ][ 'reports' ][ 'ajax' ] ?? [] );
		$this->assertArrayHasKey( 'vars', $comps[ 'tables' ][ 'reports' ] ?? [] );
		$this->assertArrayHasKey( 'datatables_init', $comps[ 'tables' ][ 'reports' ][ 'vars' ] ?? [] );
	}
}
