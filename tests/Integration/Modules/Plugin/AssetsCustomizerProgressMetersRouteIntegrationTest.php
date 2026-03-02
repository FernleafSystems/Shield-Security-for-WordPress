<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Assets\Enqueue;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class AssetsCustomizerProgressMetersRouteIntegrationTest extends ShieldIntegrationTestCase {

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

	public function test_progress_meters_are_localized_for_actions_queue_overview() :void {
		$comps = $this->getMainLocalisedComponentsForRoute(
			PluginNavs::NAV_SCANS,
			PluginNavs::SUBNAV_SCANS_OVERVIEW
		);

		$this->assertArrayHasKey( 'progress_meters', $comps );
	}

	public function test_progress_meters_are_not_localized_for_scans_results() :void {
		$comps = $this->getMainLocalisedComponentsForRoute(
			PluginNavs::NAV_SCANS,
			PluginNavs::SUBNAV_SCANS_RESULTS
		);

		$this->assertArrayNotHasKey( 'progress_meters', $comps );
	}

	public function test_progress_meters_remain_localized_for_configure_overview() :void {
		$comps = $this->getMainLocalisedComponentsForRoute(
			PluginNavs::NAV_ZONES,
			PluginNavs::SUBNAV_ZONES_OVERVIEW
		);

		$this->assertArrayHasKey( 'progress_meters', $comps );
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
}
