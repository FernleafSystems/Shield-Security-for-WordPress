<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\AttentionItemsProvider;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class AttentionItemsProviderIntegrationTest extends ShieldIntegrationTestCase {

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'scans' );
		$this->requireDb( 'scan_results' );
		$this->requireDb( 'scan_result_items' );
		$this->requireDb( 'scan_result_item_meta' );
		$this->requireDb( 'file_locker' );

		$this->loginAsSecurityAdmin();
		\delete_site_transient( 'update_plugins' );
	}

	public function tear_down() {
		if ( static::con() !== null ) {
			static::con()->comps->file_locker->clearLocks();
		}
		\delete_site_transient( 'update_plugins' );
		parent::tear_down();
	}

	private function setPluginUpdateAvailable() :void {
		$updates = new \stdClass();
		$updates->response = [
			self::con()->base_file => (object)[
				'plugin'      => self::con()->base_file,
				'new_version' => self::con()->cfg->version().'.1',
			],
		];
		\set_site_transient( 'update_plugins', $updates );
	}

	public function test_build_action_items_includes_operational_issue() :void {
		$this->setPluginUpdateAvailable();

		$items = ( new AttentionItemsProvider() )->buildActionItems();
		$itemsByKey = [];
		foreach ( $items as $item ) {
			$itemsByKey[ (string)( $item[ 'key' ] ?? '' ) ] = $item;
		}

		$this->assertArrayHasKey( 'wp_plugins_updates', $itemsByKey );
		$this->assertSame( 'maintenance', (string)( $itemsByKey[ 'wp_plugins_updates' ][ 'zone' ] ?? '' ) );
		$this->assertSame( 1, (int)( $itemsByKey[ 'wp_plugins_updates' ][ 'count' ] ?? 0 ) );
	}

	public function test_build_action_summary_reports_warning_for_maintenance_item() :void {
		$this->setPluginUpdateAvailable();

		$summary = ( new AttentionItemsProvider() )->buildActionSummary();
		$this->assertGreaterThanOrEqual( 1, (int)( $summary[ 'total' ] ?? 0 ) );
		$this->assertSame( 'warning', (string)( $summary[ 'severity' ] ?? '' ) );
		$this->assertFalse( (bool)( $summary[ 'is_all_clear' ] ?? true ) );
	}

	public function test_build_scan_items_link_to_actions_queue_scans() :void {
		self::con()->opts
			->optSet( 'enable_core_file_integrity_scan', 'Y' )
			->optSet( 'file_scan_areas', [ 'wp' ] )
			->store();

		$scanId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertScanResultMeta( $scanId, 'is_in_core' );
		$this->resetScanResultCountMemoization();

		$items = ( new AttentionItemsProvider() )->buildScanItems();
		$itemsByKey = [];
		foreach ( $items as $item ) {
			$itemsByKey[ (string)( $item[ 'key' ] ?? '' ) ] = $item;
		}

		$this->assertSame(
			self::con()->plugin_urls->actionsQueueScans(),
			(string)( $itemsByKey[ 'wp_files' ][ 'href' ] ?? '' )
		);
	}

	public function test_build_scan_items_uses_affected_asset_counts_and_includes_file_locker_problems() :void {
		$this->enablePremiumCapabilities( [
			'scan_pluginsthemes_local',
			'scan_file_locker',
		] );

		self::con()->opts
			->optSet( 'enable_core_file_integrity_scan', 'Y' )
			->optSet( 'file_scan_areas', [ 'plugins', 'themes' ] )
			->optSet( 'file_locker', [ 'wpconfig' ] )
			->store();

		$pluginSlug = self::con()->base_file;
		$themeSlug = \wp_get_theme()->get_stylesheet();

		$scanId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertScanResultItem( $scanId, [
			'item_id'      => 'plugin-file-one.php',
			'is_in_plugin' => 1,
			'ptg_slug'     => $pluginSlug,
		] );
		TestDataFactory::insertScanResultItem( $scanId, [
			'item_id'      => 'plugin-file-two.php',
			'is_in_plugin' => 1,
			'ptg_slug'     => $pluginSlug,
		] );
		TestDataFactory::insertScanResultItem( $scanId, [
			'item_id'     => 'theme-file.php',
			'is_in_theme' => 1,
			'ptg_slug'    => $themeSlug,
		] );

		$this->insertFileLockRecord( 'wpconfig', ABSPATH.'wp-config.php', \time() );
		self::con()->comps->file_locker->clearLocks();
		$this->resetScanResultCountMemoization();

		$items = ( new AttentionItemsProvider() )->buildScanItems();
		$itemsByKey = [];
		foreach ( $items as $item ) {
			$itemsByKey[ (string)( $item[ 'key' ] ?? '' ) ] = $item;
		}

		$this->assertSame( 1, (int)( $itemsByKey[ 'plugin_files' ][ 'count' ] ?? 0 ) );
		$this->assertSame( 'Plugins', (string)( $itemsByKey[ 'plugin_files' ][ 'label' ] ?? '' ) );
		$this->assertSame( 1, (int)( $itemsByKey[ 'theme_files' ][ 'count' ] ?? 0 ) );
		$this->assertSame( 'Themes', (string)( $itemsByKey[ 'theme_files' ][ 'label' ] ?? '' ) );
		$this->assertSame( 1, (int)( $itemsByKey[ 'file_locker' ][ 'count' ] ?? 0 ) );
	}

	public function test_build_scan_items_hide_disabled_historical_scan_results() :void {
		self::con()->opts
			->optSet( 'enable_core_file_integrity_scan', 'N' )
			->optSet( 'file_scan_areas', [] )
			->store();

		$pluginSlug = self::con()->base_file;
		$themeSlug = \wp_get_theme()->get_stylesheet();

		$scanId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertScanResultItem( $scanId, [
			'item_id'    => 'wp-admin/admin.php',
			'is_in_core' => 1,
		] );
		TestDataFactory::insertScanResultItem( $scanId, [
			'item_id'      => 'plugin-file.php',
			'is_in_plugin' => 1,
			'ptg_slug'     => $pluginSlug,
		] );
		TestDataFactory::insertScanResultItem( $scanId, [
			'item_id'     => 'theme-file.php',
			'is_in_theme' => 1,
			'ptg_slug'    => $themeSlug,
		] );
		$this->resetScanResultCountMemoization();

		$items = ( new AttentionItemsProvider() )->buildScanItems();

		$this->assertNotContains( 'wp_files', \array_column( $items, 'key' ) );
		$this->assertNotContains( 'plugin_files', \array_column( $items, 'key' ) );
		$this->assertNotContains( 'theme_files', \array_column( $items, 'key' ) );
	}

	public function test_build_scan_items_hide_file_locker_when_premium_unavailable() :void {
		self::con()->opts
			->optSet( 'file_locker', [ 'wpconfig' ] )
			->store();

		$this->insertFileLockRecord( 'wpconfig', ABSPATH.'wp-config.php', \time() );
		self::con()->comps->file_locker->clearLocks();

		$items = ( new AttentionItemsProvider() )->buildScanItems();

		$this->assertNotContains( 'file_locker', \array_column( $items, 'key' ) );
	}

	private function insertFileLockRecord( string $type, string $path, int $detectedAt = 0 ) :void {
		$handler = $this->requireDb( 'file_locker' );
		$record = $handler->getRecord();
		$record->type = $type;
		$record->path = $path;
		$record->hash_original = \sha1( $type.'-original' );
		$record->hash_current = \sha1( $type.'-current' );
		$record->public_key_id = 1;
		$record->cipher = 'aes-256-cbc';
		$record->content = 'encrypted-content-'.$type;
		$record->detected_at = $detectedAt;
		$handler->getQueryInserter()->insert( $record );
	}
}
