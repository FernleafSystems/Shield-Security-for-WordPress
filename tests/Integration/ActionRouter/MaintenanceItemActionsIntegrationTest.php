<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	Actions\MaintenanceItemIgnore,
	Actions\MaintenanceItemUnignore,
	Actions\Render\Components\Widgets\MaintenanceIssueStateProvider,
	Actions\Render\PluginAdminPages\PageActionsQueueLanding,
	Constants
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\PluginAdminRouteRenderAssertions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class MaintenanceItemActionsIntegrationTest extends ShieldIntegrationTestCase {

	use PluginAdminRouteRenderAssertions;

	private array $optionsSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
		$this->optionsSnapshot = $this->snapshotSelectedOptions( [
			MaintenanceIssueStateProvider::OPT_KEY,
		] );
		\delete_site_transient( 'update_plugins' );
	}

	public function tear_down() {
		\delete_site_transient( 'update_plugins' );
		$this->restoreSelectedOptions( $this->optionsSnapshot );
		parent::tear_down();
	}

	public function test_ignore_action_reduces_plugin_update_count_by_one() :void {
		$pluginFiles = $this->requireAtLeastInstalledPlugins( 2 );
		$this->setPluginUpdatesAvailable( $pluginFiles );

		$beforePayload = $this->renderActionsQueueLandingPage();
		$beforeMaintenance = $this->maintenanceZoneTile( $beforePayload );
		$beforeSummary = $this->summaryRailTab( $beforePayload );

		$response = $this->processMaintenanceAction( MaintenanceItemIgnore::SLUG, [
			'maintenance_key' => 'wp_plugins_updates',
			'identifier'      => $pluginFiles[ 0 ],
		] );

		$afterPayload = $this->renderActionsQueueLandingPage();
		$afterMaintenance = $this->maintenanceZoneTile( $afterPayload );
		$afterSummary = $this->summaryRailTab( $afterPayload );

		$this->assertTrue( (bool)( $response[ 'success' ] ?? false ) );
		$this->assertSame(
			(int)( $beforeMaintenance[ 'total_issues' ] ?? 0 ) - 1,
			(int)( $afterMaintenance[ 'total_issues' ] ?? 0 )
		);
		$this->assertSame(
			(int)( $beforeSummary[ 'count' ] ?? 0 ) - 1,
			(int)( $afterSummary[ 'count' ] ?? 0 )
		);
		$this->assertSame(
			[ $pluginFiles[ 0 ] ],
			$this->requireController()->opts->optGet( MaintenanceIssueStateProvider::OPT_KEY )['wp_plugins_updates']
		);
	}

	public function test_ignoring_all_plugin_updates_removes_warning_count_and_keeps_good_assessment_note() :void {
		$pluginFiles = $this->requireAtLeastInstalledPlugins( 2 );
		$this->setPluginUpdatesAvailable( $pluginFiles );

		foreach ( $pluginFiles as $pluginFile ) {
			$response = $this->processMaintenanceAction( MaintenanceItemIgnore::SLUG, [
				'maintenance_key' => 'wp_plugins_updates',
				'identifier'      => $pluginFile,
			] );
			$this->assertTrue( (bool)( $response[ 'success' ] ?? false ) );
		}

		$payload = $this->renderActionsQueueLandingPage();
		$maintenance = $this->maintenanceZoneTile( $payload );
		$row = $this->maintenanceAssessmentRow( $payload, 'wp_plugins_updates' );

		$this->assertSame( 0, (int)( $maintenance[ 'total_issues' ] ?? -1 ) );
		$this->assertSame( 'good', (string)( $maintenance[ 'status' ] ?? '' ) );
		$this->assertSame( 'good', (string)( $row[ 'status' ] ?? '' ) );
		$this->assertStringContainsString( 'ignored', (string)( $row[ 'description' ] ?? '' ) );
		$this->assertFalse( (bool)( $payload[ 'render_data' ][ 'flags' ][ 'queue_is_empty' ] ?? true ) );
		$this->assertContains(
			'wp_plugins_updates',
			\array_column( $maintenance[ 'items' ] ?? [], 'key' )
		);
	}

	public function test_unignore_action_is_idempotent_and_restores_plugin_update_count() :void {
		$pluginFiles = $this->requireAtLeastInstalledPlugins( 2 );
		$this->setPluginUpdatesAvailable( $pluginFiles );

		$this->processMaintenanceAction( MaintenanceItemIgnore::SLUG, [
			'maintenance_key' => 'wp_plugins_updates',
			'identifier'      => $pluginFiles[ 0 ],
		] );
		$ignoredPayload = $this->renderActionsQueueLandingPage();
		$ignoredMaintenance = $this->maintenanceZoneTile( $ignoredPayload );

		$firstRestore = $this->processMaintenanceAction( MaintenanceItemUnignore::SLUG, [
			'maintenance_key' => 'wp_plugins_updates',
			'identifier'      => $pluginFiles[ 0 ],
		] );
		$secondRestore = $this->processMaintenanceAction( MaintenanceItemUnignore::SLUG, [
			'maintenance_key' => 'wp_plugins_updates',
			'identifier'      => $pluginFiles[ 0 ],
		] );

		$restoredPayload = $this->renderActionsQueueLandingPage();
		$restoredMaintenance = $this->maintenanceZoneTile( $restoredPayload );

		$this->assertTrue( (bool)( $firstRestore[ 'success' ] ?? false ) );
		$this->assertTrue( (bool)( $secondRestore[ 'success' ] ?? false ) );
		$this->assertSame(
			(int)( $ignoredMaintenance[ 'total_issues' ] ?? 0 ) + 1,
			(int)( $restoredMaintenance[ 'total_issues' ] ?? 0 )
		);
		$this->assertSame( [], $this->requireController()->opts->optGet( MaintenanceIssueStateProvider::OPT_KEY )['wp_plugins_updates'] );
	}

	public function test_ignore_action_rejects_missing_identifier_for_sub_item_maintenance() :void {
		$pluginFiles = $this->requireAtLeastInstalledPlugins( 1 );
		$this->setPluginUpdatesAvailable( $pluginFiles );

		$response = $this->processMaintenanceAction( MaintenanceItemIgnore::SLUG, [
			'maintenance_key' => 'wp_plugins_updates',
		] );

		$this->assertFalse( (bool)( $response[ 'success' ] ?? true ) );
		$this->assertStringContainsString( 'identifier', (string)( $response[ 'message' ] ?? '' ) );
		$this->assertSame( [], $this->requireController()->opts->optGet( MaintenanceIssueStateProvider::OPT_KEY )['wp_plugins_updates'] );
	}

	private function processMaintenanceAction( string $slug, array $data ) :array {
		return ( new ActionProcessor() )->processAction( $slug, $data )->payload();
	}

	private function renderActionsQueueLandingPage() :array {
		return $this->processActionPayloadWithAdminBypass( PageActionsQueueLanding::SLUG, [
			Constants::NAV_ID     => PluginNavs::NAV_SCANS,
			Constants::NAV_SUB_ID => PluginNavs::SUBNAV_SCANS_OVERVIEW,
		] );
	}

	private function maintenanceZoneTile( array $payload ) :array {
		$zoneTiles = \is_array( $payload[ 'render_data' ][ 'vars' ][ 'zone_tiles' ] ?? null )
			? $payload[ 'render_data' ][ 'vars' ][ 'zone_tiles' ]
			: [];
		$matches = \array_values( \array_filter(
			$zoneTiles,
			static fn( array $tile ) :bool => (string)( $tile[ 'key' ] ?? '' ) === 'maintenance'
		) );
		$this->assertCount( 1, $matches );
		return $matches[ 0 ] ?? [];
	}

	private function maintenanceAssessmentRow( array $payload, string $key ) :array {
		$maintenance = $this->maintenanceZoneTile( $payload );
		$rows = \is_array( $maintenance[ 'assessment_rows' ] ?? null ) ? $maintenance[ 'assessment_rows' ] : [];
		$matches = \array_values( \array_filter(
			$rows,
			static fn( array $row ) :bool => (string)( $row[ 'key' ] ?? '' ) === $key
		) );
		$this->assertCount( 1, $matches );
		return $matches[ 0 ] ?? [];
	}

	private function summaryRailTab( array $payload ) :array {
		$tabs = \is_array( $payload[ 'render_data' ][ 'vars' ][ 'scans_results' ][ 'vars' ][ 'rail_tabs' ] ?? null )
			? $payload[ 'render_data' ][ 'vars' ][ 'scans_results' ][ 'vars' ][ 'rail_tabs' ]
			: [];
		$matches = \array_values( \array_filter(
			$tabs,
			static fn( array $tab ) :bool => (string)( $tab[ 'key' ] ?? '' ) === 'summary'
		) );
		$this->assertCount( 1, $matches );
		return $matches[ 0 ] ?? [];
	}

	/**
	 * @return list<string>
	 */
	private function requireAtLeastInstalledPlugins( int $minimum ) :array {
		$pluginFiles = \array_values( \array_map(
			static fn( $file ) :string => (string)$file,
			\array_keys( Services::WpPlugins()->getPlugins() )
		) );
		\natsort( $pluginFiles );
		$pluginFiles = \array_values( $pluginFiles );

		if ( \count( $pluginFiles ) < $minimum ) {
			$this->markTestSkipped( 'Not enough installed plugins are available for this integration fixture.' );
		}

		return \array_slice( $pluginFiles, 0, $minimum );
	}

	/**
	 * @param list<string> $pluginFiles
	 */
	private function setPluginUpdatesAvailable( array $pluginFiles ) :void {
		$updates = new \stdClass();
		$updates->response = [];

		foreach ( $pluginFiles as $index => $pluginFile ) {
			$updates->response[ $pluginFile ] = (object)[
				'plugin'      => $pluginFile,
				'new_version' => self::con()->cfg->version().'.'.( $index + 1 ),
			];
		}

		\set_site_transient( 'update_plugins', $updates );
	}
}
