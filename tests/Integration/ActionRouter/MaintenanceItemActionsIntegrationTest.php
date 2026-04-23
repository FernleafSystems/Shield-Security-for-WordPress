<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionProcessor;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MaintenanceItemIgnore;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MaintenanceItemUnignore;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\MaintenanceIssueStateProvider;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ActionsQueueDrillDownGroups;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageActionsQueueLanding;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\InvalidActionNonceException;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\SecurityAdminRequiredException;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\ActionRequestNonceFixture;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\PluginAdminRouteRenderAssertions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class MaintenanceItemActionsIntegrationTest extends ShieldIntegrationTestCase {

	use ActionRequestNonceFixture;
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

		$response = $this->processMaintenanceAction( MaintenanceItemIgnore::class, [
			'maintenance_key' => 'wp_plugins_updates',
			'identifier'      => $pluginFiles[ 0 ],
		] );

		$afterPayload = $this->renderActionsQueueLandingPage();
		$afterMaintenance = $this->maintenanceZoneTile( $afterPayload );

		$this->assertTrue( (bool)( $response[ 'success' ] ?? false ) );
		$this->assertSame(
			(int)( $beforeMaintenance[ 'total_issues' ] ?? 0 ) - 1,
			(int)( $afterMaintenance[ 'total_issues' ] ?? 0 )
		);
		$this->assertSame(
			[ $pluginFiles[ 0 ] ],
			$this->requireController()->opts->optGet( MaintenanceIssueStateProvider::OPT_KEY )['wp_plugins_updates']
		);
	}

	public function test_ignoring_all_plugin_updates_removes_warning_count() :void {
		$pluginFiles = $this->requireAtLeastInstalledPlugins( 2 );
		$this->setPluginUpdatesAvailable( $pluginFiles );

		foreach ( $pluginFiles as $pluginFile ) {
			$response = $this->processMaintenanceAction( MaintenanceItemIgnore::class, [
				'maintenance_key' => 'wp_plugins_updates',
				'identifier'      => $pluginFile,
			] );
			$this->assertTrue( (bool)( $response[ 'success' ] ?? false ) );
		}

		$state = ( new MaintenanceIssueStateProvider() )->buildStates()[ 'wp_plugins_updates' ] ?? [];

		$this->assertSame( 0, (int)( $state[ 'count' ] ?? -1 ) );
		$this->assertSame( \count( $pluginFiles ), (int)( $state[ 'ignored_count' ] ?? -1 ) );
		$this->assertSame( 'good', (string)( $state[ 'severity' ] ?? '' ) );
		$this->assertEqualsCanonicalizing(
			$pluginFiles,
			(array)$this->requireController()->opts->optGet( MaintenanceIssueStateProvider::OPT_KEY )[ 'wp_plugins_updates' ]
		);
	}

	public function test_unignore_action_is_idempotent_and_restores_plugin_update_count() :void {
		$pluginFiles = $this->requireAtLeastInstalledPlugins( 2 );
		$this->setPluginUpdatesAvailable( $pluginFiles );

		$this->processMaintenanceAction( MaintenanceItemIgnore::class, [
			'maintenance_key' => 'wp_plugins_updates',
			'identifier'      => $pluginFiles[ 0 ],
		] );
		$ignoredPayload = $this->renderActionsQueueLandingPage();
		$ignoredMaintenance = $this->maintenanceZoneTile( $ignoredPayload );

		$firstRestore = $this->processMaintenanceAction( MaintenanceItemUnignore::class, [
			'maintenance_key' => 'wp_plugins_updates',
			'identifier'      => $pluginFiles[ 0 ],
		] );
		$secondRestore = $this->processMaintenanceAction( MaintenanceItemUnignore::class, [
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

		$response = $this->processMaintenanceAction( MaintenanceItemIgnore::class, [
			'maintenance_key' => 'wp_plugins_updates',
		] );

		$this->assertFalse( (bool)( $response[ 'success' ] ?? true ) );
		$this->assertSame( [], $this->requireController()->opts->optGet( MaintenanceIssueStateProvider::OPT_KEY )['wp_plugins_updates'] );
	}

	public function test_ignore_action_requires_valid_nonce() :void {
		$pluginFiles = $this->requireAtLeastInstalledPlugins( 1 );
		$this->setPluginUpdatesAvailable( $pluginFiles );
		$snapshot = $this->seedActionNonceContext( MaintenanceItemIgnore::class );
		$this->mergeCurrentRequestTransport( [ 'exnonce' => '' ] );

		try {
			$this->expectException( InvalidActionNonceException::class );
			( new ActionProcessor() )->processAction( MaintenanceItemIgnore::SLUG, [
				'maintenance_key' => 'wp_plugins_updates',
				'identifier'      => $pluginFiles[ 0 ],
			] );
		}
		finally {
			$this->restoreActionNonceContext( $snapshot );
		}
	}

	public function test_ignore_action_rejects_invalid_nonce() :void {
		$pluginFiles = $this->requireAtLeastInstalledPlugins( 1 );
		$this->setPluginUpdatesAvailable( $pluginFiles );
		$snapshot = $this->seedActionNonceContext( MaintenanceItemIgnore::class );
		$this->mergeCurrentRequestTransport( [ 'exnonce' => 'invalid_nonce' ] );

		try {
			$this->expectException( InvalidActionNonceException::class );
			( new ActionProcessor() )->processAction( MaintenanceItemIgnore::SLUG, [
				'maintenance_key' => 'wp_plugins_updates',
				'identifier'      => $pluginFiles[ 0 ],
			] );
		}
		finally {
			$this->restoreActionNonceContext( $snapshot );
		}
	}

	public function test_ignore_action_requires_security_admin_even_with_valid_nonce() :void {
		$pluginFiles = $this->requireAtLeastInstalledPlugins( 1 );
		$this->setPluginUpdatesAvailable( $pluginFiles );
		$this->loginAsAdministrator();
		$snapshot = $this->seedActionNonceContext( MaintenanceItemIgnore::class );

		try {
			$this->expectException( SecurityAdminRequiredException::class );
			( new ActionProcessor() )->processAction( MaintenanceItemIgnore::SLUG, [
				'maintenance_key' => 'wp_plugins_updates',
				'identifier'      => $pluginFiles[ 0 ],
			] );
		}
		finally {
			$this->restoreActionNonceContext( $snapshot );
		}
	}

	public function test_groups_refresh_keeps_selected_review_group_resolvable_after_it_becomes_healthy() :void {
		$pluginFiles = $this->requireAtLeastInstalledPlugins( 2 );
		$this->setPluginUpdatesAvailable( $pluginFiles );
		$selectedGroupKey = 'wp_plugins_updates';
		$this->assertSame( 'wp_plugins_updates', $selectedGroupKey );

		foreach ( $pluginFiles as $pluginFile ) {
			$response = $this->processMaintenanceAction( MaintenanceItemIgnore::class, [
				'maintenance_key' => 'wp_plugins_updates',
				'identifier'      => $pluginFile,
			] );
			$this->assertTrue( (bool)( $response[ 'success' ] ?? false ) );
		}

		$payload = $this->processActionPayloadWithAdminBypass( ActionsQueueDrillDownGroups::SLUG, [
			'bucket'                  => 'review',
			'group'                   => $selectedGroupKey,
			'include_landing_refresh' => 1,
		] );

		$this->assertArrayNotHasKey( 'render_data', $payload );
		$this->assertArrayNotHasKey( 'render_output', $payload );
		$this->assertSame( $selectedGroupKey, (string)( $payload[ 'selected_group' ][ 'key' ] ?? '' ) );
		$this->assertSame( 'maintenance', (string)( $payload[ 'selected_group' ][ 'detail_shell' ] ?? '' ) );
		$this->assertSame(
			(string)( $payload[ 'selected_group' ][ 'label' ] ?? '' ),
			(string)( $payload[ 'selected_group' ][ 'header' ][ 'title' ] ?? '' )
		);
		$this->assertSame( \count( $pluginFiles ), (int)( $payload[ 'selected_group' ][ 'item_count' ] ?? -1 ) );
		$this->assertNotSame( '', \trim( (string)( $payload[ 'selected_group' ][ 'header' ][ 'badge' ] ?? '' ) ) );
		$this->assertArrayNotHasKey( 'queue_is_empty', $payload[ 'landing_refresh' ] ?? [] );
		$this->assertSame( 'review', (string)( $payload[ 'bucket_selection' ][ 'key' ] ?? '' ) );
	}

	/**
	 * @param class-string<MaintenanceItemIgnore|MaintenanceItemUnignore> $actionClass
	 */
	private function processMaintenanceAction( string $actionClass, array $data ) :array {
		$snapshot = $this->seedActionNonceContext( $actionClass );

		try {
			return ( new ActionProcessor() )->processAction( $actionClass::SLUG, $data )->payload();
		}
		finally {
			$this->restoreActionNonceContext( $snapshot );
		}
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
